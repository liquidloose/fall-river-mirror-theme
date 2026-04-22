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
	define( 'FR_MIRROR_TYPESENSE_HIGHLIGHT_AFFIX_TOKENS', 200 );
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
	$params['highlight_affix_num_tokens'] = (int) FR_MIRROR_TYPESENSE_HIGHLIGHT_AFFIX_TOKENS;
	$params['snippet_threshold']          = (int) FR_MIRROR_TYPESENSE_SNIPPET_THRESHOLD;

	return $params;
}
add_filter( 'cm_typesense_additional_search_params', 'fr_mirror_typesense_additional_search_params', 10, 1 );

/**
 * When instant search is article-only, include meeting date in query_by for relevance + highlighting.
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

	if ( count( $list ) === 1 && $list[0] === 'article' ) {
		$out['query_by'] = 'post_title,post_content,_article_meeting_date';
	}

	return $out;
}
add_filter( 'shortcode_atts_cm_typesense_search', 'fr_mirror_typesense_shortcode_query_by_article_only', 10, 3 );

/**
 * Build (if needed) a patched instant-search bundle and return its public URL + cache-bust version.
 *
 * The plugin enqueues this script from wp_footer (hijacked search popup), so mutating it on
 * wp_print_scripts is too early — wp_script_is( 'enqueued' ) is still false. We instead rewrite
 * the src when WordPress resolves it (script_loader_src), which runs during footer script output.
 *
 * The vendor bundle truncates post_content to 100 chars when there is no highlight mark; we replace that constant.
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

	$dir  = trailingslashit( $upload['basedir'] ) . 'fr-mirror-typesense-cache';
	$key  = md5_file( $plugin_js ) . '-' . (int) FR_MIRROR_TYPESENSE_SNIPPET_FALLBACK_CHARS;
	$file = trailingslashit( $dir ) . 'instant-search-' . $key . '.js';

	$need_rebuild = ! is_readable( $file ) || filemtime( $file ) < filemtime( $plugin_js );
	if ( ! $need_rebuild && is_readable( $file ) ) {
		$existing = file_get_contents( $file );
		$needle    = 'substring(0,' . (int) FR_MIRROR_TYPESENSE_SNIPPET_FALLBACK_CHARS . ')';
		if ( false === $existing || strpos( $existing, $needle ) === false ) {
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
			// Plugin bundle changed; do not cache a false success.
			$memo = false;
			return null;
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
	wp_register_style( 'fr-mirror-typesense-hit-meta', false, array(), wp_get_theme()->get( 'Version' ) );
	wp_enqueue_style( 'fr-mirror-typesense-hit-meta' );
	wp_add_inline_style(
		'fr-mirror-typesense-hit-meta',
		'.hit-meeting-date{font-size:0.9em;opacity:0.85;margin:0.35em 0 0.25em;}'
		. '.cmswt-InstantSearchPopup .hit-description,.cmswt-InstantSearch .hit-description{'
		. 'max-height:none!important;overflow:visible!important;}'
	);
}
add_action( 'wp_footer', 'fr_mirror_typesense_instant_search_hit_styles', 15 );
