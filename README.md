# Fall River Mirror Theme

Block theme for the Fall River Mirror newsroom workflow.  
This theme combines Full Site Editing templates with custom post types, block-editor tooling, and API endpoints used by an external content pipeline.

## Requirements

- WordPress `6.9+` (from `style.css` / `readme.txt`)
- PHP `5.7+` (theme header requirement)
- A WordPress install where this folder lives at:
  `wp-content/themes/fall-river-mirror-theme`

Optional, but used by this theme:

- JWT auth plugin compatible with Bearer token auth for REST requests
- Codemanas Search with Typesense plugin (for the Typesense filters in `functions.php`)

## Quick Setup

1. Copy or clone the theme into your WordPress themes directory.
2. From the theme directory, initialize submodules:
   - `git submodule update --init --recursive`
3. In WordPress admin, activate **Fall River Mirror Theme**.
4. Ensure permalinks are enabled (`Settings -> Permalinks`), then save once to flush rewrites.
5. If using API ingestion, verify JWT auth is working against your site.

## Theme Structure

- `style.css` - Theme metadata and lightweight global CSS
- `theme.json` - Global style system (palette, typography, spacing, shadows, layout)
- `functions.php` - Main bootstrap for editor assets, query behavior, SEO/meta tags, and integrations
- `includes/` - CPT registration, block bindings, API endpoints, and block style registration
- `templates/`, `parts/`, `patterns/` - FSE templates and reusable block compositions
- `assets/css/blocks/` - Block-specific styles (`core-group.css`, `core-navigation.css`)
- `js/` - Gutenberg editor extensions (variations, sidebar panels, block bindings)

## Custom Post Types

Loaded via `includes/custom-post-types.php`:

- `article` (`/article/{slug}/`)
- `journalist` (`/journalist/{slug}/`)
- `artist` (`/artist/{slug}/`)

### Article Meta Model

The article CPT registers and uses:

- `_article_content`
- `_article_committee`
- `_article_youtube_id`
- `_article_bullet_points`
- `_article_meeting_date`
- `_article_view_count`
- `_article_journalists` (array of linked journalist IDs)
- `_article_artists` (array of linked artist IDs)

## REST API Endpoints

Defined in `includes/article-api-endpoint.php` under namespace `fr-mirror/v2`:

- `POST /wp-json/fr-mirror/v2/create-article`
- `GET /wp-json/fr-mirror/v2/article-youtube-ids`
- `POST /wp-json/fr-mirror/v2/update-article`

Notes:

- Route access requires authentication (`fr_mirror_require_jwt` + logged-in user check)
- The theme also protects `wp/v2/article*` routes via `rest_request_before_callbacks`
- `post_content` is built as editable Gutenberg content using `fr_mirror_get_required_article_blocks()`

## Editor and Block Behavior

The theme injects multiple editor scripts from `functions.php`:

- Journalist/artist/article sidebar meta panels
- Paragraph/query variations (meeting date, view count, people metadata)
- Block binding helpers for article/journalist/artist meta
- A dynamic block `fr-mirror/post-id-block` that renders bullet points

Block bindings are registered in `includes/block-bindings.php` and expose post meta to templates without hardcoding values in markup.

## Query Loop Sorting for Articles

`functions.php` adds custom Query Loop behavior for article lists:

- View-count variation sorts by `_article_view_count` (numeric)
- Meeting-date variation sorts by `_article_meeting_date`
- Legacy article query loops default to meeting date sort

Equivalent sorting behavior is mirrored for editor previews via `rest_article_collection_params` and `rest_article_query`.

## Search / Typesense Integration

In `functions.php`, the theme:

- Adds `article` as an available Typesense index type
- Syncs instant-search popup post types with Codemanas Search config (`enabled_post_types`)

If Typesense plugin classes are unavailable, these filters safely no-op.

## Frontend Behavior

Key runtime behavior in `functions.php`:

- Category archives use `15` posts per page
- Category archives include both `post` and `article`
- Empty/placeholder YouTube embeds can be populated from `_article_youtube_id`
- Open Graph tags are injected into `<head>` for social previews
- AdSense script is injected in `<head>` (client ID currently hardcoded)

## Content Migration Helper

For legacy data, `includes/article-content-template.php` provides:

- `fr_mirror_backfill_article_post_content_from_meta()`

Run manually via WP-CLI:

- `wp eval 'print_r( fr_mirror_backfill_article_post_content_from_meta() );'`

This only seeds content for articles with empty content or old binding scaffolds.

## Development Notes

- There is no root theme build pipeline in this repository.
- Most theme JS is plain files loaded directly from `js/`.
- The `plugins/to-tha-top` directory is a git submodule with its own Node build (`@wordpress/scripts`).
- Keep custom PHP in `includes/` where possible, and use `functions.php` as integration/orchestration.

## Troubleshooting

- **No article API access**: verify JWT plugin config and token/cookie auth.
- **Article queries sort incorrectly in editor**: confirm variation attributes are present and REST filters are active.
- **Typesense popup only searching posts**: ensure Codemanas plugin is active and search settings contain `article`.
- **Templates not reflecting CPT URLs**: re-save permalinks to refresh rewrite rules.
