<?php

namespace SJ\View;

use SJ\Admin\Auth;
use SJ\Content\Registry;

/**
 * data-edit-* attribute emitters (grammar per DYNAMIC_MIGRATION_PLAN.md §5.3).
 * Every method returns '' for public visitors, so public output is byte-identical
 * to a static render. Ported verbatim from _libs/edit.php in R2; the global
 * names (ed_field(), ed_item(), ed_add(), ed_rich(), ed_img()) delegate here.
 */
final class EditAttrs
{
    /** Editable single field on a text element. */
    public static function field(string $entity, $id, string $field): string
    {
        if (!Auth::isEdit()) {
            return '';
        }
        $def = Registry::entity($entity)['fields'][$field] ?? null;
        if ($def === null) {
            return '';
        }
        $attrs = \sprintf(' data-edit-field="%s:%d:%s" data-edit-type="%s"', e($entity), (int)$id, e($field), e($def['type']));
        if ($def['type'] === 'enum') {
            $attrs .= ' data-edit-options="' . e(\implode(',', $def['values'])) . '"';
        }
        return $attrs;
    }

    /** Deletable/reorderable repeating item (slide, card, row…). */
    public static function item(string $entity, $id, string $label = ''): string
    {
        if (!Auth::isEdit()) {
            return '';
        }
        $reg = Registry::entity($entity) ?? [];
        $flags = (!empty($reg['orderable']) ? 'o' : '') . (!empty($reg['deletable']) ? 'd' : '');
        $a = \sprintf(' data-edit-item="%s:%d" data-edit-flags="%s"', e($entity), (int)$id, $flags);
        if ($label !== '') {
            $a .= ' data-edit-label="' . e($label) . '"';
        }
        return $a;
    }

    /** "+ Add" affordance on a list container. $preset = column values fixed at creation. */
    public static function add(string $entity, array $preset = [], string $label = 'Add'): string
    {
        if (!Auth::isEdit()) {
            return '';
        }
        $reg = Registry::entity($entity);
        if ($reg === null || empty($reg['creatable'])) {
            return '';
        }
        $fields = [];
        foreach ($reg['fields'] as $name => $def) {
            $fields[] = [
                'name'     => $name,
                'label'    => $def['label'] ?? \ucfirst(\str_replace('_', ' ', $name)),
                'type'     => $def['type'],
                'options'  => $def['values'] ?? null,
                'preset'   => $def['preset'] ?? null,
                'required' => !empty($def['required']),
                'multiline' => !empty($def['multiline']),
            ];
        }
        $payload = ['entity' => $entity, 'preset' => $preset, 'label' => $label, 'fields' => $fields];
        return " data-edit-add='" . \str_replace("'", '&#39;', \json_encode($payload, \JSON_UNESCAPED_SLASHES)) . "'";
    }

    /** Echo a raw *_html value; in edit mode it gets a layout-neutral editable wrapper. */
    public static function rich(string $entity, $id, string $field, ?string $html): void
    {
        if (Auth::isEdit()) {
            echo '<div style="display:contents"' . self::field($entity, $id, $field) . '>' . $html . '</div>';
        } else {
            echo (string)$html;
        }
    }

    /** Image slot (entity image field). Camera overlay → upload/pick modal. */
    public static function img(string $entity, $id, string $field = 'image_id'): string
    {
        if (!Auth::isEdit()) {
            return '';
        }
        $def = Registry::entity($entity)['fields'][$field] ?? null;
        if ($def === null || $def['type'] !== 'image') {
            return '';
        }
        return \sprintf(' data-edit-img="%s:%d:%s:%s"', e($entity), (int)$id, e($field), e($def['preset'] ?? ''));
    }
}
