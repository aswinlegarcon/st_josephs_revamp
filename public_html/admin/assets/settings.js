// Site Settings screen (C1) — collects the whitelisted inputs and saves them
// through the admin API front controller (?r=settings). External file: the
// admin CSP is script-src 'self', so no inline scripts.
(function () {
  'use strict';

  var form = document.getElementById('sj-settings');
  if (!form) return;

  var meta = document.querySelector('meta[name="sj-csrf"]');
  var CSRF = meta ? meta.content : '';
  var note = document.getElementById('sj-settings-note');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var values = {};
    form.querySelectorAll('input[name]').forEach(function (inp) {
      values[inp.name] = inp.value;
    });

    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    note.textContent = 'Saving…';

    fetch('/admin/api/index.php?r=settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ values: values })
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) throw new Error(j.error || 'Save failed');
      note.textContent = 'Saved — the site is updated.';
    }).catch(function (err) {
      note.textContent = 'Error: ' + err.message;
    }).then(function () {
      btn.disabled = false;
    });
  });

  // K11: diary PDF uploader — picks a file, POSTs it to ?r=diary, and the API
  // stores it AND updates the diary_url setting itself (no Save press needed);
  // we just mirror the new value into the text input so the form stays true.
  var dBtn  = document.getElementById('sj-diary-btn');
  var dFile = document.getElementById('sj-diary-file');
  if (dBtn && dFile) {
    dBtn.addEventListener('click', function () { dFile.click(); });
    dFile.addEventListener('change', function () {
      if (!dFile.files.length) return;
      var fd = new FormData();
      fd.append('file', dFile.files[0]);
      dBtn.disabled = true;
      note.textContent = 'Uploading diary…';
      fetch('/admin/api/index.php?r=diary', {
        method: 'POST',
        headers: { 'X-CSRF-Token': CSRF },
        body: fd
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Upload failed');
        var inp = form.querySelector('input[name="diary_url"]');
        if (inp) inp.value = j.url;
        note.textContent = 'Diary uploaded — the Download link on the About page is live.';
      }).catch(function (err) {
        note.textContent = 'Error: ' + err.message;
      }).then(function () {
        dBtn.disabled = false;
        dFile.value = '';
      });
    });
  }
})();
