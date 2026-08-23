<?php

namespace SJ\Content;

/**
 * THE entity registry — the single authority the admin API uses to resolve tables,
 * columns and types (DYNAMIC_MIGRATION_PLAN.md §5.4, SECURITY.md SEC-01/09).
 * Request payloads can never name a table or column directly. admin_users and
 * settings are NEVER registered.
 *
 * Field types: text | html | url | int | enum | bool | image
 *  - image fields carry the upload/auto-crop preset (FEATURES_PLAN.md §2.2)
 *  - 'parent' names the FK column a new row must receive via the creation preset.
 */
final class Registry
{
    private static ?array $map = null;

    public static function all(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        return self::$map = [
            'page' => [
                'table' => 'pages', 'orderable' => false, 'creatable' => false, 'deletable' => false,
                'fields' => [
                    'heading_html' => ['type' => 'html', 'max' => 255, 'label' => 'Heading'],
                ],
            ],
            'hero_slide' => [
                'table' => 'hero_slides', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'parent' => 'page_id',
                'fields' => [
                    'caption_title' => ['type' => 'text', 'max' => 120, 'label' => 'Caption title'],
                    'caption_text'  => ['type' => 'text', 'max' => 255, 'label' => 'Caption text'],
                    'button_label'  => ['type' => 'text', 'max' => 40,  'label' => 'Button label', 'nullable' => true],
                    'button_url'    => ['type' => 'url',  'max' => 255, 'label' => 'Button link',  'nullable' => true],
                    'image_id'      => ['type' => 'image', 'preset' => 'hero_16x7', 'label' => 'Slide image', 'required' => true],
                    'is_active'     => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            'profile' => [
                'table' => 'profiles', 'orderable' => false, 'creatable' => false, 'deletable' => false,
                'fields' => [
                    'heading'      => ['type' => 'text', 'max' => 60,  'label' => 'Role heading'],
                    'person_name'  => ['type' => 'text', 'max' => 120, 'label' => 'Name'],
                    'message_html' => ['type' => 'html', 'max' => 65000, 'label' => 'Message'],
                    'image_id'     => ['type' => 'image', 'preset' => 'portrait_4x5', 'label' => 'Portrait'],
                ],
            ],
            'unique_feature' => [
                'table' => 'unique_features', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'title'     => ['type' => 'text', 'max' => 150, 'label' => 'Title'],
                    'body_html' => ['type' => 'html', 'max' => 65000, 'label' => 'Content'],
                    'image_id'  => ['type' => 'image', 'preset' => 'feature_4x3', 'label' => 'Image'],
                    'is_active' => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            'ticker_item' => [
                'table' => 'ticker_items', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'label'     => ['type' => 'text', 'max' => 200, 'label' => 'Text'],
                    'url'       => ['type' => 'url',  'max' => 255, 'label' => 'Link'],
                    'is_active' => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            'update_slide' => [
                'table' => 'update_slides', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'title'      => ['type' => 'text', 'max' => 120, 'label' => 'Title'],
                    'subtitle'   => ['type' => 'text', 'max' => 200, 'label' => 'Subtitle'],
                    'link_url'   => ['type' => 'url',  'max' => 255, 'label' => 'Video link', 'nullable' => true],
                    'link_label' => ['type' => 'text', 'max' => 40,  'label' => 'Button label'],
                    'image_id'   => ['type' => 'image', 'preset' => 'update_16x9', 'label' => 'Slide image', 'required' => true],
                    'is_active'  => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            'school_section' => [
                'table' => 'school_sections', 'orderable' => true, 'creatable' => false, 'deletable' => false,
                'fields' => [
                    'name'             => ['type' => 'text', 'max' => 80,  'label' => 'Section name'],
                    'intro_heading'    => ['type' => 'text', 'max' => 80,  'label' => 'Intro heading'],
                    'intro_html'       => ['type' => 'html', 'max' => 65000, 'label' => 'Intro text'],
                    'timeline_heading' => ['type' => 'text', 'max' => 60,  'label' => 'Timeline label'],
                    'events_heading'   => ['type' => 'text', 'max' => 60,  'label' => 'Events heading prefix'],
                    'card_title'       => ['type' => 'text', 'max' => 80,  'label' => 'Grade-card title (Academics page)'],
                    'card_range'       => ['type' => 'text', 'max' => 40,  'label' => 'Grade-card range'],
                    'card_image_id'    => ['type' => 'image', 'preset' => 'card_4x3', 'label' => 'Grade-card image'],
                ],
            ],
            'timeline_entry' => [
                'table' => 'timeline_entries', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'parent' => 'section_id',
                'fields' => [
                    'month_label' => ['type' => 'text', 'max' => 20,   'label' => 'Month'],
                    'time_label'  => ['type' => 'text', 'max' => 30,   'label' => 'Time tag'],
                    'events_text' => ['type' => 'text', 'max' => 5000, 'label' => 'Events (one per line)', 'multiline' => true],
                ],
            ],
            'section_event' => [
                'table' => 'section_events', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'parent' => 'section_id',
                'fields' => [
                    'title'     => ['type' => 'text', 'max' => 120,   'label' => 'Event title'],
                    'body_html' => ['type' => 'html', 'max' => 65000, 'label' => 'Description'],
                    'image_id'  => ['type' => 'image', 'preset' => 'feature_4x3', 'label' => 'Photo', 'nullable' => true],
                ],
            ],
            'academy' => [
                // N3: creatable — new academies serve at /academy.php?slug=…
                // (generic controller); the 18 shipped slugs keep their own
                // files and are delete/rename-protected via legacySlugs().
                'table' => 'academies', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'slug'            => ['type' => 'slug', 'max' => 40,  'label' => 'URL key (lowercase, permanent — e.g. roboticsacademy)', 'required' => true, 'create_only' => true],
                    'banner_title'    => ['type' => 'text', 'max' => 100, 'label' => 'Banner title'],
                    'banner_subtitle' => ['type' => 'text', 'max' => 120, 'label' => 'Banner subtitle'],
                    'content_heading' => ['type' => 'text', 'max' => 100, 'label' => 'Content heading'],
                    'body_html'       => ['type' => 'html', 'max' => 65000, 'label' => 'Write-up'],
                    'card_title'      => ['type' => 'text', 'max' => 100, 'label' => 'Co-curriculum card title'],
                    'card_subtitle'   => ['type' => 'text', 'max' => 120, 'label' => 'Co-curriculum card subtitle'],
                    'card_image_id'   => ['type' => 'image', 'preset' => 'card_4x3',  'label' => 'Card photo', 'required' => true],
                    'bg_image_id'     => ['type' => 'image', 'preset' => 'bg_wide',   'label' => 'Page background photo', 'required' => true],
                    'is_active'       => ['type' => 'bool', 'label' => 'Visible on the co-curriculum grid'],
                ],
            ],
            'sport' => [
                'table' => 'sports', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'name'          => ['type' => 'text', 'max' => 80,   'label' => 'Sport name'],
                    'training_time' => ['type' => 'text', 'max' => 80,   'label' => 'Training-time line'],
                    'details_html'  => ['type' => 'html', 'max' => 65000, 'label' => '"Read More" text'],
                    'image_id'      => ['type' => 'image', 'preset' => 'card_4x3', 'label' => 'Card photo', 'required' => true],
                    'is_active'     => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            'facility' => [
                'table' => 'facilities', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'slug'             => ['type' => 'text', 'max' => 40,   'label' => 'URL key (letters/dashes, unique)', 'required' => true],
                    'name'             => ['type' => 'text', 'max' => 80,   'label' => 'Facility name'],
                    'description_html' => ['type' => 'html', 'max' => 65000, 'label' => 'Description'],
                    'bg_image_id'      => ['type' => 'image', 'preset' => 'bg_wide', 'label' => 'Background photo', 'required' => true],
                    'is_active'        => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            'achievement' => [
                'table' => 'achievements', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'type'      => ['type' => 'enum', 'values' => ['achievement', 'award'], 'label' => 'List'],
                    'title'     => ['type' => 'text', 'max' => 150, 'label' => 'Title'],
                    'subtext'   => ['type' => 'text', 'max' => 255, 'label' => 'Sub-line'],
                    'image_id'  => ['type' => 'image', 'preset' => 'feature_4x3', 'label' => 'Photo', 'required' => true],
                    'is_active' => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            'gallery_album' => [
                // N4: creatable — new albums serve at /album.php?slug=… (generic
                // controller); the 10 shipped gal-* slugs keep their own files
                // and are delete/rename-protected via legacySlugs().
                'table' => 'gallery_albums', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'slug'          => ['type' => 'slug', 'max' => 40,  'label' => 'URL key (lowercase, permanent — e.g. gal-farewell)', 'required' => true, 'create_only' => true],
                    'title'         => ['type' => 'text', 'max' => 100, 'label' => 'Hub-card title'],
                    'heading'       => ['type' => 'text', 'max' => 100, 'label' => 'Album-page heading'],
                    'card_sub'      => ['type' => 'text', 'max' => 60,  'label' => 'Hub-card sub-line (years)'],
                    'card_image_id' => ['type' => 'image', 'preset' => 'card_4x3', 'label' => 'Hub-card photo', 'required' => true],
                    'is_active'     => ['type' => 'bool', 'label' => 'Visible on the gallery hub'],
                ],
            ],
            'album_year' => [
                'table' => 'album_years', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'parent' => 'album_id',
                'fields' => [
                    'year_label' => ['type' => 'text', 'max' => 20, 'label' => 'Year label (e.g. 2025)'],
                    'is_active'  => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            // K4: the About rules block's "School timings" table rows.
            'rules_timing' => [
                'table' => 'rules_timings', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'timing'   => ['type' => 'text', 'max' => 60,  'label' => 'Timing (e.g. 8.30 AM to 12.00 Noon)'],
                    'activity' => ['type' => 'text', 'max' => 120, 'label' => 'Activity (e.g. - Instructional Hours)'],
                ],
            ],
            'testimonial' => [
                'table' => 'testimonials', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                // K2 (owner decision): the home layout fits exactly three cards —
                // both Add buttons hide at the cap and item.php refuses a 4th.
                'max_count' => 3,
                'fields' => [
                    'name_html'   => ['type' => 'html', 'max' => 200,   'label' => 'Student name & tag'],
                    'body_html'   => ['type' => 'html', 'max' => 65000, 'label' => 'Testimonial'],
                    // N2: optional per-card background; empty = the shipped
                    // card1/2/3 static images keep cycling (pixel-frozen default).
                    'bg_image_id' => ['type' => 'image', 'preset' => 'feature_4x3', 'label' => 'Card background photo', 'nullable' => true],
                    'is_active'   => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            // F3: per-URL search snippet. The row set is fixed (one per public
            // URL, seeded) — only the two text fields are editable.
            'seo_meta' => [
                'table' => 'seo_meta', 'orderable' => false, 'creatable' => false, 'deletable' => false,
                'fields' => [
                    'title'       => ['type' => 'text', 'max' => 160, 'label' => 'Browser/search title'],
                    'description' => ['type' => 'text', 'max' => 300, 'label' => 'Meta description', 'multiline' => true],
                ],
            ],
            'mark_year' => [
                'table' => 'mark_years', 'orderable' => false, 'creatable' => true, 'deletable' => true,
                'fields' => [
                    'year'      => ['type' => 'int', 'min' => 2000, 'max' => 2100, 'label' => 'Year'],
                    'is_active' => ['type' => 'bool', 'label' => 'Visible on site'],
                ],
            ],
            'mark_entry' => [
                'table' => 'mark_entries', 'orderable' => true, 'creatable' => true, 'deletable' => true,
                'parent' => 'year_id',
                'fields' => [
                    'standard'     => ['type' => 'enum', 'values' => ['10', '11', '12'], 'label' => 'Standard'],
                    'rank_label'   => ['type' => 'text', 'max' => 8,   'label' => 'Rank (I/II/III)'],
                    'student_name' => ['type' => 'text', 'max' => 120, 'label' => 'Student name'],
                    'marks_scored' => ['type' => 'int', 'min' => 0, 'max' => 1000, 'label' => 'Marks scored'],
                    'marks_total'  => ['type' => 'int', 'min' => 1, 'max' => 1000, 'label' => 'Marks total'],
                ],
            ],
        ];
    }

    public static function entity(string $entity): ?array
    {
        return self::all()[$entity] ?? null;
    }

    /**
     * image_links owner whitelist (M1): owner_type => owning table.
     * The link API resolves the owner table ONLY through this map — request
     * payloads can never name a table (SEC-01/09/11). Values match
     * DYNAMIC_MIGRATION_PLAN §4.1 and the C4–C9 seeds.
     */
    public static function ownerTypes(): array
    {
        return [
            'page'       => 'pages',
            'section'    => 'school_sections',
            'academy'    => 'academies',
            'facility'   => 'facilities',
            'album'      => 'gallery_albums',
            'album_year' => 'album_years',
        ];
    }

    /**
     * N3/N4: the slugs whose pages shipped as REAL .php files. They must keep
     * resolving forever ("every legacy URL keeps working"), so their rows can
     * never be deleted (item.php guard) and their slugs never change
     * (create_only). Everything NOT in these lists serves via the generic
     * /academy.php and /album.php controllers — see academy_url()/album_url().
     */
    public static function legacySlugs(): array
    {
        return [
            'academy' => [
                'abacusacademy', 'artacademy', 'artandexpo', 'band',
                'communicativeacademy', 'danceacademy', 'englishacademy',
                'instrumentacademy', 'langacademy', 'martialacademy',
                'mathsacademy', 'ncc', 'scienceacademy', 'socialacademy',
                'sportsacademy', 'tamilacademy', 'vocalacademy', 'yogaacademy',
            ],
            'gallery_album' => [
                'gal-alumni', 'gal-annual', 'gal-children', 'gal-expo',
                'gal-expressionz', 'gal-grad', 'gal-independence', 'gal-spach',
                'gal-sports', 'gal-teacher',
            ],
        ];
    }
}
