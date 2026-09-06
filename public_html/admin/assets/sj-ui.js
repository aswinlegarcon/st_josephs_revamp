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
  /* N7: professional inline-SVG icons (Feather-style) — the JS twin of the
     PHP sj_icon() helper. No emojis anywhere in the admin UI. */
  var ICONS = {
    check: '<polyline points="20 6 9 17 4 12"/>',
    alert: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    camera: '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
    crop: '<path d="M6.13 1L6 16a2 2 0 0 0 2 2h15"/><path d="M1 6.13L16 6a2 2 0 0 1 2 2v15"/>',
    trash: '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
    plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    key: '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    copy: '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    unlock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'
  };
  function icon(name, size) {
    var body = ICONS[name];
    if (!body) return '';
    var s = size || 16;
    return '<svg class="sj-svg" width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
      ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + body + '</svg>';
  }
  function toast(msg, isErr) {
    var t = el('div', 'sj-toast' + (isErr ? ' err' : ''), icon(isErr ? 'alert' : 'check') + '<span>' + esc(msg) + '</span>');
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, isErr ? 3800 : 2200);
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
    var ret;
    function close() {
      document.removeEventListener('keydown', onKey);
      ov.remove();
      if (ret.onClose) ret.onClose();
    }
    // K2: Escape closes the TOPMOST open modal (stacking-safe — each modal
    // only reacts when it is the last overlay in the DOM).
    function onKey(e) {
      if (e.key !== 'Escape') return;
      var all = document.querySelectorAll('.sj-overlay');
      if (all[all.length - 1] !== ov) return;
      e.preventDefault();
      close();
    }
    document.addEventListener('keydown', onKey);
    x.addEventListener('click', close);
    ov.addEventListener('mousedown', function (e) { if (e.target === ov) close(); });
    ret = { ov: ov, body: body, foot: foot, close: close, onClose: null };
    return ret;
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

    // Un-wraps every gold span the range touches (shared: Gold toggle + Clear).
    function unGold(range) {
      editor.querySelectorAll('span.hl-gold').forEach(function (s) {
        if (range.intersectsNode(s)) {
          while (s.firstChild) s.parentNode.insertBefore(s.firstChild, s);
          s.remove();
        }
      });
    }
    // The current selection range, but only when it lives inside THIS editor.
    function selectionRange() {
      var sel = window.getSelection();
      if (!sel.rangeCount) return null;
      var r = sel.getRangeAt(0);
      return editor.contains(r.commonAncestorContainer) ? r : null;
    }
    function goldActive() {
      var r = selectionRange();
      if (!r) return false;
      var node = r.commonAncestorContainer;
      if (node.nodeType === 3) node = node.parentNode;
      if (node.closest && node.closest('span.hl-gold')) return true;
      var spans = editor.querySelectorAll('span.hl-gold');
      for (var i = 0; i < spans.length; i++) {
        if (r.intersectsNode(spans[i])) return true;
      }
      return false;
    }

    var bB = btn('<b>B</b>', 'Bold — click again to remove (Ctrl+B)', function () {
      document.execCommand('bold');
      refresh();
    });
    var bI = btn('<i>I</i>', 'Italic — click again to remove (Ctrl+I)', function () {
      document.execCommand('italic');
      refresh();
    });
    var bG = btn('Gold', 'Gold highlight — click again to remove', function () {
      var sel = window.getSelection();
      if (!sel.rangeCount || sel.isCollapsed) { toast('Select some text first', true); return; }
      var range = sel.getRangeAt(0);
      if (goldActive()) { // K2: Gold is a real toggle now
        unGold(range);
      } else {
        var span = document.createElement('span');
        span.className = 'hl-gold';
        try {
          range.surroundContents(span);
        } catch (err) { // selection crosses element boundaries
          span.appendChild(range.extractContents());
          range.insertNode(span);
        }
      }
      sel.removeAllRanges();
      refresh();
    });
    bG.classList.add('gold');
    bar.appendChild(el('span', 'sep'));
    btn('Clear', 'Remove ALL formatting from the selection', function () {
      document.execCommand('removeFormat');
      var r = selectionRange();
      if (r) unGold(r);
      refresh();
    });

    // K2: live toggle states — B / I / Gold light up (.on) whenever the caret
    // or selection carries that format, so the admin always SEES what is
    // applied and one click turns it off. Driven by selectionchange; inert
    // once the editor leaves the DOM.
    function refresh() {
      var inside = !!selectionRange();
      var can = false, itl = false;
      if (inside) {
        try { can = document.queryCommandState('bold'); itl = document.queryCommandState('italic'); } catch (e) {}
      }
      bB.classList.toggle('on', inside && can);
      bI.classList.toggle('on', inside && itl);
      bG.classList.toggle('on', inside && goldActive());
    }
    document.addEventListener('selectionchange', function () {
      if (!document.contains(editor)) return;
      refresh();
    });
    refresh();
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
      // K12: opts.multi lets the Library tab select SEVERAL images at once
      // (click to toggle, then "Add selected"). Single-pick callers are
      // unchanged — they never pass multi, so a tile click resolves at once.
      var multi = !!opts.multi;
      var selected = []; // {id, thumb} — multi mode only
      var M = openModal(opts.title || (multi ? 'Add photos' : 'Choose an image'));
      var tabs = el('div', 'sj-tabs');
      var tLib = el('button', 'on', icon('image', 14) + ' Library');
      var tUp = el('button', '', icon('upload', 14) + ' Upload new');
      tLib.type = tUp.type = 'button';
      tabs.appendChild(tLib); tabs.appendChild(tUp);
      M.body.appendChild(tabs);
      var pane = el('div');
      M.body.appendChild(pane);
      var settled = false;
      function done(img) { settled = true; M.close(); resolve(img); }

      function selIndex(id) {
        for (var i = 0; i < selected.length; i++) { if (selected[i].id === id) return i; }
        return -1;
      }
      var addSelBtn = null;
      function updateSelBtn() {
        if (!addSelBtn) return;
        addSelBtn.textContent = 'Add selected (' + selected.length + ')';
        addSelBtn.disabled = selected.length === 0;
      }
      if (multi) {
        var hint = el('span', 'sj-hint', 'Tap images to select — you can pick several.');
        hint.style.marginRight = 'auto';
        addSelBtn = el('button', 'sj-btn sj-btn-primary', 'Add selected (0)');
        addSelBtn.type = 'button'; addSelBtn.disabled = true;
        addSelBtn.addEventListener('click', function () {
          if (!selected.length) return;
          settled = true; M.close(); resolve(selected.slice());
        });
        M.foot.appendChild(hint);
        M.foot.appendChild(addSelBtn);
      }
      // Any close path (X, backdrop, Escape) that isn't a pick = cancel.
      M.onClose = function () {
        if (!settled) { settled = true; reject(new Error('cancel')); }
      };
      M.ov.addEventListener('mousedown', function (e) {
        if (e.target === M.ov && !settled) { settled = true; reject(new Error('cancel')); }
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
                if (multi && selIndex(it.id) >= 0) b.classList.add('sj-pick-sel');
                b.addEventListener('click', function () {
                  if (!multi) { done({ id: it.id, thumb: it.thumb }); return; }
                  var i = selIndex(it.id);
                  if (i >= 0) { selected.splice(i, 1); b.classList.remove('sj-pick-sel'); }
                  else { selected.push({ id: it.id, thumb: it.thumb }); b.classList.add('sj-pick-sel'); }
                  updateSelBtn();
                });
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
        // K12: naming guidance — name the file clearly BEFORE choosing it, so it
        // is easy to find later in the Library search.
        pane.appendChild(el('small', 'sj-hint',
          'Tip: give the file a clear name before uploading — e.g. annual-day-2026.jpg or science-lab.jpg ' +
          '(lowercase letters, numbers and dashes; no spaces). You can search the Library by this name later.'));
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
          cropHost.appendChild(el('label', '', icon('crop', 13) + ' Choose the crop (locked to the slot shape)'));
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
          'Uploads are <b>cropped to the exact shape this spot needs</b> (drag the box above to choose ' +
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
              if (multi) {
                selected.push({ id: j.image_id, thumb: j.url });
                updateSelBtn();
                toast('Uploaded — added to selection');
                showLibrary(); // back to the grid so they can keep selecting
              } else {
                toast('Uploaded');
                done({ id: j.image_id, thumb: j.url });
              }
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
        // Only the numeric enums (the marks standards: 10/11/12) get the
        // friendly "th standard" suffix; word enums (role, achievement type)
        // show the value itself — the old unconditional suffix produced
        // "ownerth standard" / "achievementth standard".
        var label = /^\d+$/.test(String(o)) ? o + 'th standard' : String(o);
        var op = el('option', '', esc(label));
        op.value = o;
        if (String(value) === String(o)) op.selected = true;
        sel.appendChild(op);
      });
      wrapper.appendChild(sel);
      getter = function () { return sel.value; };
    } else if (f.type === 'pagelink') {
      // K12: real-pages dropdown (grouped) so a button can't point nowhere.
      var psel = document.createElement('select');
      var cur = value == null ? '' : String(value);
      var found = false;
      (f.options || []).forEach(function (grp) {
        var og = document.createElement('optgroup');
        og.label = grp.group || '';
        (grp.items || []).forEach(function (it) {
          var op = el('option', '', esc(it.label));
          op.value = it.value;
          if (cur === String(it.value)) { op.selected = true; found = true; }
          og.appendChild(op);
        });
        psel.appendChild(og);
      });
      if (!found && cur !== '') {
        // preserve a legacy/custom value that isn't in the list
        var oc = el('option', '', esc(cur + '  (current)'));
        oc.value = cur; oc.selected = true;
        psel.insertBefore(oc, psel.firstChild);
      }
      wrapper.appendChild(psel);
      getter = function () { return psel.value; };
    } else if (f.type === 'image') {
      var holder = el('div', 'sj-imgfield');
      var img = thumb ? el('img') : el('div', 'sj-noimg', 'no image');
      if (thumb) img.src = thumb;
      var chosenId = value ? Number(value) : null;
      var btn = el('button', 'sj-btn sj-btn-ghost', icon('camera', 15) + (chosenId ? ' Change image' : ' Choose image'));
      btn.type = 'button';
      btn.addEventListener('click', function () {
        pickImage(f.preset).then(function (p) {
          chosenId = p.id;
          var ni = el('img'); ni.src = p.thumb;
          holder.replaceChild(ni, holder.firstChild);
          btn.innerHTML = icon('camera', 15) + ' Change image';
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
      if (f.type === 'slug') {
        // K12: a slug becomes the page's web address (…/<slug>.php), so show
        // exactly how to name it. Live-sanitise as they type.
        input.placeholder = 'e.g. science-academy';
        input.addEventListener('input', function () {
          input.value = input.value.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+/, '');
        });
        wrapper.appendChild(input);
        wrapper.appendChild(el('small', 'sj-hint',
          'This becomes the page address (e.g. “science-academy” → /science-academy.php). ' +
          'Lowercase letters, numbers and dashes only — no spaces. It cannot be changed later.'));
      } else {
        wrapper.appendChild(input);
      }
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
    icon: icon,
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
