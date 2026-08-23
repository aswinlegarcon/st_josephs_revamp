/* St.Joseph's standalone admin panel engine.
 * Everything posts to /admin/api/* (session + CSRF) and the public site simply
 * reads the database on its next request — no editing happens on the live page.
 */
(function () {
  'use strict';
  // Shared UI core (extracted in O2) — see admin/assets/sj-ui.js.
  var CSRF = SJUI.CSRF, el = SJUI.el, esc = SJUI.esc, toast = SJUI.toast,
      api = SJUI.api, presetMeta = SJUI.presetMeta,
      openModal = SJUI.openModal, initRichEditor = SJUI.initRichEditor,
      pickImage = SJUI.pickImage, openForm = SJUI.openForm;

  /* ---------------- list / row actions (event delegation) ---------------- */
  document.addEventListener('click', function (e) {
    var addBtn = e.target.closest('[data-panel-add]');
    if (addBtn) {
      var meta;
      try { meta = JSON.parse(addBtn.getAttribute('data-panel-add')); } catch (err) { return; }
      openForm({
        title: meta.label || 'Add',
        fields: meta.fields,
        submitLabel: 'Add',
        onSubmit: function (data) {
          return api('item.php', { action: 'create', entity: meta.entity, data: data, preset: meta.preset || {} })
            .then(function () { toast('Added'); location.reload(); });
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
        toast('Saved — live on the site');
        document.getElementById('sj-principal-msg-state').textContent = 'Saved';
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
              .then(function () { toast('Saved'); location.reload(); });
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

  /* ---------------- M2: drag-reorder for list rows ----------------
   * Every orderable list (.sj-list[data-list]) supports dragging rows in
   * addition to the ↑/↓ buttons. Drop → one order API call → reload.
   */
  (function initRowDrag() {
    var dragging = null;
    document.addEventListener('dragstart', function (e) {
      var row = e.target.closest ? e.target.closest('.sj-row[draggable="true"]') : null;
      if (!row) return;
      dragging = row;
      row.classList.add('sj-dragging');
      if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', ''); } catch (err) {} }
    });
    document.addEventListener('dragover', function (e) {
      if (!dragging) return;
      var over = e.target.closest ? e.target.closest('.sj-row[draggable="true"]') : null;
      if (!over || over === dragging || over.parentNode !== dragging.parentNode) return;
      e.preventDefault();
      var rect = over.getBoundingClientRect();
      // N8: grid-aware midpoint — when the dragged card and the target sit on
      // the same visual row (2-col preview grids), decide by X; otherwise by Y
      // exactly as before. Vertical lists never share a row-top, so their
      // behavior is unchanged.
      var sameRow = Math.abs(rect.top - dragging.getBoundingClientRect().top) < rect.height / 2;
      var before = sameRow
        ? e.clientX < rect.left + rect.width / 2
        : e.clientY < rect.top + rect.height / 2;
      over.parentNode.insertBefore(dragging, before ? over : over.nextSibling);
    });
    document.addEventListener('dragend', function () {
      if (!dragging) return;
      var row = dragging;
      dragging = null;
      row.classList.remove('sj-dragging');
      var list = row.closest('.sj-list[data-list]');
      if (!list) return;
      var entity = list.getAttribute('data-list');
      var ids = Array.prototype.slice.call(list.querySelectorAll('[data-row^="' + entity + ':"]'))
        .map(function (n) { return +n.getAttribute('data-row').split(':')[1]; });
      api('order.php', { entity: entity, ids: ids })
        .then(function () { toast('Order saved'); })
        .catch(function (err) { toast(err.message, true); location.reload(); });
    });
    // make rows of orderable lists draggable
    document.querySelectorAll('.sj-list[data-list] .sj-row').forEach(function (r) {
      r.setAttribute('draggable', 'true');
    });
  })();

  /* ---------------- M2: "Manage photos" modal (image_links collections) ----
   * Trigger: any element with data-panel-photos='{"owner_type","owner_id",
   * "role","preset","label"}'. Grid of linked photos with drag reorder,
   * add-from-library/upload (pickImage) and remove — all via the link API.
   */
  function openPhotos(cfg) {
    var M = openModal(cfg.label || 'Manage photos');
    var grid = el('div', 'sj-photogrid');
    M.body.appendChild(grid);
    var add = el('button', 'sj-btn sj-btn-primary', SJUI.icon('plus', 14) + ' Add photo'); add.type = 'button';
    var done = el('button', 'sj-btn sj-btn-ghost', 'Done'); done.type = 'button';
    M.foot.appendChild(add); M.foot.appendChild(done);
    done.addEventListener('click', function () { M.close(); if (cfg.onClose) cfg.onClose(); });

    var base = { owner_type: cfg.owner_type, owner_id: +cfg.owner_id, role: cfg.role || 'carousel' };

    function refresh() {
      api('link.php', Object.assign({ action: 'list' }, base)).then(function (j) {
        grid.innerHTML = '';
        j.links.forEach(function (l) {
          var t = el('div', 'sj-phototile');
          t.setAttribute('draggable', 'true');
          t.setAttribute('data-link', l.link_id);
          t.innerHTML = '<img src="' + esc(l.thumb || '') + '" alt="">';
          var rm = el('button', 'sj-photo-x', '×'); rm.type = 'button'; rm.title = 'Remove from this collection';
          rm.addEventListener('click', function () {
            api('link.php', Object.assign({ action: 'detach', link_id: l.link_id }, base))
              .then(function () { toast('Removed'); refresh(); })
              .catch(function (err) { toast(err.message, true); });
          });
          t.appendChild(rm);
          grid.appendChild(t);
        });
        if (!j.links.length) grid.appendChild(el('p', 'sj-hint', 'No photos yet — press “Add photo”.'));
      }).catch(function (err) { toast(err.message, true); });
    }

    add.addEventListener('click', function () {
      pickImage(cfg.preset || null).then(function (p) {
        api('link.php', Object.assign({ action: 'attach', image_id: p.id }, base))
          .then(function () { toast('Added'); refresh(); })
          .catch(function (err) { toast(err.message, true); });
      }).catch(function () {});
    });

    // tile drag reorder within the grid
    var dragTile = null;
    grid.addEventListener('dragstart', function (e) {
      var t = e.target.closest('.sj-phototile');
      if (!t) return;
      dragTile = t;
      t.classList.add('sj-dragging');
      if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', ''); } catch (err) {} }
    });
    grid.addEventListener('dragover', function (e) {
      if (!dragTile) return;
      var over = e.target.closest('.sj-phototile');
      if (!over || over === dragTile) return;
      e.preventDefault();
      var rect = over.getBoundingClientRect();
      var before = (e.clientX - rect.left) < rect.width / 2;
      grid.insertBefore(dragTile, before ? over : over.nextSibling);
    });
    grid.addEventListener('dragend', function () {
      if (!dragTile) return;
      dragTile.classList.remove('sj-dragging');
      dragTile = null;
      var ids = Array.prototype.slice.call(grid.querySelectorAll('.sj-phototile'))
        .map(function (n) { return +n.getAttribute('data-link'); });
      api('link.php', Object.assign({ action: 'reorder', link_ids: ids }, base))
        .then(function () { toast('Order saved'); })
        .catch(function (err) { toast(err.message, true); refresh(); });
    });

    refresh();
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-panel-photos]') : null;
    if (!btn) return;
    try {
      openPhotos(JSON.parse(btn.getAttribute('data-panel-photos')));
    } catch (err) { toast('Bad photos config', true); }
  });

  /* ---------------- media library page ---------------- */
  var mediaGrid = document.getElementById('sj-media-grid');
  if (mediaGrid) {
    var mSearch = document.getElementById('sj-media-search');
    var mMore = document.getElementById('sj-media-more');
    var mPage = 0, mQ = '';
    /* M3: re-crop an uploaded image — Cropper primed with the stored rect. */
    function openRecrop(it) {
      var meta = presetMeta(it.preset_key);
      if (!meta || meta.mode !== 'cover' || !window.Cropper) { toast('This image has no crop shape', true); return; }
      var M = openModal('Re-crop — ' + it.label);
      var img = document.createElement('img');
      img.className = 'sj-cropimg';
      img.src = it.orig;
      M.body.appendChild(img);
      var save = el('button', 'sj-btn sj-btn-primary', SJUI.icon('check', 14) + ' Save crop'); save.type = 'button';
      M.foot.appendChild(save);
      var initial;
      if (it.crop_rect) {
        var p = it.crop_rect.split(',').map(Number);
        initial = { x: p[0], y: p[1], width: p[2], height: p[3] };
      }
      var cr = new Cropper(img, {
        aspectRatio: meta.aspect_w / meta.aspect_h,
        viewMode: 1, autoCropArea: 1, movable: false, zoomable: false, rotatable: false, scalable: false,
        data: initial,
        ready: function () { if (initial) cr.setData(initial); }
      });
      save.addEventListener('click', function () {
        var d = cr.getData(true);
        save.disabled = true;
        api('recrop.php', { image_id: it.id, crop_rect: Math.max(0, d.x) + ',' + Math.max(0, d.y) + ',' + d.width + ',' + d.height })
          .then(function () {
            toast('Re-cropped — every page shows the new crop');
            cr.destroy(); M.close(); loadMedia(true);
          })
          .catch(function (err) { toast(err.message, true); save.disabled = false; });
      });
    }

    /* M4: detail modal — usage list, description edit, guarded delete. */
    function openImageDetail(it) {
      var M = openModal(it.label);
      var img = document.createElement('img');
      img.className = 'sj-cropimg';
      img.src = it.thumb;
      M.body.appendChild(img);
      var use = el('p', 'sj-hint', it.used ? ('Used in ' + it.used + ' place' + (it.used > 1 ? 's' : '') + ' — loading details…') : 'Not used anywhere.');
      M.body.appendChild(use);
      M.body.appendChild(el('label', '', 'Description (alt text)'));
      var alt = document.createElement('input');
      alt.type = 'text'; alt.value = it.alt || '';
      M.body.appendChild(alt);
      var save = el('button', 'sj-btn sj-btn-primary', SJUI.icon('check', 14) + ' Save description'); save.type = 'button';
      var del = el('button', 'sj-btn sj-danger', SJUI.icon('trash', 14) + ' Delete image'); del.type = 'button';
      del.disabled = it.used > 0;
      del.title = it.used ? 'Remove it from every place first' : 'Delete permanently';
      M.foot.appendChild(save); M.foot.appendChild(del);
      if (it.used) {
        api('image.php', { action: 'usage', image_id: it.id }).then(function (j) {
          var parts = [];
          Object.keys(j.usage).forEach(function (k) { parts.push(j.usage[k] + ' × ' + k); });
          use.textContent = 'Used in: ' + parts.join(', ');
        }).catch(function () {});
      }
      save.addEventListener('click', function () {
        api('image.php', { action: 'meta', image_id: it.id, alt_text: alt.value })
          .then(function () { toast('Description saved'); })
          .catch(function (err) { toast(err.message, true); });
      });
      del.addEventListener('click', function () {
        if (!confirm('Delete this image permanently? This cannot be undone.')) return;
        api('image.php', { action: 'delete', image_id: it.id })
          .then(function () { toast('Deleted'); M.close(); loadMedia(true); })
          .catch(function (err) { toast(err.message, true); });
      });
    }

    function loadMedia(reset) {
      if (reset) { mediaGrid.innerHTML = ''; mPage = 0; }
      var orphanBox = document.getElementById('sj-media-orphans');
      fetch('/admin/api/index.php?r=images&q=' + encodeURIComponent(mQ) + '&page=' + mPage
            + (orphanBox && orphanBox.checked ? '&filter=orphan' : ''))
        .then(function (r) { return r.json(); })
        .then(function (j) {
          j.items.forEach(function (it) {
            var d = el('div', 'sj-pick');
            d.innerHTML = '<img loading="lazy" src="' + esc(it.thumb) + '"><span>' + esc(it.label) + '</span>';
            var badge = el('em', 'sj-usedbadge', it.used ? (SJUI.icon('link', 11) + ' ' + it.used) : 'unused');
            if (!it.used) badge.className += ' free';
            d.appendChild(badge);
            d.addEventListener('click', function () { openImageDetail(it); });
            if (!it.legacy && it.preset_key) {
              var rc = el('button', 'sj-recrop', SJUI.icon('crop', 14)); rc.type = 'button'; rc.title = 'Re-crop';
              rc.addEventListener('click', function (e) { e.stopPropagation(); openRecrop(it); });
              d.appendChild(rc);
            }
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
    var mOrphans = document.getElementById('sj-media-orphans');
    if (mOrphans) mOrphans.addEventListener('change', function () { loadMedia(true); });
    document.getElementById('sj-media-upload').addEventListener('click', function () {
      pickImage(null, { presetChoice: true, startOnUpload: true, title: 'Upload image' })
        .then(function () { loadMedia(true); })
        .catch(function () {});
    });
    loadMedia(true);
  }

  /* ---------------- admin accounts (N7 — owners only) ---------------- */
  var adminsList = document.getElementById('sj-admins');
  if (adminsList) {
    function tempModal(username, temp) {
      var M = openModal('Temporary password — ' + username);
      M.body.appendChild(el('p', 'sj-hint',
        'Copy this now and hand it over securely. It is shown ONCE and must be changed at first sign-in.'));
      var box = el('div', '', '<input type="text" readonly value="' + esc(temp) + '" style="font-family:monospace">');
      M.body.appendChild(box);
      var copy = el('button', 'sj-btn sj-btn-primary', SJUI.icon('copy', 14) + ' Copy');
      copy.type = 'button';
      copy.addEventListener('click', function () {
        box.querySelector('input').select();
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(temp);
        } else {
          document.execCommand('copy');
        }
        toast('Copied to clipboard');
      });
      M.foot.appendChild(copy);
    }

    function renderAdmins() {
      api('admins.php', { action: 'list' }).then(function (j) {
        adminsList.innerHTML = '';
        j.items.forEach(function (a) {
          var row = el('div', 'sj-row');
          var chips = '<span class="sj-badge' + (a.role === 'owner' ? ' gold' : '') + '">' + esc(a.role) + '</span>';
          if (a.locked) chips += ' <span class="sj-badge red">Locked</span>';
          if (a.pending) chips += ' <span class="sj-badge">Awaiting first sign-in</span>';
          row.innerHTML =
            '<span class="sj-avatar">' + esc((a.display_name || '?').charAt(0).toUpperCase()) + '</span>' +
            '<div class="sj-row-main"><b>' + esc(a.display_name) + (a.is_me ? ' (you)' : '') + '</b>' +
            '<span>@' + esc(a.username) + ' · last sign-in: ' + esc(a.last_login || 'never') + '</span></div>' +
            chips + '<div class="sj-row-actions"></div>';
          var acts = row.querySelector('.sj-row-actions');
          function ico(name, title, danger, fn) {
            var b = el('button', 'sj-ico' + (danger ? ' danger' : ''), SJUI.icon(name, 16));
            b.type = 'button'; b.title = title; b.setAttribute('aria-label', title);
            b.addEventListener('click', fn);
            acts.appendChild(b);
          }
          if (a.locked) {
            ico('unlock', 'Unlock account', false, function () {
              api('admins.php', { action: 'unlock', id: a.id })
                .then(function () { toast('Unlocked'); renderAdmins(); })
                .catch(function (e2) { toast(e2.message, true); });
            });
          }
          ico('key', 'Reset password (new temporary)', false, function () {
            if (!confirm('Generate a new temporary password for @' + a.username + '?')) return;
            api('admins.php', { action: 'reset', id: a.id })
              .then(function (r) { tempModal(a.username, r.temp_password); renderAdmins(); })
              .catch(function (e2) { toast(e2.message, true); });
          });
          if (!a.is_me) {
            ico('shield', a.role === 'owner' ? 'Make editor' : 'Make owner', false, function () {
              var to = a.role === 'owner' ? 'editor' : 'owner';
              if (!confirm('Change @' + a.username + ' to ' + to + '?')) return;
              api('admins.php', { action: 'role', id: a.id, role: to })
                .then(function () { toast('Role updated'); renderAdmins(); })
                .catch(function (e2) { toast(e2.message, true); });
            });
            ico('trash', 'Delete account', true, function () {
              if (!confirm('Delete @' + a.username + ' permanently?')) return;
              api('admins.php', { action: 'delete', id: a.id })
                .then(function () { toast('Deleted'); renderAdmins(); })
                .catch(function (e2) { toast(e2.message, true); });
            });
          }
          adminsList.appendChild(row);
        });
      }).catch(function (e2) {
        adminsList.innerHTML = '';
        adminsList.appendChild(el('p', 'sj-hint', esc(e2.message)));
      });
    }

    document.getElementById('sj-admin-new').addEventListener('click', function () {
      openForm({
        title: 'New admin account',
        fields: [
          { name: 'username', label: 'Username (lowercase, 3–30)', type: 'text', required: true },
          { name: 'display_name', label: 'Display name', type: 'text', required: true },
          { name: 'role', label: 'Account role — owner: full control · editor: content only', type: 'enum', options: ['editor', 'owner'], required: true }
        ],
        values: { role: 'editor' },
        submitLabel: 'Create account',
        onSubmit: function (data) {
          return api('admins.php', {
            action: 'create', username: data.username, display_name: data.display_name, role: data.role
          }).then(function (r) { tempModal(data.username, r.temp_password); renderAdmins(); });
        }
      });
    });

    renderAdmins();
  }
})();
