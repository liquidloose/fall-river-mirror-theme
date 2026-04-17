/**
 * Query Loop Block Variation - Sorted by View Count
 *
 * Registers a core/query block variation that sorts articles by the
 * _article_view_count meta field (most viewed first). The __frmCustomFieldFilter
 * attribute signals the PHP query_loop_block_query_vars filter to apply the
 * correct meta sort on the frontend. Editor: core/post-template spreads unknown
 * keys from `query` into getEntityRecords (Gutenberg post-template/edit.js), so
 * markers live inside `query` for per-loop REST params; root attrs stay for
 * isActive and PHP query_loop_block_query_vars.
 *
 * @package FallRiverMirror
 * @since 1.0.0
 */

(function () {
    var VIEW_COUNT_VARIATION_NAMESPACE = 'fr-mirror/query-view-count';
    var VIEW_COUNT_FILTER_KEY = '_article_view_count';

    function tryRegister() {
        if (
            typeof wp === 'undefined' ||
            !wp.blocks ||
            !wp.blocks.registerBlockVariation ||
            !wp.hooks ||
            !wp.hooks.addFilter
        ) {
            setTimeout(tryRegister, 100);
            return;
        }

        // Add variation attributes to core/query's JS attribute schema.
        // Without this Gutenberg strips the attribute on save, causing block
        // validation errors and a broken editor preview.
        wp.hooks.addFilter(
            'blocks.registerBlockType',
            'fr-mirror/query-custom-field-filter-attribute',
            function ( settings, name ) {
                if ( name !== 'core/query' ) {
                    return settings;
                }
                settings.attributes = Object.assign( {}, settings.attributes, {
                    namespace: {
                        type: 'string',
                        default: '',
                    },
                    __frmCustomFieldFilter: {
                        type: 'string',
                        default: '',
                    },
                } );
                return settings;
            }
        );

        wp.blocks.registerBlockVariation('core/query', {
            name: 'filtered-by-view-count',
            title: 'Query: Sorted by View Count',
            description: 'Displays articles sorted by view count (most viewed first).',
            keywords: ['views', 'popular', 'view count', 'articles'],
            icon: 'chart-bar',
            scope: ['inserter', 'block'],
            isDefault: false,
            attributes: {
                namespace: VIEW_COUNT_VARIATION_NAMESPACE,
                __frmCustomFieldFilter: VIEW_COUNT_FILTER_KEY,
                query: {
                    postType: 'article',
                    perPage: 10,
                    offset: 0,
                    order: 'desc',
                    inherit: false,
                    namespace: VIEW_COUNT_VARIATION_NAMESPACE,
                    __frmCustomFieldFilter: VIEW_COUNT_FILTER_KEY,
                },
            },
            isActive: [ 'namespace', '__frmCustomFieldFilter' ],
            innerBlocks: [
                ['core/post-template', {}, [
                    ['core/post-title', { isLink: true }],
                ]],
            ],
        });
    }

    tryRegister();
})();
