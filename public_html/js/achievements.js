// Achievements page: card-blur + the numbered reveal classes, shipped
// parameters, now through /js/site.js (R2). This file is included from the
// page body, before site.js loads — hence the DOMContentLoaded wrapper
// (the original registered its handlers on DOMContentLoaded too).
window.addEventListener('DOMContentLoaded', function () {
    sjBlurCards('.achieve-card .col-md-6');
    sjReveal('.achieve-card-reveal2,.achieve-card-reveal3,.achieve-card-reveal4,.achieve-card-reveal5', 150);
});
