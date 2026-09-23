# CLAUDE.md — WP Portfolio (astra-portfolio)

**Plugin:** WP Portfolio · **Slug:** `astra-portfolio` · **Version:** 1.13.2 · **Author:** Brainstorm Force
**CPT:** `astra-portfolio` · **Taxonomy:** `astra-portfolio-tag` · **Shortcode:** `[astra-portfolio]` / `[wp_portfolio]` · **Block:** `astra-portfolio/wp-portfolio`

> UI says "WP Portfolio" but all code identifiers use `astra-` prefix — never rename slugs, hooks, or option keys.

---

## Commands

```bash
composer install && npm install   # setup
npm run build                     # compile src/ → dist/ (required after any src/ change)
npm run start                     # watch mode
composer run lint                 # PHPCS
composer run phpstan              # static analysis
npm run package                   # create distribution ZIP
```

---

## Structure

```
astra-portfolio.php                          ← bootstrap (constants + includes)
classes/
  class-astra-portfolio.php                  ← singleton orchestrator
  class-astra-portfolio-admin.php            ← CPT, taxonomy, meta box
  class-astra-portfolio-page.php             ← settings pages, all AJAX, import
  class-astra-portfolio-rest-api.php         ← 10 custom REST fields (read-only)
  class-astra-portfolio-api.php              ← HTTP client → websitedemos.net
  class-astra-portfolio-helper.php           ← get_setting() / update_setting()
  class-astra-portfolio-shortcode.php        ← shortcode + asset enqueue
  class-astra-portfolio-block.php            ← Gutenberg block
  class-astra-portfolio-templates.php        ← template loader (theme override)
  batch-processing/                          ← async import (WP_Background_Process)
src/blocks/portfolio/front-end/              ← React (Portfolio.js, PortfolioList.js, Lightboxes)
dist/                                        ← compiled assets — COMMITTED to git
includes/                                    ← PHP + Underscore templates
```

---

## Key Patterns

**Settings** — all stored in one `wp_options` key `astra-portfolio-settings`:
```php
Astra_Portfolio_Helper::get_setting( 'grid-columns', 3 );
Astra_Portfolio_Helper::update_setting( 'grid-columns', 4 );
```

**AJAX handler** — always: verify nonce → check capability → sanitize → respond:
```php
check_ajax_referer( 'astra-portfolio', '_ajax_nonce' );
if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
$val = sanitize_text_field( $_POST['param'] ?? '' );
wp_send_json_success( $result );
```

**Post meta keys:** `astra-portfolio-type` (website|image|video|page), `astra-site-url`, `astra-site-open-portfolio-in` (new-tab|same-tab|iframe), `astra-site-call-to-action`, `astra-portfolio-thumbnail-id`, `astra-portfolio-lightbox-image-id`, `astra-portfolio-video-url`, `astra-remote-post-id`

**REST fields** on `/wp-json/wp/v2/astra-portfolio`: `portfolio-type`, `astra-site-url`, `astra-site-open-portfolio-in`, `astra-site-open-in-new-tab`, `astra-site-call-to-action`, `thumbnail-image-url`, `thumbnail-image-meta`, `lightbox-image-url`, `portfolio-video-url`, `request_time`

**Key filters:** `astra_portfolio_api_url`, `astra_portfolio_api_params`, `astra_portfolio_api_args`, `astra_portfolio_settings`

---

## Security Checklist (every PR)

- [ ] `check_ajax_referer( 'astra-portfolio', '_ajax_nonce' )` on all AJAX
- [ ] `current_user_can( 'manage_options' )` on all admin actions
- [ ] Sanitize inputs: `sanitize_text_field()`, `sanitize_key()`, `esc_url_raw()`, `absint()`
- [ ] Escape outputs: `esc_html()`, `esc_attr()`, `esc_url()`
- [ ] SQL: always use `$wpdb->prepare()`

---

## Gotchas

1. **`dist/` is committed** — run `npm run build` and commit `dist/` with every JS change. Never edit `dist/` directly.
2. **`after_setup_theme` priority 102** — settings pages register at 102 (after Astra theme at 100). Never lower this.
3. **Two shortcodes** — `[astra-portfolio]` and `[wp_portfolio]` are identical aliases. Keep both.
4. **Background import needs loopback** — `WP_Background_Process` makes async requests back to `admin-ajax.php`. Must work in dev.
5. **One option key** — never add standalone `wp_options` entries for settings; use `astra-portfolio-settings` array.

---

## Quick Reference

| Task | File |
|------|------|
| Add CPT meta field | `classes/class-astra-portfolio-admin.php` |
| Add setting | `includes/general-page.php` + `Astra_Portfolio_Helper` |
| Add REST field | `classes/class-astra-portfolio-rest-api.php` |
| Add AJAX handler | `classes/class-astra-portfolio-page.php` |
| Add React component | `src/blocks/portfolio/front-end/` |
| Override template | `{theme}/wp-portfolio/{template}.php` |

**Docs:** `wiki/` (25 wiki pages) · `internal-docs/` (13 onboarding files) — both excluded from dist builds.
