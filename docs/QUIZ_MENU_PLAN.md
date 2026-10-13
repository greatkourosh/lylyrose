# Quiz menu — design plan

**Status:** built upstream in `aroma_store` at `891fa26c`, mirrored to `lylyrose-core`, both suites green (aroma_store 327/0, lylyrose 362/0). **Date:** 2026-10-12.

Upstream is the source of truth. The class is `ASC_Quiz_Menu` in `aroma-store-core` and is mirrored as `lylyrose-core/includes/class-quiz-menu.php`.

## Goal

One admin page that controls the menu items for the three finder pages:

- `/perfume-finder/` (عطرت رو پیدا کن)
- `/skin-needs/` (پوستت چه نیازی داره)
- `/hair-needs/` (موهات چه نیازی داره)

The owner edits label, URL, order and visibility there. The theme no longer hardcodes these links.

## Policy check

[FEATURE_REQUEST_POLICY.md](FEATURE_REQUEST_POLICY.md) says any new `ASC_` class in shared code routes upstream first. This menu is a new class in `lylyrose-core`, so it is shared code. The correct order is:

1. Build and test in `aroma_store` (`aroma-store-core`).
2. Run the upstream suite until green.
3. Mirror to `lylyrose` with the rename map.

If the owner wants it built here only, it must be stated explicitly as a store-specific exception. Do not mix it silently into shared code.

## Where it lives

| Piece | Location |
| --- | --- |
| Class | `includes/class-quiz-menu.php` → `ASC_Quiz_Menu` (new) |
| Bootstrap | `lylyrose-core.php`, next to the other `init()` calls |
| Admin page | Settings → «منوی کوییز» (`add_options_page`, slug `asc-quiz-menu`) |
| Storage | one option, `asc_quiz_menu` |
| Output | `header.php` (category nav, around lines 202–206) and the drawer nav (around lines 226–230) |

Why a settings page and not the WordPress menu editor: the theme's menu editor (`primary`) holds the whole site menu. This menu is three fixed quiz links with their own labels and visibility rules. A separate page keeps it apart and does not risk the main menu.

Why not the theme: a theme switch or update would drop the settings. The store already keeps its settings in plugins (`asc_finder_weights`, `asc_palette`), so this follows the same pattern.

## Data

Option `asc_quiz_menu`, one array:

```php
array(
    'items' => array(
        array( 'label' => 'عطرت رو پیدا کن', 'url' => '/perfume-finder/', 'enabled' => true,  'order' => 1 ),
        array( 'label' => 'پوستت چه نیازی داره', 'url' => '/skin-needs/',  'enabled' => true,  'order' => 2 ),
        array( 'label' => 'موهات چه نیازی داره', 'url' => '/hair-needs/',  'enabled' => true,  'order' => 3 ),
    ),
)
```

- Defaults are the three rows above. If the option is missing, the menu renders these, so nothing breaks on a fresh install.
- `url` is stored as a relative path and passed through `home_url()` on output. A full URL is also accepted.
- `sanitize_callback`: `sanitize_text_field` on labels, `esc_url_raw` on URLs, cast `enabled` to bool, cast `order` to int, drop rows with an empty label. Keep at most 10 items.

## Admin page

- Capability `manage_options`.
- A table of rows: label, URL, enabled checkbox, order. Add row and remove row. No drag-and-drop.
- Nonce via `settings_fields()` (`register_setting`), standard `options.php` save.
- Text domain `lylyrose-core`, as every file in the plugin uses.

## Frontend

`ASC_Quiz_Menu::items()` returns enabled items sorted by `order`, with `url` already resolved. `header.php` and the drawer loop over it instead of the hardcoded links.

The existing `class_exists( 'ASC_Perfume_Finder' )` and `class_exists( 'ASC_Needs_Quiz' )` guards stay. If the class is absent, no quiz links show.

The header's `get_page_by_path` fallback for `perfume-finder` is kept, so a deleted page still gives a working link.

## Not included

- Icons or emoji per item (the ⭐ on the perfume link stays hardcoded for now).
- Mega-menu grouping. These are top-level items only.
- Per-item visibility by user role or device.
- Translation of labels by the admin UI beyond the stored string.

## Verification

- Upstream: section in `aroma_store` `docker/run-tests.sh` covering defaults, save/sanitize, disabled items hidden, order respected, and that the header renders the stored labels.
- Downstream: same checks in `lylyrose` after mirror, run on `https://lylyrose.local`.
- Prove the tests can fail: change one sanitize rule and confirm the suite goes red.
- Deploy: header is PHP, so no `style.css` version bump. Purge LiteSpeed and Autoptimize after upload, because the header HTML is cached.

## Open questions

1. Should the admin page sit under Settings or as its own top-level menu item?
2. Should the header keep the ⭐ on the perfume link?
3. Build upstream first (policy), or treat this as a store-specific exception?
