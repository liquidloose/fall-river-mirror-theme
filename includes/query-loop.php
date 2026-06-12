<?php
/**
 * Query Loop block extensions for article sorting.
 *
 * - Registers __frmCustomFieldFilter on core/query blocks
 * - Sorts inherit:false Query blocks via query_loop_block_query_vars
 * - Sorts inherit:true category archives via pre_get_posts (main query)
 * - Mirrors sort in the block editor via rest_article_query
 *
 * @package FallRiverMirror
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set posts per page for category archive pages.
 *
 * @hook pre_get_posts
 */
add_action(
	'pre_get_posts',
	frm_profile_callable(
		'theme:pre_get_posts:category_posts_per_page',
		function( $query ) {
			if ( ! is_admin() && $query->is_main_query() && $query->is_category() ) {
				$query->set( 'posts_per_page', 15 );
			}
		}
	)
);

/**
 * Register __frmCustomFieldFilter on core/query:
 * - as a saved attribute (so WordPress doesn't strip it on save)
 * - in providesContext (so it flows down to child blocks)
 *
 * @hook register_block_type_args
 */
add_filter(
	'register_block_type_args',
	function( $args, $block_type ) {
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

		if ( 'core/post-template' === $block_type ) {
			if ( ! isset( $args['usesContext'] ) ) {
				$args['usesContext'] = array();
			}
			if ( ! in_array( '__frmCustomFieldFilter', $args['usesContext'], true ) ) {
				$args['usesContext'][] = '__frmCustomFieldFilter';
			}
		}

		return $args;
	},
	10,
	2
);

/**
 * Fallback: push __frmCustomFieldFilter into block context at render time.
 *
 * @hook render_block_context
 */
add_filter(
	'render_block_context',
	function( $context, $parsed_block, $parent_block ) {
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
	},
	10,
	3
);

/**
 * True when query vars target the article post type.
 *
 * @param array $query Query Loop or REST query args.
 * @return bool
 */
function fr_mirror_query_is_article( $query ) {
	$post_type = $query['post_type'] ?? '';

	return $post_type === 'article'
		|| ( is_array( $post_type ) && in_array( 'article', $post_type, true ) );
}

/**
 * Known __frmCustomFieldFilter values for article Query Loop sorting.
 *
 * @param string $custom_filter Raw block attribute.
 * @return string|null view_count, meeting_date, or null when unsupported.
 */
function fr_mirror_article_query_sort_mode( $custom_filter ) {
	if ( $custom_filter === '_article_view_count' ) {
		return 'view_count';
	}

	if ( $custom_filter === '' || in_array( $custom_filter, array( '_article_meeting_date', 'meeting_date' ), true ) ) {
		return 'meeting_date';
	}

	return null;
}

/**
 * Apply meta-based sort args for article queries.
 *
 * @param array  $query Query vars.
 * @param string $mode  view_count or meeting_date.
 * @return array
 */
function fr_mirror_apply_article_query_sort( $query, $mode ) {
	if ( $mode === 'view_count' ) {
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
}

/**
 * Sort article Query Loop blocks (inherit:false only).
 *
 * @hook query_loop_block_query_vars
 */
add_filter(
	'query_loop_block_query_vars',
	frm_profile_callable(
		'theme:query_loop_block_query_vars',
		function( $query, $block ) {
			if ( ! fr_mirror_query_is_article( $query ) ) {
				return $query;
			}

			$custom_filter = $block->context['__frmCustomFieldFilter']
				?? $block->attributes['__frmCustomFieldFilter']
				?? '';
			$mode          = fr_mirror_article_query_sort_mode( $custom_filter );

			if ( $mode === null ) {
				return $query;
			}

			return fr_mirror_apply_article_query_sort( $query, $mode );
		}
	),
	10,
	2
);

/**
 * Whitelist __frmCustomFieldFilter on the article REST collection endpoint.
 *
 * @hook rest_article_collection_params
 */
add_filter(
	'rest_article_collection_params',
	function( $params ) {
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
	}
);

/**
 * Mirror Query Loop sort in the block editor (REST preview only).
 *
 * @hook rest_article_query
 */
add_filter(
	'rest_article_query',
	frm_profile_callable(
		'theme:rest_article_query',
		function( $args, $request ) {
			$filter_params = $request->get_param( 'filter' );
			if ( ! is_array( $filter_params ) ) {
				$filter_params = array();
			}

			$custom_filter = $request->get_param( '__frmCustomFieldFilter' );
			if ( $custom_filter === null || $custom_filter === '' ) {
				$custom_filter = $filter_params['__frmCustomFieldFilter'] ?? '';
			}
			$custom_filter = sanitize_text_field( (string) $custom_filter );

			if ( $custom_filter === '' ) {
				return $args;
			}

			$mode = fr_mirror_article_query_sort_mode( $custom_filter );
			if ( $mode === null ) {
				return $args;
			}

			return fr_mirror_apply_article_query_sort( $args, $mode );
		}
	),
	10,
	2
);

/**
 * Configure the main query for category archive pages (inherit:true Query blocks).
 *
 * @hook pre_get_posts
 * @param WP_Query $query The WP_Query object.
 * @return void
 */
function fr_mirror_category_archive_main_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_category() ) {
		return;
	}

	$query->set( 'post_type', 'article' );
	$query->set( 'meta_key', '_article_meeting_date' );
	$query->set( 'orderby', 'meta_value' );
	$query->set( 'order', 'DESC' );
	$query->set(
		'meta_query',
		array(
			array(
				'key'     => '_article_meeting_date',
				'compare' => 'EXISTS',
			),
		)
	);
}
add_action( 'pre_get_posts', 'fr_mirror_category_archive_main_query', 20 );
