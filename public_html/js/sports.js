// Sports page: card-blur + numbered reveals (shipped threshold 100), now
// through /js/site.js (R2). Included from the page body, before site.js
// loads — hence the DOMContentLoaded wrapper (as the original had).
window.addEventListener('DOMContentLoaded', function () {
    sjBlurCards('.sports-card .col-md-4');
    sjReveal('.sports-card-reveal2,.sports-card-reveal3,.sports-card-reveal4,.sports-card-reveal5', 100);
});
