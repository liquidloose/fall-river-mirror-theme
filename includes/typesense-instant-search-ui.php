<?php
/**
 * Instant search UI: article hit template, Typesense params, longer description fallback.
 *
 * @package FallRiverMirror
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load instant search hit templates from includes/ (not search-with-typesense/ in theme root).
 *
 * @param string $template_path Path from locate_template or empty.
 * @param string $file          Relative template filename requested by the plugin.
 * @param array  $args          Template args.
 * @return string
 */
function fr_mirror_typesense_locate_instant_search_results_template( $template_path, $file, $args ) {
	if ( 'instant-search-results.php' !== $file ) {
		return $template_path;
	}
	$includes = get_template_directory() . '/includes/instant-search-results.php';
	if ( is_readable( $includes ) ) {
		return $includes;
	}

	return $template_path;
}
add_filter( 'cm_typesense_locate_template', 'fr_mirror_typesense_locate_instant_search_results_template', 10, 3 );

/**
 * Client-side cap when Typesense returns post_content without a highlight fragment (e.g. title-only match).
 * Patched into the plugin instant-search bundle; does not change Typesense API snippet size.
 */
if ( ! defined( 'FR_MIRROR_TYPESENSE_SNIPPET_FALLBACK_CHARS' ) ) {
	define( 'FR_MIRROR_TYPESENSE_SNIPPET_FALLBACK_CHARS', 500 );
}

/**
 * Typesense `snippet_threshold` (characters): compared to the **indexed field’s total length**, not how long
 * the excerpt should be.
 *
 * - If len(post_content) is **shorter than** this value → Typesense returns the **entire** field in highlights (can be huge).
 * - If len(post_content) is **this long or longer** → “snippet mode”: only a window around each match; size is mostly
 *   controlled by {@see FR_MIRROR_TYPESENSE_HIGHLIGHT_AFFIX_TOKENS}, not this number.
 *
 * So raising this until it exceeds your article body length is what suddenly makes excerpts “massive”: you
 * switched from snippet mode to full-body highlight. For normal long posts, 30 vs 600 vs 2000 often looks
 * identical; tune excerpt length with highlight affix tokens instead.
 *
 * @see https://typesense.org/docs/27.0/api/search.html#search-parameters
 */
if ( ! defined( 'FR_MIRROR_TYPESENSE_SNIPPET_THRESHOLD' ) ) {
	define( 'FR_MIRROR_TYPESENSE_SNIPPET_THRESHOLD', 450 );
}

/**
 * Typesense `highlight_affix_num_tokens`: how many tokens to include on **each side** of a highlighted match
 * when the field is in snippet mode (typical for long post_content). Main knob for “how big the excerpt feels”.
 */
if ( ! defined( 'FR_MIRROR_TYPESENSE_HIGHLIGHT_AFFIX_TOKENS' ) ) {
	define( 'FR_MIRROR_TYPESENSE_HIGHLIGHT_AFFIX_TOKENS', 24 );
}

/**
 * Hit thumbnail box in the hijacked instant-search **popup** (`.cmswt-InstantSearchPopup`), in CSS pixels (square).
 * Display-only; reindex is not required. For a different WP image size in the index, filter `cm_typesense_html_image_size`.
 */
if ( ! defined( 'FR_MIRROR_TYPESENSE_MODAL_THUMB_PX' ) ) {
	define( 'FR_MIRROR_TYPESENSE_MODAL_THUMB_PX', 420 );
}

/**
 * When true: instant search sorts the article index by `_article_meeting_date` (SortBy + patched bundle).
 * Leave false until Typesense has that field on the **article** collection (recreate/update schema from WP, then reindex).
 * Defining this in wp-config.php before ABSPATH loads the theme is fine; otherwise define in functions.php before
 * `require_once … typesense-instant-search-ui.php`.
 */
if ( ! defined( 'FR_MIRROR_TYPESENSE_ARTICLE_MEETING_DATE_SORT' ) ) {
	define( 'FR_MIRROR_TYPESENSE_ARTICLE_MEETING_DATE_SORT', false );
}

/**
 * Hijacked instant-search popup only: when not `'yes'`, forces `sticky_first` off so hits reorder by relevance per query.
 * Customizer "sticky first" maps to global `sort_by=is_sticky:desc,_text_match:desc`, which pins the same sticky posts
 * on top for every search (often looks like "the same results"). Define as `'yes'` in wp-config.php to restore sticky behavior.
 */
if ( ! defined( 'FR_MIRROR_TYPESENSE_POPUP_STICKY_FIRST' ) ) {
	define( 'FR_MIRROR_TYPESENSE_POPUP_STICKY_FIRST', 'no' );
}

/**
 * Merge Typesense multi_search / instant-search highlight options (see constants above).
 *
 * @param array $params Additional search parameters merged into the instant-search client.
 * @return array
 */
function fr_mirror_typesense_additional_search_params( $params ) {
	if ( ! is_array( $params ) ) {
		$params = array();
	}
	// Typesense expects modest affix counts; very large values hurt snippets and can flatten perceived relevance.
	$params['highlight_affix_num_tokens'] = min( 64, max( 4, (int) FR_MIRROR_TYPESENSE_HIGHLIGHT_AFFIX_TOKENS ) );
	$params['snippet_threshold']          = min( 100000, max( 30, (int) FR_MIRROR_TYPESENSE_SNIPPET_THRESHOLD ) );

	return $params;
}
add_filter( 'cm_typesense_additional_search_params', 'fr_mirror_typesense_additional_search_params', 10, 1 );

/**
 * @param array $options Popup shortcode / Customizer-derived options.
 * @return array
 */
function fr_mirror_typesense_popup_respect_sticky_choice( $options ) {
	if ( ! is_array( $options ) ) {
		return $options;
	}
	if ( 'yes' === FR_MIRROR_TYPESENSE_POPUP_STICKY_FIRST ) {
		return $options;
	}
	$options['sticky_first'] = 'no';

	return $options;
}
add_filter( 'cm_typesense_popup_shortcode_params', 'fr_mirror_typesense_popup_respect_sticky_choice', 25 );

/**
 * When instant search is article-only and meeting meta is on the Typesense schema, include it in query_by.
 * Gated with {@see FR_MIRROR_TYPESENSE_ARTICLE_MEETING_DATE_SORT} (same as sort).
 *
 * @param array $out   Parsed shortcode attributes.
 * @param array $pairs Default pairs (unused).
 * @param array $atts  Raw shortcode attributes (unused).
 * @return array
 */
function fr_mirror_typesense_shortcode_query_by_article_only( $out, $pairs, $atts ) {
	$pts = $out['post_types'] ?? '';
	if ( is_array( $pts ) ) {
		$list = array_values( array_filter( array_map( 'trim', $pts ) ) );
	} else {
		$list = array_values( array_filter( array_map( 'trim', explode( ',', (string) $pts ) ) ) );
	}
	$list = array_map( 'sanitize_key', $list );

	if ( FR_MIRROR_TYPESENSE_ARTICLE_MEETING_DATE_SORT && count( $list ) === 1 && $list[0] === 'article' ) {
		$out['query_by'] = 'post_title,post_content,_article_meeting_date';
	}

	return $out;
}
add_filter( 'shortcode_atts_cm_typesense_search', 'fr_mirror_typesense_shortcode_query_by_article_only', 10, 3 );

/**
 * Instant search sort dropdown for the article index: by meeting date (not post_date).
 * Only registered when {@see FR_MIRROR_TYPESENSE_ARTICLE_MEETING_DATE_SORT} is true.
 *
 * @param array  $items     Default Recent / Oldest items from the plugin.
 * @param string $post_type Schema slug in the current loop (e.g. article).
 * @return array
 */
function fr_mirror_typesense_article_sortby_meeting_date( $items, $post_type ) {
	if ( 'article' !== $post_type || ! class_exists( '\Codemanas\Typesense\Main\TypesenseAPI' ) ) {
		return $items;
	}
	$coll = \Codemanas\Typesense\Main\TypesenseAPI::getInstance()->getCollectionNameFromSchema( 'article' );

	return array(
		array(
			'label' => __( 'Meeting date (newest)', 'the-fall-river-mirror-clone' ),
			'value' => $coll . '/sort/_article_meeting_date:desc',
		),
		array(
			'label' => __( 'Meeting date (oldest)', 'the-fall-river-mirror-clone' ),
			'value' => $coll . '/sort/_article_meeting_date:asc',
		),
	);
}
if ( FR_MIRROR_TYPESENSE_ARTICLE_MEETING_DATE_SORT ) {
	add_filter( 'cm_typesense_search_sortby_items', 'fr_mirror_typesense_article_sortby_meeting_date', 10, 2 );
}

/**
 * Build (if needed) a patched instant-search bundle and return its public URL + cache-bust version.
 *
 * Patches:
 * 1) Longer post_content fallback when Typesense has no highlight fragment (substring).
 * 2) Optional: if {@see FR_MIRROR_TYPESENSE_ARTICLE_MEETING_DATE_SORT} is true, inject
 *    `collectionSpecificSearchParameters` so article searches default-sort by `_article_meeting_date`
 *    (requires that field on the live Typesense article collection).
 *
 * @return array{url:string,ver:int}|null
 */
function fr_mirror_typesense_get_patched_instant_search_bundle() {
	static $memo = null;

	if ( null !== $memo ) {
		return $memo ? $memo : null;
	}

	$plugin_js = WP_PLUGIN_DIR . '/search-with-typesense/build/frontend/instant-search.js';
	if ( ! is_readable( $plugin_js ) ) {
		$memo = false;
		return null;
	}

	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) ) {
		$memo = false;
		return null;
	}

	$dir = trailingslashit( $upload['basedir'] ) . 'fr-mirror-typesense-cache';
	$meet = FR_MIRROR_TYPESENSE_ARTICLE_MEETING_DATE_SORT;
	$key  = md5_file( $plugin_js ) . '-' . (int) FR_MIRROR_TYPESENSE_SNIPPET_FALLBACK_CHARS . '-' . ( $meet ? 'meet1' : 'meet0' );
	$file = trailingslashit( $dir ) . 'instant-search-' . $key . '.js';

	$need_rebuild = ! is_readable( $file ) || filemtime( $file ) < filemtime( $plugin_js );
	if ( ! $need_rebuild && is_readable( $file ) ) {
		$existing = file_get_contents( $file );
		$n_sub    = 'substring(0,' . (int) FR_MIRROR_TYPESENSE_SNIPPET_FALLBACK_CHARS . ')';
		if ( false === $existing || strpos( $existing, $n_sub ) === false ) {
			$need_rebuild = true;
		} elseif ( $meet && strpos( $existing, 'collectionSpecificSearchParameters' ) === false ) {
			$need_rebuild = true;
		} elseif ( ! $meet && strpos( $existing, '_article_meeting_date:desc' ) !== false ) {
			$need_rebuild = true;
		}
	}
	if ( $need_rebuild ) {
		if ( ! wp_mkdir_p( $dir ) ) {
			$memo = false;
			return null;
		}
		$src = file_get_contents( $plugin_js );
		if ( false === $src ) {
			$memo = false;
			return null;
		}
		$patched = str_replace( 'substring(0,100)', 'substring(0,' . (int) FR_MIRROR_TYPESENSE_SNIPPET_FALLBACK_CHARS . ')', $src );
		if ( $patched === $src ) {
			$memo = false;
			return null;
		}

		if ( $meet ) {
			$article_collection = 'article';
			if ( class_exists( '\Codemanas\Typesense\Main\TypesenseAPI' ) ) {
				$article_collection = \Codemanas\Typesense\Main\TypesenseAPI::getInstance()->getCollectionNameFromSchema( 'article' );
			}
			$cSpec_json = wp_json_encode(
				array(
					$article_collection => array(
						'sort_by' => '_article_meeting_date:desc',
					),
				),
				JSON_UNESCAPED_SLASHES
			);
			$adapter_find = ',cacheSearchResultsForSeconds:120,additionalSearchParameters:O}).searchClient';
			$adapter_repl = ',cacheSearchResultsForSeconds:120,additionalSearchParameters:O,collectionSpecificSearchParameters:' . $cSpec_json . '}).searchClient';
			$patched2     = str_replace( $adapter_find, $adapter_repl, $patched );
			if ( $patched2 === $patched ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					trigger_error( 'Fall River Mirror: instant-search.js bundle changed; meeting-date sort patch needle not found.', E_USER_WARNING );
				}
				$memo = false;
				return null;
			}
			$patched = $patched2;
		}

		if ( false === file_put_contents( $file, $patched ) ) {
			$memo = false;
			return null;
		}
	}

	$url = trailingslashit( $upload['baseurl'] ) . 'fr-mirror-typesense-cache/' . basename( $file );
	$ver = (int) filemtime( $file );

	$memo = compact( 'url', 'ver' );
	return $memo;
}

/**
 * @param string|false $src    Script source URL.
 * @param string       $handle Script handle.
 * @return string|false
 */
function fr_mirror_typesense_filter_instant_search_script_src( $src, $handle ) {
	if ( 'cm-typesense-instant-search' !== $handle || empty( $src ) ) {
		return $src;
	}

	$bundle = fr_mirror_typesense_get_patched_instant_search_bundle();
	if ( null === $bundle || empty( $bundle['url'] ) ) {
		return $src;
	}

	$wp_scripts = wp_scripts();
	if ( ! empty( $wp_scripts->registered[ $handle ] ) ) {
		$wp_scripts->registered[ $handle ]->ver = (string) $bundle['ver'];
	}

	return $bundle['url'];
}
add_filter( 'script_loader_src', 'fr_mirror_typesense_filter_instant_search_script_src', 10, 2 );

/**
 * Minimal styles for meeting date line in instant search hits (runs after popup shortcode enqueues scripts).
 */
function fr_mirror_typesense_instant_search_hit_styles() {
	if ( ! wp_script_is( 'cm-typesense-instant-search', 'enqueued' ) ) {
		return;
	}
	$thumb_px = max( 40, min( 320, (int) FR_MIRROR_TYPESENSE_MODAL_THUMB_PX ) );

	wp_register_style( 'fr-mirror-typesense-hit-meta', false, array(), wp_get_theme()->get( 'Version' ) );
	wp_enqueue_style( 'fr-mirror-typesense-hit-meta' );
	wp_add_inline_style(
		'fr-mirror-typesense-hit-meta',
		'.hit-meeting-date{font-size:0.9em;opacity:0.85;margin:0.35em 0 0.25em;}'
		. '.cmswt-InstantSearchPopup .hit-description,.cmswt-InstantSearch .hit-description{'
		. 'max-height:none!important;overflow:visible!important;}'
		. '.cmswt-InstantSearchPopup .hit-header img{'
		. 'width:100%!important;height:' . $thumb_px . 'px!important;'
		. 'object-fit:cover;object-position:center;flex-shrink:0;border-radius:4px;}'
	);
}
add_action( 'wp_footer', 'fr_mirror_typesense_instant_search_hit_styles', 15 );
