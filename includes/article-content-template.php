<?php

/**
 * Build editable post_content blocks from legacy article HTML content.
 *
 * If incoming content already contains Gutenberg blocks, return it as-is.
 * Otherwise wrap sanitized HTML in a core/html block so editors can update
 * content in the canvas without depending on block bindings.
 *
 * @param string $article_content Legacy article body content.
 * @return string
 */
function fr_mirror_get_required_article_blocks( $title = '', $article_content = '' ) {
    $legacy_content = trim( (string) $article_content );

    if ( $legacy_content === '' ) {
        return "<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->";
    }

    $sanitized_content = wp_kses_post( $legacy_content );

    if ( function_exists( 'has_blocks' ) && has_blocks( $sanitized_content ) ) {
        return $sanitized_content;
    }

    return "<!-- wp:html -->\n{$sanitized_content}\n<!-- /wp:html -->";
}

/**
 * Backfill legacy _article_content values into editable post_content blocks.
 *
 * Updates only posts that still have empty content or the old article-meta
 * binding scaffold, preserving manually edited post_content.
 *
 * @return array{updated:int, skipped:int}
 */
function fr_mirror_backfill_article_post_content_from_meta() {
    $articles = get_posts( array(
        'post_type'      => 'article',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
    ) );

    $updated = 0;
    $skipped = 0;

    foreach ( $articles as $article ) {
        $current_content    = (string) $article->post_content;
        $legacy_meta_value  = (string) get_post_meta( $article->ID, '_article_content', true );
        $is_empty_content   = trim( $current_content ) === '';
        $has_legacy_binding = (
            strpos( $current_content, 'fr-mirror/article-meta' ) !== false &&
            strpos( $current_content, '_article_content' ) !== false
        );
        $needs_content_seed   = ( $legacy_meta_value !== '' && ( $is_empty_content || $has_legacy_binding ) );

        if ( ! $needs_content_seed ) {
            $skipped++;
            continue;
        }

        $new_content = fr_mirror_get_required_article_blocks( '', $legacy_meta_value );
        $result      = wp_update_post(
            array(
                'ID'           => $article->ID,
                'post_content' => $new_content,
            ),
            true
        );

        if ( is_wp_error( $result ) ) {
            $skipped++;
            continue;
        }

        $updated++;
    }

    return array(
        'updated' => $updated,
        'skipped' => $skipped,
    );
}

/**
 * Manual migration entrypoint for backfill.
 *
 * Intentionally not auto-run on admin requests to avoid blocking wp-admin screens
 * on sites with large article counts.
 *
 * Usage (WP-CLI):
 * wp eval 'print_r( fr_mirror_backfill_article_post_content_from_meta() );'
 */