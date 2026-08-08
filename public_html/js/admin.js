/* On-page live-edit overlay (Stage F: O1 text editing, O2 item/image ops).
 * Loaded ONLY for logged-in admins (the shell gates on is_admin()); the editing
 * affordances activate only in edit mode (body.sj-edit-mode, toggled by the
 * admin bar's POST form). All writes go through the same CSRF'd admin API the
 * panel uses, via the shared SJUI core (admin/assets/sj-ui.js).
 *
 * Attribute grammar (emitted by _libs/edit.php only when is_edit()):
 *   data-edit-field="entity:id:field" + data-edit-type (+data-edit-options)
 *   data-edit-item="entity:id"  + data-edit-flags ("o"rderable/"d"eletable) + data-edit-label
 *   data-edit-add='{entity,preset,label,fields[]}'   (on the list container)
 *   data-edit-img="entity:id:field:preset"
 */
(function () {
  'use strict';

  if (!document.body.classList.contains('sj-edit-mode') || !window.SJUI) {
    return; // viewing as a visitor — no editing affordances
  }

  var api = SJUI.api, toast = SJUI.toast, el = SJUI.el;

  /* ================= O1 — inline text / rich editing ================= */

  var active = null; // { node, entity, id, field, type, original, bar }

  function parseRef(node) {
    var ref = node.getAttribute('data-edit-field').split(':');
    return { entity: ref[0], id: +ref[1], field: ref.slice(2).join(':'), type: node.getAttribute('data-edit-type') || 'text' };
  }

  function finishEditing(save) {
    if (!active) return;
    var a = active;
    active = null;
    a.node.classList.remove('sjov-editing');
    a.node.removeAttribute('contenteditable');
    if (a.wasContents) a.node.style.display = 'contents';
    if (a.bar) a.bar.remove();
    if (!save) {
      if (a.type === 'html') a.node.innerHTML = a.original; else a.node.textContent = a.original;
      return;
    }
    var value = a.type === 'html' ? a.node.innerHTML.trim() : a.node.textContent.trim();
    var same = a.type === 'html' ? value === a.original.trim() : value === a.original.trim();
    if (same) return;
    api('field.php', { entity: a.entity, id: a.id, field: a.field, value: value })
      .then(function (j) {
        toast('Saved ✔');
        // reflect the server-sanitized value (rich fields may get cleaned up)
        if (a.type === 'html' && typeof j.value === 'string') a.node.innerHTML = j.value;
        a.node.classList.add('sjov-saved');
        setTimeout(function () { a.node.classList.remove('sjov-saved'); }, 900);
      })
      .catch(function (err) {
        toast(err.message, true);
        if (a.type === 'html') a.node.innerHTML = a.original; else a.node.textContent = a.original;
      });
  }

  function startEditing(node) {
    if (active && active.node === node) return;
    finishEditing(true);
    var ref = parseRef(node);

    if (ref.type === 'enum') { // enum: quick select popover
      var options = (node.getAttribute('data-edit-options') || '').split(',');
      var sel = document.createElement('select');
      sel.className = 'sjov-enum';
      options.forEach(function (o) {
        var op = el('option', '', SJUI.esc(o));
        op.value = o;
        if (node.textContent.trim() === o) op.selected = true;
        sel.appendChild(op);
      });
      node.replaceChildren(sel);
      sel.focus();
      sel.addEventListener('change', function () {
        api('field.php', { entity: ref.entity, id: ref.id, field: ref.field, value: sel.value })
          .then(function () { toast('Saved ✔'); location.reload(); })
          .catch(function (err) { toast(err.message, true); location.reload(); });
      });
      return;
    }

    active = {
      node: node, entity: ref.entity, id: ref.id, field: ref.field, type: ref.type,
      original: ref.type === 'html' ? node.innerHTML : node.textContent,
      wasContents: node.style.display === 'contents',
      bar: null
    };
    if (active.wasContents) node.style.display = 'block'; // contenteditable needs a box
    node.classList.add('sjov-editing');
    node.setAttribute('contenteditable', ref.type === 'html' ? 'true' : 'plaintext-only');
    node.focus();

    if (ref.type === 'html') { // floating mini-toolbar: B / I / Gold / clear
      var bar = el('div', 'sjov-richbar sj-richbar');
      SJUI.buildRichToolbar(bar, node);
      var done = el('button', 'sjov-done', '✔ Save');
      done.type = 'button';
      done.addEventListener('mousedown', function (e) { e.preventDefault(); });
      done.addEventListener('click', function () { finishEditing(true); });
      bar.appendChild(done);
      document.body.appendChild(bar);
      var r = node.getBoundingClientRect();
      bar.style.top = Math.max(8, r.top + window.scrollY - bar.offsetHeight - 8) + 'px';
      bar.style.left = Math.max(8, r.left + window.scrollX) + 'px';
      active.bar = bar;
    }
  }

  document.addEventListener('click', function (e) {
    var field = e.target.closest ? e.target.closest('[data-edit-field]') : null;
    if (field && field.getAttribute('data-edit-type') !== 'image') {
      // ignore clicks that bubbled from the toolbar or an already-active editor
      if (active && (active.node === field || (active.bar && active.bar.contains(e.target)))) return;
      e.preventDefault();
      startEditing(field);
      return;
    }
    // click elsewhere ends the current edit (toolbar clicks handled above)
    if (active && !active.node.contains(e.target) && !(active.bar && active.bar.contains(e.target))) {
      finishEditing(true);
    }
  }, true);

  document.addEventListener('keydown', function (e) {
    if (!active) return;
    if (e.key === 'Escape') { e.preventDefault(); finishEditing(false); }
    else if (e.key === 'Enter' && active.type !== 'html' && !e.shiftKey) { e.preventDefault(); finishEditing(true); }
  });

  /* ================= O2 — item chips (delete / reorder) ================= */

  function itemContainer(item, entity) {
    // the list scope = nearest ancestor with a matching data-edit-add, else the parent
    var n = item.parentElement;
    while (n && n !== document.body) {
      var add = n.getAttribute && n.getAttribute('data-edit-add');
      if (add) {
        try { if (JSON.parse(add).entity === entity) return n; } catch (err) {}
      }
      n = n.parentElement;
    }
    return item.parentElement;
  }

  document.querySelectorAll('[data-edit-item]').forEach(function (item) {
    var ref = item.getAttribute('data-edit-item').split(':');
    var entity = ref[0], id = +ref[1];
    var flags = item.getAttribute('data-edit-flags') || '';
    var label = item.getAttribute('data-edit-label') || entity;

    var chip = el('span', 'sjov-chip');
    chip.appendChild(el('b', '', SJUI.esc(label)));
    if (flags.indexOf('o') !== -1) {
      var up = el('button', 'sjov-ico', '↑'); up.type = 'button'; up.title = 'Move up';
      var down = el('button', 'sjov-ico', '↓'); down.type = 'button'; down.title = 'Move down';
      function move(dir) {
        var scope = itemContainer(item, entity);
        var items = Array.prototype.slice.call(scope.querySelectorAll('[data-edit-item^="' + entity + ':"]'));
        var idx = items.indexOf(item), to = idx + dir;
        if (to < 0 || to >= items.length) return;
        var ids = items.map(function (n) { return +n.getAttribute('data-edit-item').split(':')[1]; });
        var t = ids[idx]; ids[idx] = ids[to]; ids[to] = t;
        api('order.php', { entity: entity, ids: ids })
          .then(function () { location.reload(); })
          .catch(function (err) { toast(err.message, true); });
      }
      up.addEventListener('click', function (e) { e.stopPropagation(); move(-1); });
      down.addEventListener('click', function (e) { e.stopPropagation(); move(1); });
      chip.appendChild(up); chip.appendChild(down);
    }
    if (flags.indexOf('d') !== -1) {
      var del = el('button', 'sjov-ico sjov-del', '🗑'); del.type = 'button'; del.title = 'Delete';
      del.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!confirm('Delete "' + label + '"? This cannot be undone.')) return;
        api('item.php', { action: 'delete', entity: entity, id: id })
          .then(function () { toast('Deleted'); location.reload(); })
          .catch(function (err) { toast(err.message, true); });
      });
      chip.appendChild(del);
    }
    item.classList.add('sjov-item');
    item.appendChild(chip);
  });

  /* ================= O2 — "+ Add" buttons on list containers ================= */

  document.querySelectorAll('[data-edit-add]').forEach(function (holder) {
    var meta;
    try { meta = JSON.parse(holder.getAttribute('data-edit-add')); } catch (err) { return; }
    var btn = el('button', 'sjov-add', '＋ ' + SJUI.esc(meta.label || 'Add'));
    btn.type = 'button';
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      SJUI.openForm({
        title: meta.label || 'Add',
        fields: meta.fields,
        submitLabel: '＋ Add',
        onSubmit: function (data) {
          return api('item.php', { action: 'create', entity: meta.entity, data: data, preset: meta.preset || {} })
            .then(function () { toast('Added ✔'); location.reload(); });
        }
      });
    });
    holder.classList.add('sjov-addhost');
    holder.appendChild(btn);
  });

  /* ================= O2 — image slots (click 📷 → picker) ================= */

  document.querySelectorAll('[data-edit-img]').forEach(function (node) {
    var ref = node.getAttribute('data-edit-img').split(':');
    var entity = ref[0], id = +ref[1], field = ref[2] || 'image_id', preset = ref[3] || null;
    var cam = el('button', 'sjov-cam', '📷'); cam.type = 'button'; cam.title = 'Change photo';
    cam.addEventListener('click', function (e) {
      e.stopPropagation(); e.preventDefault();
      SJUI.pickImage(preset).then(function (p) {
        api('field.php', { entity: entity, id: id, field: field, value: p.id })
          .then(function () { toast('Photo changed ✔'); location.reload(); })
          .catch(function (err) { toast(err.message, true); });
      }).catch(function () {});
    });
    // anchor the badge to the slot (the attribute sits on the <img>/<picture> or a wrapper)
    var host = node.tagName === 'IMG' || node.tagName === 'PICTURE' ? node.parentElement : node;
    if (host && getComputedStyle(host).position === 'static') host.classList.add('sjov-imghost');
    (host || document.body).appendChild(cam);
  });
})();
