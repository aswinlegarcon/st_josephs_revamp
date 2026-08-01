// Password strength meter + live rule checklist for admin/password.php.
// External (not inline) so the admin CSP can stay script-src 'self'.
(function () {
  'use strict';
  var input = document.getElementById('new');
  if (!input) return;
  var bars   = Array.prototype.slice.call(document.querySelectorAll('.meter span'));
  var checks = {
    len:  document.querySelector('.checks li[data-rule="len"]'),
    case: document.querySelector('.checks li[data-rule="case"]'),
    num:  document.querySelector('.checks li[data-rule="num"]')
  };
  var COLORS = ['#c62828', '#b26a00', '#b26a00', '#2e7d32']; // red, amber, amber, green

  function evaluate() {
    var v = input.value;
    var rules = {
      len:  v.length >= 12,
      case: /[a-z]/.test(v) && /[A-Z]/.test(v),
      num:  /[0-9]/.test(v)
    };
    var score = 0;
    Object.keys(rules).forEach(function (k) {
      if (checks[k]) checks[k].classList.toggle('ok', rules[k]);
      if (rules[k]) score++;
    });
    if (v.length >= 16 && rules.len && rules.case && rules.num) score = 4; // long + complete → full
    for (var i = 0; i < bars.length; i++) {
      bars[i].style.background = i < score ? COLORS[Math.min(score, 4) - 1] : '#e2e7f0';
    }
  }
  input.addEventListener('input', evaluate);
  evaluate();
})();
