<?php
require __DIR__ . '/bootstrap.php';

// N4: the hub's cross-fade slider comes from image_links (owner = the
// 'gallery' pages row, role 'slider'), seeded from the 15 shipped photos —
// so the default output is byte-identical, and the set is editable in the
// panel. The shipped list stays as a fallback for a pre-seed database.
$sj_gallery_page = repo_page('gallery');
$sliderRows = $sj_gallery_page ? repo_linked_images('page', (int)$sj_gallery_page['id'], 'slider') : [];
$sj_slider_urls = $sliderRows
    ? array_map(static fn ($im) => img_url($im, 'bg_wide'), $sliderRows)
    : array_map(static fn ($f) => '/photos/' . $f, [
        'sportsday1.jpg', 'sportsday10.jpg', 'indday1.jpg', 'indday12.jpg',
        'childday1.jpg', 'childday4.jpg', 'teachday1.jpg', 'teachday9.jpg',
        'expressday1.jpg', 'expressday13.jpg', 'expo1.jpg', 'expo18.jpg',
        'gradday1.jpg', 'gradday11.jpg', 'spach1.jpg',
    ]);

\SJ\View\Layout::render('gallery', [
    'sj_albums'      => repo_albums(is_edit()),
    'sj_slider_urls' => $sj_slider_urls,
    'title'          => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'      => 'gallery',
    'styles'         => ['gallery'],
    'showPreloader'  => false,
]);
