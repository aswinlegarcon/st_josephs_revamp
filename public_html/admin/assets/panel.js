/* St.Joseph's standalone admin panel engine.
 * Everything posts to /admin/api/* (session + CSRF) and the public site simply
 * reads the database on its next request — no editing happens on the live page.
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
        if (opts.presetChoice && window.SJ_PRESETS) {
          pane.appendChild(el('label', '', 'Crop shape (where will this image be used?)'));
          var sel = document.createElement('select');
          window.SJ_PRESETS.forEach(function (p) {
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
        pane.appendChild(el('div', 'sj-upnote',
          '✂️ Uploads are <b>auto-cropped</b> to the exact shape this spot needs and lightly ' +
          '<b>compressed</b> (JPEG + WebP) — cards and slides always stay uniform and fast.'));
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

  /* ---------------- list / row actions (event delegation) ---------------- */
  document.addEventListener('click', function (e) {
    var addBtn = e.target.closest('[data-panel-add]');
    if (addBtn) {
      var meta;
      try { meta = JSON.parse(addBtn.getAttribute('data-panel-add')); } catch (err) { return; }
      openForm({
        title: meta.label || 'Add',
        fields: meta.fields,
        submitLabel: '＋ Add',
        onSubmit: function (data) {
          return api('item.php', { action: 'create', entity: meta.entity, data: data, preset: meta.preset || {} })
            .then(function () { toast('Added ✔'); location.reload(); });
        }
      });
      return;
    }

    var actBtn = e.target.closest('[data-act]');
    if (!actBtn) return;
    var act = actBtn.getAttribute('data-act');

    /* principal form actions */
    if (act === 'pick-principal-photo') {
      pickImage(actBtn.getAttribute('data-preset') || 'portrait_4x5').then(function (p) {
        document.getElementById('sj-principal-img').value = p.id;
        document.getElementById('sj-principal-thumb').src = p.thumb;
        toast('Photo selected — press Save changes');
      }).catch(function () {});
      return;
    }
    if (act === 'save-principal') {
      var card = document.getElementById('sj-principal');
      actBtn.disabled = true;
      api('item.php', {
        action: 'update',
        entity: card.getAttribute('data-entity'),
        id: +card.getAttribute('data-id'),
        data: {
          heading: document.getElementById('sj-principal-heading').value,
          person_name: document.getElementById('sj-principal-name').value,
          message_html: document.getElementById('sj-principal-msg').innerHTML.trim(),
          image_id: +document.getElementById('sj-principal-img').value || null
        }
      }).then(function () {
        toast('Saved ✔ — live on the site');
        document.getElementById('sj-principal-msg-state').textContent = 'Saved ✔';
        actBtn.disabled = false;
      }).catch(function (err) { toast(err.message, true); actBtn.disabled = false; });
      return;
    }

    /* generic row actions */
    var row = actBtn.closest('[data-row]');
    if (!row) return;
    var ref = row.getAttribute('data-row').split(':');
    var entity = ref[0], id = +ref[1];

    if (act === 'edit') {
      api('item.php', { action: 'get', entity: entity, id: id }).then(function (j) {
        openForm({
          title: 'Edit',
          fields: j.fields,
          values: j.values,
          thumbs: j.thumbs || {},
          onSubmit: function (data) {
            return api('item.php', { action: 'update', entity: entity, id: id, data: data })
              .then(function () { toast('Saved ✔'); location.reload(); });
          }
        });
      }).catch(function (err) { toast(err.message, true); });
    } else if (act === 'move') {
      var list = row.closest('[data-list]');
      if (!list) return;
      var rows = Array.prototype.slice.call(list.querySelectorAll('[data-row^="' + entity + ':"]'));
      var idx = rows.indexOf(row);
      var to = idx + (+actBtn.getAttribute('data-dir'));
      if (to < 0 || to >= rows.length) return;
      var ids = rows.map(function (n) { return +n.getAttribute('data-row').split(':')[1]; });
      var t = ids[idx]; ids[idx] = ids[to]; ids[to] = t;
      api('order.php', { entity: entity, ids: ids })
        .then(function () { location.reload(); })
        .catch(function (err) { toast(err.message, true); });
    } else if (act === 'toggle') {
      var now = actBtn.getAttribute('data-active') === '1';
      api('field.php', { entity: entity, id: id, field: 'is_active', value: now ? 0 : 1 })
        .then(function () { location.reload(); })
        .catch(function (err) { toast(err.message, true); });
    } else if (act === 'del') {
      var msg = actBtn.getAttribute('data-confirm') || 'Delete this item? This cannot be undone.';
      if (!confirm(msg)) return;
      api('item.php', { action: 'delete', entity: entity, id: id })
        .then(function () { toast('Deleted'); location.reload(); })
        .catch(function (err) { toast(err.message, true); });
    }
  });

  /* ---------------- principal page: init its rich editor ---------------- */
  var pMsg = document.getElementById('sj-principal-msg');
  if (pMsg) {
    initRichEditor(pMsg, document.querySelector('.sj-richbar[data-for="sj-principal-msg"]'));
  }

  /* ---------------- media library page ---------------- */
  var mediaGrid = document.getElementById('sj-media-grid');
  if (mediaGrid) {
    var mSearch = document.getElementById('sj-media-search');
    var mMore = document.getElementById('sj-media-more');
    var mPage = 0, mQ = '';
    function loadMedia(reset) {
      if (reset) { mediaGrid.innerHTML = ''; mPage = 0; }
      fetch('/admin/api/index.php?r=images&q=' + encodeURIComponent(mQ) + '&page=' + mPage)
        .then(function (r) { return r.json(); })
        .then(function (j) {
          j.items.forEach(function (it) {
            var d = el('div', 'sj-pick');
            d.innerHTML = '<img loading="lazy" src="' + esc(it.thumb) + '"><span>' + esc(it.label) + '</span>';
            mediaGrid.appendChild(d);
          });
          mMore.style.display = j.hasMore ? '' : 'none';
        });
    }
    var mDeb;
    mSearch.addEventListener('input', function () {
      clearTimeout(mDeb);
      mDeb = setTimeout(function () { mQ = mSearch.value.trim(); loadMedia(true); }, 300);
    });
    mMore.addEventListener('click', function () { mPage++; loadMedia(false); });
    document.getElementById('sj-media-upload').addEventListener('click', function () {
      pickImage(null, { presetChoice: true, startOnUpload: true, title: 'Upload image' })
        .then(function () { loadMedia(true); })
        .catch(function () {});
    });
    loadMedia(true);
  }
})();
