<?php

if(!ABSPATH) {
    exit; // Exit if accessed directly
}

// Include custom post types
require_once get_template_directory() . '/includes/article-api-endpoint.php';
require_once get_template_directory() . '/includes/custom-post-types.php';
require_once get_template_directory() . '/includes/article-content-template.php';

// Include block styles
require_once get_template_directory() . '/includes/block-styles.php';

// Include block bindings
require_once get_template_directory() . '/includes/block-bindings.php';

// Instant search: article hit template, Typesense params, longer snippet fallback.
require_once get_template_directory() . '/includes/typesense-instant-search-ui.php';

/**
 * Expose the article post type to CM Typesense index type configuration.
 *
 * @param array $available_post_types Post type configs keyed by slug (label/value pairs).
 * @return array
 */
add_filter( 'cm_typesense_available_index_types', function( $available_post_types ) {
    $available_post_types['article'] = [
        'label' => 'Articles',
        'value' => 'article' // Must match your CPT slug exactly
    ];
    return $available_post_types;
});

/**
 * Use Search Configuration "enabled post types" for the hijacked instant-search popup.
 *
 * 
 * curl -H "X-TYPESENSE-API-KEY: ec5e0cbdfd8a063d1ae421a43b1785457fe43a8c6cec4f84e8c5211047544100" http://localhost:8108/health
 * 
 * 
 * The popup is built from the Customizer option typesense_customizer_instant_search; if that
 * value is missing or never published, Codemanas Search with Typesense falls back to post
 * only (see Frontend::load_popup). Autocomplete already follows enabled_post_types — this
 * keeps the popup in sync so article (and anything else enabled) is actually searched.
 *
 * @param array $options Parsed typesense_customizer_instant_search + defaults.
 * @return array
 */
if ( ! function_exists( 'cm_typesense_sync_popup_enabled_post_types' ) ) {
	function cm_typesense_sync_popup_enabled_post_types( $options ) {
		if ( ! is_array( $options ) || ! class_exists( '\Codemanas\Typesense\Backend\Admin' ) ) {
			return $options;
		}
		$config = \Codemanas\Typesense\Backend\Admin::get_search_config_settings();
		if ( empty( $config['enabled_post_types'] ) || ! is_array( $config['enabled_post_types'] ) ) {
			return $options;
		}
		$slugs = array_values( array_unique( array_map( 'sanitize_key', $config['enabled_post_types'] ) ) );
		if ( $slugs === array() ) {
			return $options;
		}
		$options['available_post_types'] = implode( ',', $slugs );
		return $options;
	}
}
add_filter( 'cm_typesense_popup_shortcode_params', 'cm_typesense_sync_popup_enabled_post_types', 5 );

/**
 * Add article meeting date custom meta field to Typesense schema.
 *
 * @param array  $schema Collection schema.
 * @param string $schema_name Collection/schema key (post type).
 * @return array
 */
if ( ! function_exists( 'cm_typesense_add_article_meeting_date_schema_field' ) ) {
    function cm_typesense_add_article_meeting_date_schema_field( $schema, $schema_name ) {
        if ( $schema_name !== 'article' || empty( $schema['fields'] ) || ! is_array( $schema['fields'] ) ) {
            return $schema;
        }

        foreach ( $schema['fields'] as $field ) {
            if ( isset( $field['name'] ) && $field['name'] === '_article_meeting_date' ) {
                return $schema;
            }
        }

        $schema['fields'][] = array(
            'name'     => '_article_meeting_date',
            'type'     => 'string',
            'optional' => true,
        );

        return $schema;
    }
}
add_filter( 'cm_typesense_schema', 'cm_typesense_add_article_meeting_date_schema_field', 10, 2 );

/**
 * Populate article meeting date in Typesense indexed document.
 *
 * @param array        $formatted_data Typesense document payload.
 * @param WP_Post|mixed $raw_data      Raw object data provided by plugin.
 * @param int          $object_id      Post ID or term ID.
 * @param string       $schema_name    Collection/schema key (post type).
 * @return array
 */
if ( ! function_exists( 'cm_typesense_add_article_meeting_date_data' ) ) {
    function cm_typesense_add_article_meeting_date_data( $formatted_data, $raw_data, $object_id, $schema_name ) {
        if ( $schema_name !== 'article' ) {
            return $formatted_data;
        }

        $post_id = (int) $object_id;
        if ( $post_id <= 0 && $raw_data instanceof WP_Post ) {
            $post_id = (int) $raw_data->ID;
        }

        $formatted_data['_article_meeting_date'] = $post_id > 0
            ? (string) get_post_meta( $post_id, '_article_meeting_date', true )
            : '';

        return $formatted_data;
    }
}
add_filter( 'cm_typesense_data_before_entry', 'cm_typesense_add_article_meeting_date_data', 10, 4 );

/**
 * Enqueue Gutenberg editor scripts for custom meta panels
 * 
 * This loads the JavaScript file that adds custom meta field panels
 * to the Document sidebar in the Gutenberg block editor.
 * The JavaScript file itself checks the post type and only shows on journalist posts.
 */
function the_fall_river_mirror_clone_enqueue_block_editor_assets() {
    wp_enqueue_script(
        'journalist-meta-panel',
        get_template_directory_uri() . '/js/journalist-meta-panel.js',
        array(
            'wp-plugins',
            'wp-editor',
            'wp-element',
            'wp-components',
            'wp-data',
            'wp-core-data',
            'wp-i18n',
            'wp-api-fetch'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'artist-meta-panel',
        get_template_directory_uri() . '/js/artist-meta-panel.js',
        array(
            'wp-plugins',
            'wp-editor',
            'wp-element',
            'wp-components',
            'wp-data',
            'wp-core-data',
            'wp-i18n',
            'wp-api-fetch'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'article-meta-panel',
        get_template_directory_uri() . '/js/article-meta-panel.js',
        array(
            'wp-plugins',
            'wp-editor',
            'wp-element',
            'wp-components',
            'wp-data',
            'wp-core-data',
            'wp-i18n',
            'wp-api-fetch'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'article-meta-block-bindings',
        get_template_directory_uri() . '/js/article-meta-block-bindings.js',
        array(
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-block-editor',
            'wp-api-fetch',
            'wp-core-data',
            'wp-components',
            'wp-compose',
            'wp-hooks',
            'wp-i18n'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'paragraph-meeting-date-variation',
        get_template_directory_uri() . '/js/paragraph-meeting-date-variation.js',
        array(
            'wp-blocks',
            'wp-i18n'
        ),
        wp_get_theme()->get('Version'),
        true
    );

    wp_enqueue_script(
        'query-loop-view-count-variation',
        get_template_directory_uri() . '/js/query-loop-view-count-variation.js',
        array(
            'wp-blocks',
            'wp-hooks',
            'wp-i18n',
        ),
        wp_get_theme()->get('Version'),
        true
    );

    wp_enqueue_script(
        'query-loop-meeting-date-variation',
        get_template_directory_uri() . '/js/query-loop-meeting-date-variation.js',
        array(
            'wp-blocks',
            'wp-hooks',
            'wp-i18n',
        ),
        wp_get_theme()->get('Version'),
        true
    );

    wp_enqueue_script(
        'paragraph-view-count-variation',
        get_template_directory_uri() . '/js/paragraph-view-count-variation.js',
        array(
            'wp-blocks',
            'wp-i18n'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'paragraph-journalist-variation',
        get_template_directory_uri() . '/js/paragraph-journalist-variation.js',
        array(
            'wp-blocks',
            'wp-i18n'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'paragraph-journalist-bio-variation',
        get_template_directory_uri() . '/js/paragraph-journalist-bio-variation.js',
        array(
            'wp-blocks',
            'wp-i18n'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'paragraph-artist-variation',
        get_template_directory_uri() . '/js/paragraph-artist-variation.js',
        array(
            'wp-blocks',
            'wp-i18n'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'journalist-meta-block-bindings',
        get_template_directory_uri() . '/js/journalist-meta-block-bindings.js',
        array(
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-block-editor',
            'wp-api-fetch',
            'wp-core-data',
            'wp-components',
            'wp-compose',
            'wp-hooks',
            'wp-i18n'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'artist-meta-block-bindings',
        get_template_directory_uri() . '/js/artist-meta-block-bindings.js',
        array(
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-block-editor',
            'wp-api-fetch',
            'wp-core-data',
            'wp-components',
            'wp-compose',
            'wp-hooks',
            'wp-i18n'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'fr-mirror-editor',
        get_template_directory_uri() . '/js/editor.js',
        array(
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-block-editor'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'fr-mirror-post-id-block',
        get_template_directory_uri() . '/js/post_id_block.js',
        array(
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-data',
            'wp-hooks'
        ),
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_enqueue_script(
        'fr-mirror-youtube-council-meeting-variation',
        get_template_directory_uri() . '/js/youtube-council-meeting-variation.js',
        array(
            'wp-blocks',
            'wp-element',
            'wp-data',
            'wp-block-editor',
            'wp-compose',
            'wp-hooks'
        ),
        wp_get_theme()->get('Version'),
        true
    );
}
add_action( 'enqueue_block_editor_assets', 'the_fall_river_mirror_clone_enqueue_block_editor_assets' );

/**
 * Set posts per page for category archive pages.
 *
 * @hook pre_get_posts
 */
add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_category() ) {
        $query->set( 'posts_per_page', 15 );
    }
} );

/**
 * Register __frmCustomFieldFilter on core/query:
 * - as a saved attribute (so WordPress doesn't strip it on save)
 * - in providesContext (so it flows down to child blocks)
 *
 * @hook register_block_type_args
 */
add_filter( 'register_block_type_args', function( $args, $block_type ) {
    if ( 'core/query' === $block_type ) {
        $args['attributes']['namespace'] = array(
            'type'    => 'string',
            'default' => '',
        );
        $args['attributes']['__frmCustomFieldFilter'] = array(
            'type'    => 'string',
            'default' => '',
        );
        if ( ! isset( $args['providesContext'] ) ) {
            $args['providesContext'] = array();
        }
        $args['providesContext']['__frmCustomFieldFilter'] = '__frmCustomFieldFilter';
    }

    // Add __frmCustomFieldFilter to core/post-template's usesContext so it
    // receives the value from the parent Query block via context.
    if ( 'core/post-template' === $block_type ) {
        if ( ! isset( $args['usesContext'] ) ) {
            $args['usesContext'] = array();
        }
        if ( ! in_array( '__frmCustomFieldFilter', $args['usesContext'], true ) ) {
            $args['usesContext'][] = '__frmCustomFieldFilter';
        }
    }

    return $args;
}, 10, 2 );

/**
 * Fallback: push __frmCustomFieldFilter into block context at render time.
 *
 * providesContext/usesContext handles the normal path, but this catches
 * edge cases where the context hasn't propagated (e.g. cached blocks).
 *
 * @hook render_block_context
 */
add_filter( 'render_block_context', function( $context, $parsed_block, $parent_block ) {
    if (
        $parent_block &&
        isset( $parent_block->block_type->name ) &&
        'core/query' === $parent_block->block_type->name
    ) {
        $value = $parent_block->attributes['__frmCustomFieldFilter'] ?? '';
        if ( $value ) {
            $context['__frmCustomFieldFilter'] = $value;
        }
    }
    return $context;
}, 10, 3 );

/**
 * Sort article Query Loop blocks by the correct meta field based on variation.
 *
 * - fr-mirror/query-view-count: sort by _article_view_count (numeric)
 * - fr-mirror/query-meeting-date: sort by _article_meeting_date
 * - Legacy article query blocks (no variation marker): fallback to meeting date
 *
 * The block editor's ASC/DESC toggle still works — only the sort field changes.
 *
 * @hook query_loop_block_query_vars
 */
add_filter( 'query_loop_block_query_vars', function( $query, $block ) {
    if ( ( $query['post_type'] ?? '' ) !== 'article' ) {
        return $query;
    }

    $variation_namespace = $block->attributes['namespace'] ?? '';
    $custom_filter = $block->context['__frmCustomFieldFilter']
        ?? $block->attributes['__frmCustomFieldFilter']
        ?? '';

    $is_view_count_variation = (
        $variation_namespace === 'fr-mirror/query-view-count' ||
        $custom_filter === '_article_view_count'
    );
    $is_meeting_date_variation = (
        $variation_namespace === 'fr-mirror/query-meeting-date' ||
        in_array( $custom_filter, array( '_article_meeting_date', 'meeting_date' ), true )
    );
    $is_legacy_article_query = ( ! $variation_namespace && ! $custom_filter );

    // Backward compatibility: old article query blocks without variation markers
    // still sort by meeting date.
    if ( ! $is_view_count_variation && ! $is_meeting_date_variation && ! $is_legacy_article_query ) {
        return $query;
    }

    if ( $is_view_count_variation ) {
        // No meta_query EXISTS — articles without a view count still show,
        // sorted last (MySQL treats NULL as lowest in DESC order).
        $query['meta_key'] = '_article_view_count';
        $query['orderby']  = 'meta_value_num';
    } else {
        $query['meta_key']   = '_article_meeting_date';
        $query['orderby']    = 'meta_value';
        $query['meta_query'] = array(
            array(
                'key'     => '_article_meeting_date',
                'compare' => 'EXISTS',
            ),
        );
    }

    if ( empty( $query['order'] ) ) {
        $query['order'] = 'DESC';
    }

    return $query;
}, 10, 2 );

/**
 * Register __frmCustomFieldFilter as a valid REST collection param for articles.
 *
 * Without this WordPress strips the param before it reaches rest_article_query,
 * so the editor preview can't apply custom sorting.
 *
 * @hook rest_article_collection_params
 */
add_filter( 'rest_article_collection_params', function( $params ) {
    $params['namespace'] = array(
        'description'       => 'Block variation namespace for Query Loop preview parity.',
        'type'              => 'string',
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    );
    $params['__frmCustomFieldFilter'] = array(
        'description'       => 'Custom field filter for Query Loop sorting variations.',
        'type'              => 'string',
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    );

    // Some editor requests nest custom keys under the REST `filter` object; allow them
    // in the schema so they are not stripped before rest_article_query runs.
    if ( isset( $params['filter'] ) && is_array( $params['filter'] ) ) {
        if ( ! isset( $params['filter']['properties'] ) || ! is_array( $params['filter']['properties'] ) ) {
            $params['filter']['properties'] = array();
        }
        $params['filter']['properties']['namespace']              = array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        );
        $params['filter']['properties']['__frmCustomFieldFilter'] = array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        );
    }

    return $params;
} );

/**
 * Apply custom field sorting in the editor via REST API.
 *
 * Only runs when the request explicitly includes variation markers
 * (`namespace` and/or `__frmCustomFieldFilter`). Do not treat “no params”
 * as legacy meeting-date here: generic /wp/v2/article fetches (sidebar,
 * multiple Query blocks, other UI) would all get the same sort and look
 * “stuck” on one mode. Legacy article loops without markers still use
 * meeting-date on the frontend via query_loop_block_query_vars.
 *
 * Reads markers from top-level query args and from the REST `filter` object
 * (some block-editor requests nest attributes there).
 *
 * @hook rest_article_query
 */
add_filter( 'rest_article_query', function( $args, $request ) {
    $filter_params = $request->get_param( 'filter' );
    if ( ! is_array( $filter_params ) ) {
        $filter_params = array();
    }

    $variation_namespace = $request->get_param( 'namespace' );
    if ( $variation_namespace === null || $variation_namespace === '' ) {
        $variation_namespace = isset( $filter_params['namespace'] )
            ? sanitize_text_field( (string) $filter_params['namespace'] )
            : '';
    } else {
        $variation_namespace = sanitize_text_field( (string) $variation_namespace );
    }

    $custom_filter = $request->get_param( '__frmCustomFieldFilter' );
    if ( $custom_filter === null || $custom_filter === '' ) {
        $custom_filter = isset( $filter_params['__frmCustomFieldFilter'] )
            ? sanitize_text_field( (string) $filter_params['__frmCustomFieldFilter'] )
            : '';
    } else {
        $custom_filter = sanitize_text_field( (string) $custom_filter );
    }

    $is_view_count_variation = (
        $variation_namespace === 'fr-mirror/query-view-count' ||
        $custom_filter === '_article_view_count'
    );
    $is_meeting_date_variation = (
        $variation_namespace === 'fr-mirror/query-meeting-date' ||
        in_array( $custom_filter, array( 'meeting_date', '_article_meeting_date' ), true )
    );

    if ( $is_view_count_variation ) {
        $args['meta_key'] = '_article_view_count';
        $args['orderby']  = 'meta_value_num';
        if ( empty( $args['order'] ) ) {
            $args['order'] = 'DESC';
        }
    } elseif ( $is_meeting_date_variation ) {
        $args['meta_key'] = '_article_meeting_date';
        $args['orderby']  = 'meta_value';
        if ( empty( $args['order'] ) ) {
            $args['order'] = 'DESC';
        }
    }

    return $args;
}, 10, 2 );

/**
 * Register Post ID Block with PHP render callback
 * 
 * This allows the block to get the actual post ID from context at render time,
 * which is especially important when the block is used in templates.
 */
function fr_mirror_register_post_id_block() {
    register_block_type('fr-mirror/post-id-block', array(
        'api_version' => 3,
        'render_callback' => 'fr_mirror_render_post_id_block',
        'uses_context' => array('postId', 'postType'),
    ));
}
add_action('init', 'fr_mirror_register_post_id_block');

/**
 * Render callback for Post ID Block
 * 
 * Gets the bullet_points meta field from the post and displays it.
 * 
 * @param array    $attributes Block attributes
 * @param string   $content    Block content (not used)
 * @param WP_Block $block      Block instance with context
 * @return string HTML output
 */
function fr_mirror_render_post_id_block($attributes, $content, $block) {
    // Get post ID from block context (available when template is rendered)
    $post_id = $block->context['postId'] ?? get_the_ID();
    
    // Validate it's a numeric ID
    $post_id = is_numeric($post_id) ? (int) $post_id : null;
    
    if (!$post_id) {
        return '<div class="fr-mirror-bullet-points-block"></div>';
    }
    
    // Get bullet_points meta field
    $bullet_points = get_post_meta($post_id, '_article_bullet_points', true);
    
    if (empty($bullet_points)) {
        return '<div class="fr-mirror-bullet-points-block"></div>';
    }
    
    // Output bullet points with proper sanitization (same as shortcode)
    return '<div class="fr-mirror-bullet-points">' . wp_kses_post($bullet_points) . '</div>';
}

/**
 * Filter embed block rendering on frontend to populate YouTube URL from meta
 * 
 * If the embed block is a Council Meeting variation or YouTube embed without URL,
 * populate it from the article's YouTube ID meta field before rendering.
 * 
 * @param string   $block_content The block's rendered HTML
 * @param array    $block         The block data array
 * @return string Modified block content
 */
function fr_mirror_render_council_meeting_embed($block_content, $block) {
    // Static flag to prevent infinite recursion
    static $processing = false;
    
    if ($processing) {
        return $block_content;
    }
    
    // Only process embed blocks
    if (!isset($block['blockName']) || $block['blockName'] !== 'core/embed') {
        return $block_content;
    }
    
    $attributes = $block['attrs'] ?? array();
    
    // Check if this is a Council Meeting variation
    $is_council_meeting = isset($attributes['__frmCouncilMeeting']) && $attributes['__frmCouncilMeeting'] === true;
    
    // Also check if it's a YouTube embed without a URL or with empty content
    $is_youtube_no_url = isset($attributes['providerNameSlug']) && 
                         $attributes['providerNameSlug'] === 'youtube' && 
                         (empty($attributes['url']) || empty(trim($block_content)));
    
    if (!$is_council_meeting && !$is_youtube_no_url) {
        // Not our variation, return original content
        return $block_content;
    }
    
    // If content is already rendered and not empty, return it
    if (!empty(trim($block_content))) {
        return $block_content;
    }
    
    // Get post ID from block context or current post
    $post_id = $block['context']['postId'] ?? get_the_ID();
    
    if (!$post_id) {
        return $block_content; // Can't get post ID, return original
    }
    
    // Get YouTube ID from meta
    $youtube_id = get_post_meta($post_id, '_article_youtube_id', true);
    
    if (empty($youtube_id)) {
        return $block_content; // No YouTube ID, return original
    }
    
    // Set processing flag to prevent recursion
    $processing = true;
    
    // Construct YouTube URL
    $youtube_url = 'https://www.youtube.com/watch?v=' . esc_attr($youtube_id);
    
    // Use wp_oembed_get to get the embed HTML
    $embed_html = wp_oembed_get($youtube_url, array(
        'width' => 640,
        'height' => 360,
    ));
    
    // Reset processing flag
    $processing = false;
    
    // If wp_oembed_get failed, manually create the embed
    if (empty($embed_html)) {
        $embed_url = 'https://www.youtube.com/embed/' . esc_attr($youtube_id);
        $embed_html = sprintf(
            '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
                <div class="wp-block-embed__wrapper">
                    <iframe loading="lazy" title="%s" width="640" height="360" src="%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            </figure>',
            esc_attr(sprintf(__('Embedded video: %s', 'the-fall-river-mirror-clone'), $youtube_id)),
            esc_url($embed_url)
        );
    } else {
        // Wrap oembed output in the standard embed block structure
        $embed_html = '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
            <div class="wp-block-embed__wrapper">' . $embed_html . '</div>
        </figure>';
    }
    
    return $embed_html;
}
add_filter('render_block', 'fr_mirror_render_council_meeting_embed', 10, 2);

/**
 * Enqueue frontend scripts
 */
function the_fall_river_mirror_clone_enqueue_frontend_assets() {
	wp_enqueue_script(
		'fr-mirror-bullet-points-processor',
		get_template_directory_uri() . '/js/bullet-points-processor.js',
		array(),
		wp_get_theme()->get('Version'),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'the_fall_river_mirror_clone_enqueue_frontend_assets' );

/**
 * Add Google AdSense script to site head
 */
function the_fall_river_mirror_clone_add_adsense_script() {
    ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456" crossorigin="anonymous"></script>
    <?php
}
add_action('wp_head', 'the_fall_river_mirror_clone_add_adsense_script');

/**
 * Include Article Post Type in Category Archive Queries
 * 
 * This modifies category archive queries to include Articles.
 * Without this, category archives only show regular 'post' post types.
 * 
 * @hook pre_get_posts
 * @param WP_Query $query The WP_Query object
 * @return void
 */
if (!function_exists('include_articles_in_category_archives')) {
    function include_articles_in_category_archives($query) {
        // Only run on frontend category archive pages
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Check if we're on a category archive page
        if (is_category()) {
            // Set post type to include Articles
            $query->set('post_type', array('post', 'article'));
        }
    }
    }
    add_action('pre_get_posts', 'include_articles_in_category_archives');

function add_the_fall_river_mirror_clone_og_setup() {
    // 1. Configuration
    $fb_app_id      = "2157817984985248";
    $default_image  = "https://fallrivermirror.com/wp-content/uploads/2026/01/Futuristic-cityscape-with-brutalist-architecture.png";
    $fallback_desc  = "Your leading news source for Fall River City Council meetings";

    if (is_singular()) { 
        global $post;
        
        // TITLE: Fallback to Site Name if headline is missing
        $title = get_the_title($post->ID);
        if (empty($title)) {
            $title = get_bloginfo('name');
        }

        // URL: The canonical permalink
        $url = get_permalink();

        // DESCRIPTION: Manual excerpt, auto-excerpt, or the Council Meeting fallback
        $description = get_the_excerpt();
        if (empty($description)) {
            $description = $fallback_desc;
        }

        // IMAGE: Featured image or the Brutalist fallback
        if (has_post_thumbnail($post->ID)) {
            $img_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'large');
            $image = $img_src[0];
        } else {
            $image = $default_image;
        }
        
        $type = 'article';

        // SECTION: Pulls the primary category (e.g., Politics, Local News)
        $categories = get_the_category($post->ID);
        $section = !empty($categories) ? $categories[0]->name : 'News';

    } else { 
        // Homepage and Archives
        $title = get_bloginfo('name');
        $url = home_url('/');
        $description = get_bloginfo('description') ?: $fallback_desc;
        $image = $default_image;
        $type = 'website';
    }

    // Output tags to the <head>
    echo "\n\n";
    echo '<meta property="fb:app_id" content="' . esc_attr($fb_app_id) . '" />' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($type) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
    echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
    echo '<meta property="og:image:secure_url" content="' . esc_url($image) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr(wp_strip_all_tags($description)) . '" />' . "\n";
    
    if (isset($section)) {
        echo '<meta property="article:section" content="' . esc_attr($section) . '" />' . "\n";
    }
}
add_action('wp_head', 'add_the_fall_river_mirror_clone_og_setup', 5);