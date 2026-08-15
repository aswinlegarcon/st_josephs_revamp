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
})();
