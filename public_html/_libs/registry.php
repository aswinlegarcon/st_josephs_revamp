<?php
// THE entity registry — the single authority the admin API uses to resolve tables,
// columns, and types. Request payloads can never name a table or column directly
// (DYNAMIC_MIGRATION_PLAN.md §5.4). Home-page entities only, for the demo.
//
// Field types: text | html | url | int | enum | bool | image
//  - image fields carry the preset used for uploads/auto-crop (FEATURES_PLAN.md §2.2)
//  - 'parent' names the FK column a new row must receive via the creation preset.

function sj_registry(): array
{
    static $r = null;
    if ($r !== null) {
        return $r;
    }
    $r = [
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
    return $r;
}

function sj_registry_entity(string $entity): ?array
{
    return sj_registry()[$entity] ?? null;
}
