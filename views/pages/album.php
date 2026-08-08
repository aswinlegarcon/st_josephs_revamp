<?php
// Gallery-album template — the 10 gal-* pages (C9). Variables from the thin
// controllers: $sj_slug, $sj_album (row + 'years', each year carrying 'photos').
// The album's verbatim shipped CSS loads from /css/albums/{slug}.css (the
// controller passes it via $styles); container class stays gal-{name}-container.
//
// C9 fixes baked in: year-toggle labels' for= now always match their input ids
// (bug 7 — labels are clickable); the unused duplicate image-N ids are gone
// (bug 11); the lightbox navigates ONLY the visible year's photos and supports
// ← / → / Esc with typing/modifier/edit-mode guards (FEATURES_PLAN §3–4).
$sj_container = str_replace('gal-', 'gal-', $sj_slug) . '-container';
?>
<div class="album-container <?= e($sj_container) ?>">
    <h1<?= ed_field('gallery_album', $sj_album['id'], 'heading') ?>><?= e($sj_album['heading']) ?></h1>
    <div class="btn-group" role="group" aria-label="Basic radio toggle button group"<?= ed_add('album_year', ['album_id' => (int)$sj_album['id']], 'Add year') ?>>
        <?php foreach ($sj_album['years'] as $yi => $Y): ?>
        <input type="radio" class="btn-check" name="btnradio" id="btnradio<?= e($Y['year_label']) ?>" autocomplete="off"<?= $yi === 0 ? ' checked' : '' ?>>
        <label class="btn btn-outline-primary" for="btnradio<?= e($Y['year_label']) ?>"><?= e($Y['year_label']) ?></label>
        <?php endforeach; ?>
    </div>
    <div class="image-container">
        <?php foreach ($sj_album['years'] as $Y): ?>
        <?php foreach ($Y['photos'] as $ph): ?>
        <div class="image year-<?= e($Y['year_label']) ?>"><?= img_tag($ph, 'gallery_full', ['alt' => '']) ?></div>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <div class="popup-image">
        <span>&times;</span>
        <img src="<?= e(img_url($sj_album['years'][0]['photos'][0] ?? null, 'gallery_full')) ?>" alt="">
        <div class="navigation-buttons">
            <button class="prev">&lt;</button>
            <button class="next">&gt;</button>
        </div>
    </div>
</div>

<script>
const container = document.querySelector('.<?= e($sj_container) ?>');
let activeImages = [];   // photos of the SELECTED year only (C9)
let currentImageIndex = 0;

function collectActive() {
    activeImages = Array.prototype.slice.call(
        container.querySelectorAll('.image-container .image')
    ).filter(function (div) { return div.style.display !== 'none'; })
     .map(function (div) { return div.querySelector('img'); });
}

container.querySelectorAll('.image-container img').forEach(function (image) {
    image.onclick = function () {
        collectActive();
        const popupImage = document.querySelector('.popup-image');
        popupImage.style.display = 'flex';
        document.querySelector('.popup-image img').src = image.getAttribute('src');
        currentImageIndex = activeImages.indexOf(image);
        setTimeout(function () {
            popupImage.classList.add('visible');
        }, 10);
    };
});

function closePopup() {
    const popupImage = document.querySelector('.popup-image');
    popupImage.classList.remove('visible');
    setTimeout(function () {
        popupImage.style.display = 'none';
    }, 500);
}
document.querySelector('.popup-image span').onclick = closePopup;

const updatePopupImage = function () {
    document.querySelector('.popup-image img').src = activeImages[currentImageIndex].getAttribute('src');
};
function popupPrev() {
    currentImageIndex = (currentImageIndex - 1 + activeImages.length) % activeImages.length;
    updatePopupImage();
}
function popupNext() {
    currentImageIndex = (currentImageIndex + 1) % activeImages.length;
    updatePopupImage();
}
document.querySelector('.prev').onclick = popupPrev;
document.querySelector('.next').onclick = popupNext;

// Keyboard: ← / → navigate the open lightbox (active year only), Esc closes.
// Guards per FEATURES_PLAN §4: ignore while typing, with modifiers, in edit mode.
document.addEventListener('keydown', function (e) {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'Escape') return;
    if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
    var t = e.target;
    if (t && t.closest && t.closest('input, textarea, select, [contenteditable]')) return;
    if (document.body.classList.contains('sj-edit-mode')) return;
    var popup = document.querySelector('.popup-image');
    if (!popup || popup.style.display !== 'flex') return;
    if (e.key === 'Escape') { closePopup(); }
    else if (e.key === 'ArrowLeft') { popupPrev(); }
    else { popupNext(); }
});

let startX;
document.querySelector('.popup-image img').addEventListener('touchstart', function (e) {
    startX = e.touches[0].clientX;
});
document.querySelector('.popup-image img').addEventListener('touchmove', function (e) {
    if (!startX) return;
    const touch = e.touches[0];
    const change = startX - touch.clientX;
    if (change > 50) {
        popupNext();
        startX = null;
    } else if (change < -50) {
        popupPrev();
        startX = null;
    }
});

// Show/hide images based on the selected year
const radioButtons = container.querySelectorAll('input[name="btnradio"]');
const imageContainers = container.querySelectorAll('.image');

radioButtons.forEach(function (radio) {
    radio.addEventListener('change', function () {
        const selectedYear = radio.id.replace('btnradio', '');
        imageContainers.forEach(function (containerDiv) {
            if (containerDiv.classList.contains('year-' + selectedYear)) {
                containerDiv.style.display = 'block';
            } else {
                containerDiv.style.display = 'none';
            }
        });
    });
});

// Trigger change event to display initial set of images
document.querySelector('input[name="btnradio"]:checked').dispatchEvent(new Event('change'));
</script>
