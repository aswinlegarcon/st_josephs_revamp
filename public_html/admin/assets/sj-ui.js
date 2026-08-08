/* SJUI — shared admin UI core (M2/M3 modals, picker, forms, rich editor).
 * Extracted from panel.js in O2 so BOTH the standalone panel and the on-page
 * live-edit overlay (js/admin.js) use the SAME modals and API plumbing.
 * Exposes window.SJUI. Loaded before panel.js / admin.js.
 */
(function () {
  'use strict';

  var CSRF = (document.querySelector('meta[name="sj-csrf"]') || {}).content || '';

  /* ---------------- tiny helpers ---------------- */
  function el(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html !== undefined) n.innerHTML = html;
    return n;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function toast(msg, isErr) {
    var t = el('div', 'sj-toast' + (isErr ? ' err' : ''), esc(msg));
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, isErr ? 3800 : 1800);
  }
  function api(path, payload) {
    // Route through the front controller: 'item.php' -> index.php?r=item
    var route = path.replace(/\.php$/, '');
    return fetch('/admin/api/index.php?r=' + route, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(payload || {})
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) throw new Error(j.error || 'Request failed');
      return j;
    });
  }

  /* ---------------- preset metadata (M3, CSP-safe via body data-attr) ------ */
  var SJ_PRESETS = [];
  try { SJ_PRESETS = JSON.parse(document.body.getAttribute('data-sj-presets') || '[]'); } catch (e) {}
  function presetMeta(key) {
    for (var i = 0; i < SJ_PRESETS.length; i++) {
      if (SJ_PRESETS[i].preset_key === key) return SJ_PRESETS[i];
    }
    return null;
  }

  /* ---------------- modal scaffolding ---------------- */
  function openModal(title) {
    var ov = el('div', 'sj-overlay');
    var m = el('div', 'sj-modal');
    var head = el('div', 'sj-modal-head', esc(title));
    var x = el('button', 'sj-x', '×'); x.type = 'button';
    head.appendChild(x);
    var body = el('div', 'sj-modal-body');
    var foot = el('div', 'sj-modal-foot');
    m.appendChild(head); m.appendChild(body); m.appendChild(foot);
    ov.appendChild(m);
    document.body.appendChild(ov);
    function close() { ov.remove(); }
    x.addEventListener('click', close);
    ov.addEventListener('mousedown', function (e) { if (e.target === ov) close(); });
    return { ov: ov, body: body, foot: foot, close: close };
  }

  /* ---------------- WYSIWYG rich editor ----------------
   * The admin sees formatted text exactly like the website — never raw tags.
   * HTML (p/br/strong/span.hl-gold) is produced behind the scenes and the server
   * sanitizes it again on save.
   */
  try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

  function buildRichToolbar(bar, editor) {
    function btn(html, title, fn) {
      var b = el('button', '', html);
      b.type = 'button'; b.title = title;
      b.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep selection
      b.addEventListener('click', function (e) { e.preventDefault(); editor.focus(); fn(); });
      bar.appendChild(b);
      return b;
    }
    btn('<b>B</b>', 'Bold', function () { document.execCommand('bold'); });
    btn('<i>I</i>', 'Italic', function () { document.execCommand('italic'); });
    var g = btn('Gold', 'Gold highlight', function () {
      var sel = window.getSelection();
      if (!sel.rangeCount || sel.isCollapsed) { toast('Select some text first', true); return; }
      var range = sel.getRangeAt(0);
      var span = document.createElement('span');
      span.className = 'hl-gold';
      try {
        range.surroundContents(span);
      } catch (err) { // selection crosses element boundaries
        span.appendChild(range.extractContents());
        range.insertNode(span);
      }
      sel.removeAllRanges();
    });
    g.classList.add('gold');
    btn('✕ fmt', 'Remove formatting from selection', function () {
      document.execCommand('removeFormat');
      var sel = window.getSelection();
      if (!sel.rangeCount) return;
      var range = sel.getRangeAt(0);
      editor.querySelectorAll('span.hl-gold').forEach(function (s) {
        if (range.intersectsNode(s)) {
          while (s.firstChild) s.parentNode.insertBefore(s.firstChild, s);
          s.remove();
        }
      });
    });
  }

  function initRichEditor(editor, bar) {
    buildRichToolbar(bar, editor);
    editor.addEventListener('paste', function (e) { // paste as plain text
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });
  }

  /** Rich control for modal forms. */
  function makeRichControl(initialHtml) {
    var wrap = el('div', 'sj-richwrap');
    var bar = el('div', 'sj-richbar');
    var ed = el('div', 'sj-rich');
    ed.contentEditable = 'true';
    ed.innerHTML = initialHtml || '';
    wrap.appendChild(bar); wrap.appendChild(ed);
    initRichEditor(ed, bar);
    return { node: wrap, get: function () { return ed.innerHTML.trim(); } };
  }

  /* ---------------- image picker (library + upload with auto-crop) ---------------- */
  function pickImage(preset, opts) {
    opts = opts || {};
    return new Promise(function (resolve, reject) {
      var M = openModal(opts.title || 'Choose an image');
      var tabs = el('div', 'sj-tabs');
      var tLib = el('button', 'on', '📚 Library');
      var tUp = el('button', '', '⬆️ Upload new');
      tLib.type = tUp.type = 'button';
      tabs.appendChild(tLib); tabs.appendChild(tUp);
      M.body.appendChild(tabs);
      var pane = el('div');
      M.body.appendChild(pane);
      var settled = false;
      function done(img) { settled = true; M.close(); resolve(img); }
      M.ov.addEventListener('mousedown', function (e) {
        if (e.target === M.ov && !settled) reject(new Error('cancel'));
      });

      function showLibrary() {
        tLib.className = 'on'; tUp.className = '';
        pane.innerHTML = '';
        var search = document.createElement('input');
        search.type = 'text'; search.placeholder = 'Search by file name…'; search.className = 'sj-search';
        var grid = el('div', 'sj-grid');
        var more = el('button', 'sj-btn sj-btn-ghost sj-more', 'Load more'); more.type = 'button';
        pane.appendChild(search); pane.appendChild(grid); pane.appendChild(more);
        var page = 0, q = '';
        function load(reset) {
          if (reset) { grid.innerHTML = ''; page = 0; }
          fetch('/admin/api/index.php?r=images&q=' + encodeURIComponent(q) + '&page=' + page)
            .then(function (r) { return r.json(); })
            .then(function (j) {
              if (!j.ok) throw new Error(j.error || 'load failed');
              j.items.forEach(function (it) {
                var b = el('button', 'sj-pick');
                b.type = 'button';
                b.innerHTML = '<img loading="lazy" src="' + esc(it.thumb) + '"><span>' + esc(it.label) + '</span>';
                b.addEventListener('click', function () { done({ id: it.id, thumb: it.thumb }); });
                grid.appendChild(b);
              });
              more.style.display = j.hasMore ? '' : 'none';
            }).catch(function (e) { toast(e.message, true); });
        }
        var deb;
        search.addEventListener('input', function () {
          clearTimeout(deb);
          deb = setTimeout(function () { q = search.value.trim(); load(true); }, 300);
        });
        more.addEventListener('click', function () { page++; load(false); });
        load(true);
      }

      function showUpload() {
        tUp.className = 'on'; tLib.className = '';
        pane.innerHTML = '';
        var chosenPreset = preset;
        if (opts.presetChoice && SJ_PRESETS.length) {
          pane.appendChild(el('label', '', 'Crop shape (where will this image be used?)'));
          var sel = document.createElement('select');
          SJ_PRESETS.forEach(function (p) {
            var o = el('option', '', esc(p.label));
            o.value = p.preset_key;
            sel.appendChild(o);
          });
          pane.appendChild(sel);
          chosenPreset = sel.value;
          sel.addEventListener('change', function () { chosenPreset = sel.value; });
        }
        pane.appendChild(el('label', '', 'Image file (JPEG / PNG / WebP, max 10 MB)'));
        var file = document.createElement('input');
        file.type = 'file'; file.accept = 'image/jpeg,image/png,image/webp';
        pane.appendChild(file);
        pane.appendChild(el('label', '', 'Description (optional)'));
        var alt = document.createElement('input'); alt.type = 'text';
        pane.appendChild(alt);
        // M3: crop stage — appears once a file is chosen (cover-shape presets only)
        var cropHost = el('div', 'sj-crophost');
        pane.appendChild(cropHost);
        var cropper = null;
        function mountCropper() {
          var meta = presetMeta(chosenPreset);
          if (cropper) { cropper.destroy(); cropper = null; }
          cropHost.innerHTML = '';
          if (!file.files.length || !window.Cropper || !meta || meta.mode !== 'cover' || !meta.aspect_w) return;
          cropHost.appendChild(el('label', '', '✂️ Choose the crop (locked to the slot shape)'));
          var img = document.createElement('img');
          img.className = 'sj-cropimg';
          img.src = URL.createObjectURL(file.files[0]);
          cropHost.appendChild(img);
          cropper = new Cropper(img, {
            aspectRatio: meta.aspect_w / meta.aspect_h,
            viewMode: 1, autoCropArea: 1, movable: false, zoomable: false, rotatable: false, scalable: false
          });
        }
        file.addEventListener('change', mountCropper);
        pane.querySelectorAll('select').forEach(function (s) { s.addEventListener('change', mountCropper); });
        pane.appendChild(el('div', 'sj-upnote',
          '✂️ Uploads are <b>cropped to the exact shape this spot needs</b> (drag the box above to choose ' +
          'what stays) and lightly <b>compressed</b> (JPEG + WebP) — cards and slides always stay uniform and fast.'));
        var go = el('button', 'sj-btn sj-btn-primary', 'Upload'); go.type = 'button';
        go.style.marginTop = '14px';
        pane.appendChild(go);
        go.addEventListener('click', function () {
          if (!file.files.length) { toast('Choose a file first', true); return; }
          go.disabled = true; go.textContent = 'Uploading…';
          var fd = new FormData();
          fd.append('file', file.files[0]);
          fd.append('preset', chosenPreset || '');
          fd.append('alt', alt.value);
          if (cropper) {
            var d = cropper.getData(true); // natural-size coordinates, rounded
            fd.append('crop_rect', Math.max(0, d.x) + ',' + Math.max(0, d.y) + ',' + d.width + ',' + d.height);
          }
          fetch('/admin/api/index.php?r=upload', { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              if (!j.ok) throw new Error(j.error || 'Upload failed');
              toast('Uploaded ✔');
              done({ id: j.image_id, thumb: j.url });
            })
            .catch(function (e) { toast(e.message, true); go.disabled = false; go.textContent = 'Upload'; });
        });
      }

      tLib.addEventListener('click', showLibrary);
      tUp.addEventListener('click', showUpload);
      if (opts.startOnUpload) showUpload(); else showLibrary();
    });
  }

  /* ---------------- registry-driven form ---------------- */
  function control(f, value, thumb) {
    var wrapper = el('div');
    var getter;
    if (f.type === 'bool') {
      var lab = el('label', 'sj-check');
      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.checked = (value === null || value === undefined) ? true : !!Number(value);
      lab.appendChild(cb);
      lab.appendChild(document.createTextNode(' ' + f.label));
      wrapper.appendChild(lab);
      getter = function () { return cb.checked ? 1 : 0; };
      return { node: wrapper, get: getter, field: f };
    }
    wrapper.appendChild(el('label', '', esc(f.label) + (f.required ? ' *' : '')));
    if (f.type === 'html') {
      var rc = makeRichControl(value || '');
      wrapper.appendChild(rc.node);
      getter = rc.get;
    } else if (f.type === 'enum') {
      var sel = document.createElement('select');
      (f.options || []).forEach(function (o) {
        var op = el('option', '', esc(o) + 'th standard');
        op.value = o;
        if (String(value) === String(o)) op.selected = true;
        sel.appendChild(op);
      });
      wrapper.appendChild(sel);
      getter = function () { return sel.value; };
    } else if (f.type === 'image') {
      var holder = el('div', 'sj-imgfield');
      var img = thumb ? el('img') : el('div', 'sj-noimg', 'no image');
      if (thumb) img.src = thumb;
      var chosenId = value ? Number(value) : null;
      var btn = el('button', 'sj-btn sj-btn-ghost', chosenId ? '📷 Change image' : '📷 Choose image');
      btn.type = 'button';
      btn.addEventListener('click', function () {
        pickImage(f.preset).then(function (p) {
          chosenId = p.id;
          var ni = el('img'); ni.src = p.thumb;
          holder.replaceChild(ni, holder.firstChild);
          btn.textContent = '📷 Change image';
        }).catch(function () {});
      });
      holder.appendChild(img); holder.appendChild(btn);
      wrapper.appendChild(holder);
      getter = function () { return chosenId; };
    } else if (f.multiline) {
      var ta = document.createElement('textarea');
      ta.rows = 5;
      ta.value = value == null ? '' : value;
      wrapper.appendChild(ta);
      getter = function () { return ta.value; };
    } else {
      var input = document.createElement('input');
      input.type = f.type === 'int' ? 'number' : 'text';
      input.value = value == null ? '' : value;
      wrapper.appendChild(input);
      getter = function () { return input.value; };
    }
    return { node: wrapper, get: getter, field: f };
  }

  function openForm(opts) {
    // opts: {title, fields, values?, thumbs?, submitLabel, onSubmit(data)→Promise}
    var M = openModal(opts.title);
    var controls = [];
    opts.fields.forEach(function (f) {
      var v = opts.values ? opts.values[f.name] : null;
      var c = control(f, v, opts.thumbs ? opts.thumbs[f.name] : null);
      controls.push(c);
      M.body.appendChild(c.node);
    });
    var save = el('button', 'sj-btn sj-btn-primary', opts.submitLabel || 'Save');
    var cancel = el('button', 'sj-btn sj-btn-ghost', 'Cancel');
    save.type = cancel.type = 'button';
    M.foot.appendChild(cancel); M.foot.appendChild(save);
    cancel.addEventListener('click', M.close);
    var first = M.body.querySelector('input[type="text"],input[type="number"]');
    if (first) first.focus();
    save.addEventListener('click', function () {
      var data = {};
      for (var i = 0; i < controls.length; i++) {
        var c = controls[i];
        var v = c.get();
        if (c.field.required && (v === null || v === '')) {
          toast(c.field.label + ' is required', true);
          return;
        }
        data[c.field.name] = v;
      }
      save.disabled = true;
      opts.onSubmit(data)
        .then(function () { M.close(); })
        .catch(function (e) { toast(e.message, true); save.disabled = false; });
    });
  }


  window.SJUI = {
    CSRF: CSRF,
    el: el,
    esc: esc,
    toast: toast,
    api: api,
    presets: SJ_PRESETS,
    presetMeta: presetMeta,
    openModal: openModal,
    buildRichToolbar: buildRichToolbar,
    initRichEditor: initRichEditor,
    makeRichControl: makeRichControl,
    pickImage: pickImage,
    control: control,
    openForm: openForm
  };
})();
