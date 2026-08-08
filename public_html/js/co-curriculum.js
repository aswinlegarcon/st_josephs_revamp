// Co-curriculum page: card-blur + numbered reveals (shipped threshold 100),
// now through /js/site.js (R2). Included from the page body, before site.js
// loads — hence the DOMContentLoaded wrapper (as the original had).
window.addEventListener('DOMContentLoaded', function () {
    sjBlurCards('.co-curriculum-card .col-md-4');
    sjReveal('.co-curriculum-card-reveal2,.co-curriculum-card-reveal3,.co-curriculum-card-reveal4,.co-curriculum-card-reveal5', 100);
});
