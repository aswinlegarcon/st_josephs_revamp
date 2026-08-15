# The Registry Pattern — One Map That Drives the Whole CMS

> **What you'll learn:** why our admin panel refuses to let the browser name a database table, how
> one PHP array decides what can be edited, created, reordered and deleted, and how that same array
> *generates* the admin forms so nobody hand-writes them.
>
> **Prerequisites:** basic PHP (`if`, `foreach`, functions, arrays) and the idea that a website
> talks to a database. No OOP, patterns or security knowledge needed — every term is explained.
>
> **Where it lives:** `src/Content/Registry.php` (the map), `public_html/admin/api/_bootstrap.php`
> (validation), `field.php` / `item.php` / `order.php` (write endpoints),
> `public_html/admin/_layout.php` + `section.php` (panel UI), `src/View/EditAttrs.php` (overlay).

---

## 1. The one-paragraph version

Our content lives in MySQL and the admin panel has to edit it. The dangerous way to build that is
to let the browser say "update table `X`, column `Y`, to value `Z`". We do the opposite. One PHP
array — the **registry** — lists everything an editor may touch. Each entry has a safe nickname
(an **entity name** like `hero_slide`), the real table behind it, three yes/no **capabilities**
(orderable / creatable / deletable), an optional **parent** foreign key, and a list of **fields**
with a **type** each. The browser may only send an entity name and a field name; anything not in
the array is rejected. Everything else — the SQL, the validation, the shape of the Add form — is
*derived* from that array. A **pattern** is just a shape of code people re-use because it keeps
working; this one means *keep one central, code-authored list of what is allowed, and resolve
every untrusted name through it.*

---

## 2. The problem this solves

### 2.1 The naive design

The simplest possible admin API takes JSON and writes it:

```php
// THE NAIVE VERSION — we do NOT do this. Never write this.
$in  = json_decode(file_get_contents('php://input'), true);
$sql = "UPDATE {$in['table']} SET {$in['column']} = ? WHERE id = ?";
db()->prepare($sql)->execute([$in['value'], $in['id']]);
```

It works on day one: the panel sends `{"table":"hero_slides","column":"caption_title", …}` and the
caption changes.

### 2.2 Why it is a catastrophe

The browser is **untrusted** — not because our editor is malicious, but because *anything* in that
tab can send that request. `SECURITY.md:10` states the rule: "everything in a request (JSON body,
headers, `$_FILES`, query strings, cookies) is untrusted".

So one bug anywhere — an **XSS** (attacker JavaScript running inside our page), a **CSRF** (a
request fired by another site while the admin is logged in), a rogue extension — can send this:

```json
{ "table": "admin_users", "column": "password_hash", "id": 1, "value": "$2y$10$…attacker-hash…" }
```

That one request hands over the admin account. Swap in `"table": "settings"` and site-wide
configuration is rewritten. The naive endpoint has no idea anything is special; it is a remote
control for the whole database.

A quieter second disaster: values travel safely as **placeholders** (the `?` marks — the driver
keeps them as data, never as commands), but table and column names **cannot** be placeholders, so
they must be text inside the query. That makes `{$in['column']}` an **SQL injection** hole — send
`` x`, `password_hash` = 'y' -- `` and you are writing SQL, not data.

Two catastrophes, one root cause: **letting the request name the identifier** (a table or column
name). Hence the repo law at `CLAUDE.md:62` — "values only via PDO **placeholders**; table/column
**identifiers only from the registry** … Never build SQL from request data."

### 2.3 The other problem: keeping the UI in sync

We have 19 editable content types. Hand-written forms would mean one new field touches the table,
the API, the Add form, the Edit form and the on-page editor — five chances to forget one. Describe
the field **once** and every form builds itself.

---

## 3. How it works in general

| Part | Plain English | In our code |
|---|---|---|
| **The map** | A code-authored list of what exists and what may be done to it | `Registry::all()` — `src/Content/Registry.php:19` |
| **The lookup** | Untrusted input is *translated* through the map, never used directly | `Registry::entity()` — `src/Content/Registry.php:214` |
| **The derivations** | SQL, validation and UI are generated from the map | the files in §4 |

The key idea is **indirection**. The browser never says table `hero_slides`; it says entity
`hero_slide`. To a human those look similar; to the code one is *a name the attacker chose* and
the other is *a key that must exist in an array we wrote by hand*. The lookup is the airlock
(`src/Content/Registry.php:214-217`):

```php
public static function entity(string $entity): ?array
{
    return self::all()[$entity] ?? null;
}
```

`?? null` means "if the key isn't there, give me `null`", and every caller treats `null` as
"reject". `admin_users`, `mysql.user`, `` hero_slides`; DROP TABLE `` — none are keys, so all take
the same exit door. The map caches itself in a `private static ?array $map`
(`src/Content/Registry.php:17-24`): `all()` returns the cached copy or builds it once. Twenty
callers cost one build and **zero SQL queries** — which matters against our budget of ≤ 12 per
page (`CLAUDE.md`, "Performance budget").

---

## 4. How we use it — every place in this codebase

### 4.1 The shape of one entry

From `src/Content/Registry.php:32-43`:

```php
'hero_slide' => [
    'table' => 'hero_slides', 'orderable' => true, 'creatable' => true, 'deletable' => true,
    'parent' => 'page_id',
    'fields' => [
        'caption_title' => ['type' => 'text', 'max' => 120, 'label' => 'Caption title'],
        …
        'image_id'      => ['type' => 'image', 'preset' => 'hero_16x7', 'label' => 'Slide image', 'required' => true],
        'is_active'     => ['type' => 'bool', 'label' => 'Visible on site'],
    ],
],
```

- **`'hero_slide'`** — the entity name; the only word the browser may use.
- **`table`** — the real MySQL table, read from here and never from the request.
- **`orderable` / `creatable` / `deletable`** — may rows be reordered / added / removed?
- **`parent`** — the **foreign key** column (a column holding the id of a row in another table) a
  new row must receive. A hero slide belongs to a page, so it needs a `page_id`.
- **`fields`** — the editable columns. Anything *not* listed (`id`, `position`, `created_at`) is
  invisible to the API. That is deliberate.

Per-field keys: `type` (§4.3), `max` (length or numeric ceiling), `min`, `label` (what the editor
reads), `values` (options for `enum`), `preset` (image crop recipe), `required`, `nullable`,
`multiline` (textarea instead of a one-line box).

### 4.2 Every entity in the registry

From `src/Content/Registry.php:25-211`. **O**=orderable, **C**=creatable, **D**=deletable.

| Entity | Table | O | C | D | Parent | Fields (type) |
|---|---|:-:|:-:|:-:|---|---|
| `page` | `pages` | – | – | – | – | `heading_html` (html) |
| `hero_slide` | `hero_slides` | ✔ | ✔ | ✔ | `page_id` | `caption_title`, `caption_text` (text); `button_label` (text, nullable); `button_url` (url, nullable); `image_id` (image `hero_16x7`, required); `is_active` (bool) |
| `profile` | `profiles` | – | – | – | – | `heading`, `person_name` (text); `message_html` (html); `image_id` (image `portrait_4x5`) |
| `unique_feature` | `unique_features` | ✔ | ✔ | ✔ | – | `title` (text); `body_html` (html); `image_id` (image `feature_4x3`); `is_active` (bool) |
| `ticker_item` | `ticker_items` | ✔ | ✔ | ✔ | – | `label` (text); `url` (url); `is_active` (bool) |
| `update_slide` | `update_slides` | ✔ | ✔ | ✔ | – | `title`, `subtitle`, `link_label` (text); `link_url` (url, nullable); `image_id` (image `update_16x9`, required); `is_active` (bool) |
| `school_section` | `school_sections` | ✔ | – | – | – | `name`, `intro_heading`, `timeline_heading`, `events_heading`, `card_title`, `card_range` (text); `intro_html` (html); `card_image_id` (image `card_4x3`) |
| `timeline_entry` | `timeline_entries` | ✔ | ✔ | ✔ | `section_id` | `month_label`, `time_label` (text); `events_text` (text, multiline) |
| `section_event` | `section_events` | ✔ | ✔ | ✔ | `section_id` | `title` (text); `body_html` (html); `image_id` (image `feature_4x3`, nullable) |
| `academy` | `academies` | ✔ | ✔ | ✔ | – | `slug` (slug, required, **create-only**); `banner_title`, `banner_subtitle`, `content_heading`, `card_title`, `card_subtitle` (text); `body_html` (html); `card_image_id` (image `card_4x3`, required); `bg_image_id` (image `bg_wide`, required); `is_active` (bool) |
| `sport` | `sports` | ✔ | ✔ | ✔ | – | `name`, `training_time` (text); `details_html` (html); `image_id` (image `card_4x3`, required); `is_active` (bool) |
| `facility` | `facilities` | ✔ | ✔ | ✔ | – | `slug` (text, required), `name` (text); `description_html` (html); `bg_image_id` (image `bg_wide`, required); `is_active` (bool) |
| `achievement` | `achievements` | ✔ | ✔ | ✔ | – | `type` (enum `achievement`/`award`); `title`, `subtext` (text); `image_id` (image `feature_4x3`, required); `is_active` (bool) |
| `gallery_album` | `gallery_albums` | ✔ | ✔ | ✔ | – | `slug` (slug, required, **create-only**); `title`, `heading`, `card_sub` (text); `card_image_id` (image `card_4x3`, required); `is_active` (bool) |
| `album_year` | `album_years` | ✔ | ✔ | ✔ | `album_id` | `year_label` (text); `is_active` (bool) |
| `testimonial` | `testimonials` | ✔ | ✔ | ✔ | – | `name_html`, `body_html` (html); `bg_image_id` (image `feature_4x3`, nullable — N2); `is_active` (bool) |
| `seo_meta` | `seo_meta` | – | – | – | – | `title` (text); `description` (text, multiline) |
| `mark_year` | `mark_years` | – | ✔ | ✔ | – | `year` (int 2000–2100); `is_active` (bool) |
| `mark_entry` | `mark_entries` | ✔ | ✔ | ✔ | `year_id` | `standard` (enum `10`/`11`/`12`); `rank_label`, `student_name` (text); `marks_scored`, `marks_total` (int) |

The flags encode *policy*. `academy` and `gallery_album` were `creatable => false` for a long
time because a new academy needed its own URL stub — a developer task. **N3/N4 solved the URL
problem** (a generic `/academy.php?slug=…` controller + a `create_only` slug field type +
`Registry::legacySlugs()` protecting the 18+10 shipped slugs from delete/rename), so both are
now fully creatable from the panel. `seo_meta` is edit-only because its rows track the URL set
(auto-inserted/removed when academies/albums are created/deleted).

### 4.3 The field types, and exactly what each triggers on save

Every write goes through one function, `api_validate_field()`
(`public_html/admin/api/_bootstrap.php:57`). It takes the field's registry definition plus the raw
value and returns a **normalised** value ready for the database — or kills the request. First an
empty-value gate: `required` fields fail, `nullable` fields return `null`, empty `text`/`html`/`url`
become `''`, anything else fails (`:60-71`). Then the type switch, verbatim with repeated
max-length checks elided (`:72-118`):

```php
        case 'text':
            $v = trim(strip_tags((string)$value));
            …
        case 'html':
            $v = sj_sanitize_html((string)$value);
            …
        case 'url':
            if (preg_match('/^(javascript|data|vbscript):/i', $v) || preg_match('/[\x00-\x1f]/', $v)) {
                api_fail("Field '$field' is not a valid link");
            }
            …
        case 'int':
            if ((isset($def['min']) && $v < $def['min']) || (isset($def['max']) && $v > $def['max'])) {
                api_fail("Field '$field' out of range");
            }
            …
        case 'enum':
            if (!in_array($v, $def['values'], true)) {
                api_fail("Field '$field' has an invalid value");
            }
            …
        case 'bool':
            return !empty($value) && $value !== '0' ? 1 : 0;
        case 'image':
            if ($v <= 0 || !repo_image($v)) {
                api_fail("Field '$field': image not found");
            }
            …
    }
    api_fail("Unknown field type for '$field'");
```

| Type | What it triggers on save | Lines |
|---|---|---|
| `text` | `strip_tags()` (all markup removed) + `trim()` + `max` as a character count | 73-78 |
| `html` | `sj_sanitize_html()` — the **only** door HTML may enter the DB — then `max` | 79-84 |
| `url` | blocks `javascript:`/`data:`/`vbscript:` and control characters, then `max` | 85-93 |
| `int` | must be numeric (`is_numeric`), cast to `int`, must sit inside `min`…`max` | 94-102 |
| `enum` | must be one of `values`, compared strictly (so `"0"` never passes as `0`) | 103-108 |
| `bool` | squashed to literal `1` or `0`, never anything else | 109-110 |
| `image` | value is an image **id**; the row must exist (`repo_image()`) | 111-116 |

`sj_sanitize_html()` keeps a frozen tag whitelist (`b, strong, i, em, br, p, span.hl-gold` —
`CLAUDE.md:64`) and discards the rest, which is why `SECURITY.md:25` requires **any** new rich
field to be typed `html`. An unknown type is a hard failure, not a silent pass, so a registry typo
cannot open a hole.

### 4.4 How the API uses the registry

Every admin call enters through one hardcoded route map (`public_html/admin/api/index.php:7-17`),
and every endpoint requires `_bootstrap.php` first, which checks the session (`:22`), the
forced-password-change gate (`:27`) and the **CSRF token** on every non-GET request (`:32-37`).

**`field.php` — save one field.** The whole file is 29 lines; this is the load-bearing part
(`public_html/admin/api/field.php:10-17`):

```php
$reg = api_entity($entity);
$def = $reg['fields'][$field] ?? null;
if ($def === null || $id <= 0) {
    api_fail('Unknown field');
}
$value = api_validate_field($entity, $field, $def, $in['value'] ?? null);

$st = db()->prepare("UPDATE {$reg['table']} SET `$field` = ? WHERE id = ?");
```

Look at that SQL. `{$reg['table']}` came from the registry; `` `$field` `` had to be a key inside
`$reg['fields']`; the value is a `?` placeholder. **Nothing in the query came from the request.**
`SECURITY.md:20` gives the test: POST ``{"entity":"hero_slide","field":"id`; --"}`` must answer
"Unknown field" — it does, because that string is not a key. `api_entity()` does the same for the
entity name via `sj_registry_entity()` (`src/helpers.php:93`), failing with `Unknown entity`
(`_bootstrap.php:122-129`).

**`item.php` — get / create / update / delete a row.** `create` refuses unless the flag is on
(`:46-48`), `delete` mirrors it with `deletable` (`:110-113`), and the INSERT column list is built
**only** from the registry, never from the payload (`:54-60`):

```php
        foreach ($reg['fields'] as $name => $def) {
            $provided = array_key_exists($name, $data) ? $data[$name] : null;
            …
            $cols[$name] = api_validate_field($entity, $name, $def, $provided);
        }
```

That kills **mass assignment** (adding extra keys to a payload hoping they land in the database):
send `{"data":{"password_hash":"x"}}` and it is never read, because the loop walks *our* field
list, not *their* key list. The parent FK comes from a separate `preset` bag and is required when
the entity declares one (`:62-68`); `position` is computed server-side as `MAX(position)+1`,
scoped to the parent (`:70-78`); `update` rejects unknown keys loudly (`:97-100`); `get` returns
values **and** field metadata rebuilt from the registry (`:24-42`) for the Edit modal.

**`order.php` — reorder rows.** All ten lines of logic are registry-gated (`:10-16`):
`if (empty($reg['orderable']) || !is_array($ids) || !$ids) { api_fail('Entity is not orderable'); }`
then `UPDATE \`{$reg['table']}\` SET position = ? WHERE id = ?` in a loop, inside a
**transaction** (`:14-24`) so a half-finished reorder is impossible.

### 4.5 How the admin UI is *generated* from the registry

Nobody hand-writes the Add/Edit forms. `panel_add_attr()` turns a registry entry into JSON and
parks it on the button (`public_html/admin/_layout.php:45-65`):

```php
    $reg = sj_registry_entity($entity);
    if ($reg === null || empty($reg['creatable'])) {
        return '';
    }
    $fields = [];
    foreach ($reg['fields'] as $name => $def) {
        $fields[] = [
            'name'     => $name,
            'label'    => $def['label'] ?? ucfirst(str_replace('_', ' ', $name)),
            'type'     => $def['type'],
            'options'  => $def['values'] ?? null,
            'preset'   => $def['preset'] ?? null,
            …
        ];
    }
    $payload = ['entity' => $entity, 'preset' => $preset, 'label' => $label, 'fields' => $fields];
    return " data-panel-add='" . str_replace("'", '&#39;', json_encode($payload, JSON_UNESCAPED_SLASHES)) . "'";
```

A non-creatable entity returns `''`, so the button renders powerless. `$preset` is where the parent
id is injected **by the page**, not the browser —
`panel_add_attr('hero_slide', ['page_id' => (int)$page['id']], 'Add hero slide')`
(`public_html/admin/section.php:56`), or `['year_id' => (int)$y['id']]` for a topper (`:193`).
Lists render through a shared `panel_row()` helper (`:14-42`) that stamps `data-row="entity:id"`.

The JavaScript parses that JSON and builds the form (`admin/assets/panel.js:15-21`), and
`control()` in `admin/assets/sj-ui.js:269` switches on `f.type`: `bool` → checkbox (`:272`),
`html` → rich-text editor (`:284`), `enum` → a `<select>` from `f.options` (`:288`), `image` →
thumbnail plus picker for `f.preset` (`:298`), `multiline` → `<textarea>` (`:316`), otherwise an
`<input>` typed `number` for `int` and `text` for the rest (`:324`). **Add a field to the registry
and this form grows a control by itself.**

### 4.6 The live-edit overlay gets its attributes the same way

The public site is editable in place when an admin turns on edit mode (`Auth::isEdit()` —
`src/Admin/Auth.php:81`). The attributes come from `src/View/EditAttrs.php`, each registry-checked:
`field()` looks up `Registry::entity($entity)['fields'][$field] ?? null`, returns `''` if that is
`null`, then emits `data-edit-field="entity:id:field"` plus `data-edit-type` and, for `enum`,
`data-edit-options` from `$def['values']` (`:22-30`).

| Emitter | Marks | Registry check |
|---|---|---|
| `ed_field()` | one editable text/rich value | field must exist; its `type` goes to the JS |
| `ed_item()` | a repeating row | `orderable`/`deletable` become the flags `"o"`/`"d"` (`:40`) |
| `ed_add()` | the "+ Add" affordance on a list container | `''` unless `creatable` (`:54-56`); ships the same field JSON |
| `ed_img()` | an image slot | field must exist **and** be `type => 'image'` (`:90-93`) |
| `ed_rich()` | echoes a `*_html` value, wrapped only in edit mode | delegates to `field()` |

Every method returns `''` for a public visitor, so a normal page is byte-identical to a static
render (`:10-11`). Real usage at `views/partials/carousel.php:22`:
`<h5<?= ed_field('hero_slide', $s['id'], 'caption_title') ?>><?= e($s['caption_title']) ?></h5>` —
note `e()`: every dynamic value is HTML-escaped on output, except `_html` columns sanitized on the
way in.

### 4.7 `ownerTypes()` — a second, smaller registry for images

Photo collections live in `image_links`, one **polymorphic** table (one table, several kinds of
owner). Turning an owner *type* into an owner *table* needs its own whitelist:
`Registry::ownerTypes()` (`src/Content/Registry.php:225-235`) maps `page => pages`,
`section => school_sections`, `academy => academies`, `facility => facilities`,
`album => gallery_albums`, `album_year => album_years`. `link.php` uses it exactly as `field.php`
uses the main map — `if (!isset($owners[$type]) || $oid <= 0) { api_fail('Unknown owner'); }`, then
`$ownerTable = $owners[$type];` before any SQL (`public_html/admin/api/link.php:16-26`). The panel
will not even draw the button for a non-whitelisted owner (`_layout.php:70-72`).

### 4.8 What is deliberately **not** in the registry

`CLAUDE.md:66` is blunt: "any new editable field/entity must be registered; `admin_users` and
`settings` are **never** registered." `admin_users` holds password hashes; `settings` holds
site-wide config. Register either and the generic `field.php` becomes a write path into them —
exactly the §2.2 catastrophe. `SECURITY.md:180` repeats it as pre-ship checklist item 9.

So how *are* settings edited? A dedicated endpoint with its own, much narrower whitelist — key →
`[max length, validator]` (`public_html/admin/api/settings.php:9-36`):

```php
/** key => [max length, validator] — the ONLY editable settings. */
$SETTING_KEYS = [
    'contact_email'         => [160, 'email'],
    …
];
…
    if (!isset($SETTING_KEYS[$key])) {
        api_fail('Unknown setting');
    }
```

Same pattern, different map, deliberately separate — its header says this way "the generic
field/item APIs can never touch arbitrary settings rows" (`:4-6`). Its UI is the one hand-built
form in the panel (`section.php:674-722`), on purpose.

---

## 5. Why this is the right approach here

| Alternative | What it gives | Why not here |
|---|---|---|
| **An ORM with model classes** (a `HeroSlide` class per table) | Type safety, autocomplete, relationships | We have **no framework** (`CLAUDE.md`, "Stack") and deploy to MilesWeb shared hosting by file upload. An ORM still wouldn't describe labels, crop presets, or *which* fields the editor may see — we'd bolt a registry-shaped layer on top anyway. |
| **One config file per entity** | Smaller files, fewer merge conflicts | 19 files to answer "what is editable?", plus filesystem reads per request. Worse, a config *directory* invites loading a file whose name came from the request — the LFI risk of `SECURITY.md` SEC-11. One array with keyed lookups has no filename to attack. |
| **A full CMS (WordPress, …)** | Everything, free | Wrong shape. The design is frozen and must stay pixel-identical to production (`CLAUDE.md`, visual-freeze rule); a theme layer fights that daily, and plugins add an update/attack surface on shared hosting with one non-technical editor and no SSH. |

The constraints that decide it: **no framework and upload-only deploys** (a PHP array works when
copied); **a security catalogue that forbids request-derived identifiers**, which `SECURITY.md:19`
makes an *invariant* and a registry is the smallest thing satisfying it; **one non-technical
editor**, so the panel must show every field she needs and none she can break; **no UI/DB drift**,
because a field is either registered (and appears everywhere) or not (and appears nowhere), with
no half-state where the form exists but the API rejects it; and **zero query cost**.

---

## 6. How this scales

Imagine 10× growth — 190 entities.

**What stays fast forever.** Every lookup is `self::all()[$entity]`, a PHP array-key hit: **O(1)**,
constant time whether the array holds 19 keys or 19,000. Endpoints never loop over entities; they
jump straight to one. The array is built once per request (`Registry.php:17-24`), so the cost stays
microseconds and **zero SQL queries**.

**What actually degrades: the human.** `Registry.php` is 236 lines today; at 10× it is ~2,000 lines
in one file — merge conflicts when two people add entities, and endless scrolling. `SECURITY.md:67`
anticipates it: "keep the registry as sole authority as it grows to ~30 entities".

**The exact next step, when it hurts.** Split per domain, keeping the public API identical:

```
src/Content/Registry.php          ← all() merges the parts; entity()/ownerTypes() unchanged
src/Content/Registry/Home.php     ← hero_slide, unique_feature, ticker_item, update_slide, testimonial
src/Content/Registry/Academic.php ← school_section, timeline_entry, section_event, mark_year, mark_entry
src/Content/Registry/Media.php    ← gallery_album, album_year
```

`all()` becomes `array_merge(Home::map(), Academic::map(), …)` and caches as before; nothing
outside the class changes, because every caller already goes through `Registry::entity()` or
`sj_registry_entity()`. What **not** to do is move the registry into the database or into
JSON/YAML files: that turns a hand-written, code-reviewed, git-diffable security boundary into
runtime data, where a stray write could grant edit access to `admin_users`. Per-entity permissions
(when roles arrive) belong as extra registry flags checked in `_bootstrap.php` — what
`SECURITY.md:67` recommends.

---

## 7. Gotchas and mistakes to avoid

**1. Forgetting to register a new field — the panel silently won't save it.** You add `subtitle` to
the table, render it, and… nothing. `panel_add_attr()` loops `$reg['fields']` (`_layout.php:52`),
so no control is built; `ed_field()` returns `''` when `$def === null` (`EditAttrs.php:22-25`), so
the overlay never marks it editable; and a forced request gets `Unknown field` (`field.php:12-14`).
Three silent no-ops and one terse error.

**2. Registering a sensitive table.** Adding `admin_users` or `settings` hands the generic
`field.php` a write path to password hashes or config — §2.2 by hand. It breaks `CLAUDE.md:66` and
fails `SECURITY.md:180`. Such tables get their own endpoint and whitelist, like `settings.php`.

**3. `max` that doesn't match the column.** The registry's `max` is checked in PHP
(`_bootstrap.php:75`); the column has its own limit in MySQL. `caption_title` is `VARCHAR(120)`
(`database/schema.sql:135`) and the registry says `'max' => 120` (`Registry.php:36`) — they agree
on purpose. Set 255 against a `VARCHAR(120)` and PHP waves the value through, then MySQL truncates
or rejects it; the editor gets mangled text or an opaque failure instead of the friendly "too long"
message. **Copy the number from the DDL.** (Registry `max` *lower* than the column is safe.)

**4. `image` presets that don't exist.** `preset` names a row in `image_presets`; the shipped keys
are `hero_16x7`, `gallery_tile`, `gallery_full`, `card_4x3`, `content_slide`, `portrait_4x5`,
`feature_4x3`, `update_16x9`, `bg_wide` (`database/schema.sql:366-375`). Nothing validates the
spelling on save — the `image` branch only checks the id exists (`_bootstrap.php:111-116`). A typo
surfaces later as a broken thumbnail (`sj-ui.js:306` passes `f.preset` to the picker) and a wrong
crop on the page.

**5. `parent` missing on a creatable child entity.** If rows belong to a parent, declare `parent`.
Without it `item.php:62` skips the FK block and the INSERT omits the column — MySQL rejects it,
because the foreign key is `NOT NULL` (e.g. `database/schema.sql:133`), so "Add" dies messily. With
`parent` declared you get the tidy `api_fail('Missing parent reference')` (`:64-66`). Pass the id
from the page: `panel_add_attr('timeline_entry', ['section_id' => (int)$S['id']], …)`
(`section.php:400`).

**6. Assuming `orderable` is free.** It makes `order.php:16` write a `position` column and
`item.php:70-78` compute the next position. No `position` column, no `orderable` — `mark_year`
leaves the flag off for exactly that reason (`Registry.php:194`).

---

## 8. Try it yourself

### 8.1 Watch the generated form change

Run `./run.sh` (it builds, starts and seeds, then prints the URLs). Open
<http://localhost:8090/admin/> and log in (`admin` / `admin123` on a fresh seed — the first login
forces a password change; that gate is `_bootstrap.php:27` and `_layout.php:13`).

1. Click **📣 News Ticker**, then **＋ Add announcement**. The form has exactly three controls —
   *Text*, *Link*, *Visible on site* — the three fields of `ticker_item` (`Registry.php:62-69`).
2. With the **Write/Edit tool** (never shell redirection — see `CLAUDE.md`'s file-writing rule),
   add a temporary field to that entity in `src/Content/Registry.php`:
   `'note' => ['type' => 'text', 'max' => 80, 'label' => 'Internal note', 'nullable' => true],`
3. Reload the panel and click **＋ Add announcement** again. There is now an *Internal note* box,
   and you wrote no HTML or JavaScript — that is `panel_add_attr()` → `sj-ui.js::control()`.
4. Try to **save**: it fails, because `ticker_items` has no `note` column. The registry describes,
   the schema stores, and both must change together (§8.3). Now **undo step 2** — this was a demo.

### 8.2 Call the API by hand and watch the guards fire

```bash
# No session, no token → the auth guard answers first (_bootstrap.php:22)
curl -i -X POST 'http://localhost:8090/admin/api/index.php?r=field' \
  -H 'Content-Type: application/json' \
  -d '{"entity":"ticker_item","id":1,"field":"label","value":"hello"}'
# → HTTP/1.1 401   {"ok":false,"error":"Not authenticated"}
```

Routes come only from the hardcoded map in `public_html/admin/api/index.php`, so `?r=field` reaches
`field.php` and nothing else can be named. With a logged-in session cookie but **no**
`X-CSRF-Token` header you get `403 {"ok":false,"error":"Invalid CSRF token"}` instead
(`_bootstrap.php:32-37`). The panel takes its token from `<meta name="sj-csrf">` (`_layout.php:88`),
reads it at `sj-ui.js:9` and sends it on every call (`:33`). No mutating admin call is possible
without **both** a session and a matching token — by design, so use the browser panel for real
edits. The interesting probe is the injection test from `SECURITY.md:20` — logged in, with a token:

```bash
curl -X POST 'http://localhost:8090/admin/api/index.php?r=field' \
  -H 'Content-Type: application/json' -H "X-CSRF-Token: $TOKEN" -b "$COOKIEJAR" \
  -d '{"entity":"hero_slide","id":1,"field":"id`; --","value":"x"}'
# → {"ok":false,"error":"Unknown field"}
```

`{"entity":"admin_users", …}` answers `Unknown entity`. Neither string is a key in the map, so
neither ever reaches SQL.

### 8.3 Recipe A — add a new editable field to an existing entity

1. **Schema:** add the column to that table's `CREATE TABLE` in `database/schema.sql`, with a sane
   `NOT NULL DEFAULT ''` so existing rows stay valid.
2. **Migration:** add `database/migrations/00N_short_name.sql` with an **additive-only**
   `ALTER TABLE … ADD COLUMN …`. Never destructive DDL (`CLAUDE.md`, deploy cautions).
3. **Registry:** add the field to that entity's `fields`, matching `max` to the column length.
   Rich text → `type => 'html'` **and** a `*_html` column name.
4. **Read path:** `SELECT *` repository methods (e.g. `Repo::ticker()`, `src/Content/Repo.php:297-301`)
   pick it up free; column-listing ones need it added.
5. **Render:** `<?= e($row['new_field']) ?>` plus `<?= ed_field('entity', $row['id'], 'new_field') ?>`
   on the element; for `*_html` use `ed_rich()` instead of `e()`.
6. **Panel:** nothing to do — Add and Edit modals pick it up from the registry.
7. **Seed:** if it needs content on a fresh install, add it in `database/seed.php`, keeping the
   seeder **idempotent** (running it twice changes nothing).
8. **Verify:** `./run.sh`, then create → edit → reorder → delete in the panel; confirm the page
   still renders pixel-identically; walk `SECURITY.md` §4.

### 8.4 Recipe B — make a whole new entity editable

1. **Table:** `CREATE TABLE IF NOT EXISTS …` in `database/schema.sql` with `id`, your columns,
   `position` (only if reorderable), `is_active`, timestamps and the parent foreign key — copy the
   shape of `hero_slides` (`database/schema.sql:131-146`).
2. **Migration:** the same `CREATE TABLE IF NOT EXISTS` in a new numbered file under
   `database/migrations/` (`007.sql` is a good model).
3. **Registry entry:** `table`, the three capability flags, `parent` if it has one, and the typed
   `fields` list. Start conservative — leave `creatable`/`deletable` off if the row set is fixed.
4. **Repository:** add a `public static function` to `src/Content/Repo.php` reading the rows (join
   `images` in the same query if there is an image — no N+1 in loops), plus a thin global wrapper
   in `src/helpers.php` beside `repo_ticker()` (`:313`).
5. **Panel screen:** add a slug to `panel_sections()` (`public_html/admin/_layout.php:19-42`) and a
   `case` in `section.php` rendering `panel_row()` per row plus a `panel_add_attr()` button.
6. **Public view:** render in `views/`, escaping with `e()` (or `ed_rich()` for `*_html`), and add
   `ed_add()` / `ed_item()` / `ed_field()` / `ed_img()` for live editing.
7. **Images?** If it owns a *collection* of photos rather than one image, add its owner type to
   `Registry::ownerTypes()` (`Registry.php:225`) and use `panel_photos_attr()`.
8. **Verify** per `SECURITY.md:68`: create with an extra field `{"data":{"password_hash":"x"}}` →
   must answer `Unknown field`; create with `parent_id: 999999` → clean failure, no 500; confirm
   `admin_users`/`settings` are still unregistered. Then affirm `SECURITY.md` §4.

---

## 9. Where to read more

- [`../05-stage-e.md`](../05-stage-e.md) — Stage E: content moved into the database and the panel
  learned to edit it. Where the registry was born.
- [`../06-stage-f.md`](../06-stage-f.md) — Stage F: the live-edit overlay, i.e. `EditAttrs`
  consuming the registry on the public site.
- [`../02-security.md`](../02-security.md) — beginner-level explanations of SQL injection, XSS,
  CSRF and mass assignment, in plain words.
- [`../../../SECURITY.md`](../../../SECURITY.md) — the normative catalog: **SEC-01** (SQL injection),
  **SEC-02** (stored XSS / the `html` type), **SEC-09** (IDOR & mass assignment via the registry)
  and the **§4** checklist, items 1, 4 and 9.
- [`../../../CLAUDE.md`](../../../CLAUDE.md) — the repo laws: identifiers from the registry only (line
  62), rich fields typed `html` (64), `admin_users`/`settings` never registered (66).
- [`../../../DYNAMIC_MIGRATION_PLAN.md`](../../../DYNAMIC_MIGRATION_PLAN.md) — legacy reference; §5.4 is
  the original registry design note and §5.3 the `data-edit-*` grammar (both cited in the source
  files' own doc-comments).
- OWASP, *Mass Assignment Cheat Sheet* —
  <https://cheatsheetseries.owasp.org/cheatsheets/Mass_Assignment_Cheat_Sheet.html> — its
  "allow-list the properties you accept" advice is exactly what `item.php:54-60` does.
- OWASP, *SQL Injection Prevention Cheat Sheet* — the section on allow-list validation for table
  and column names, i.e. why identifiers can never be placeholders:
  <https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html>
