/**
 * Query Loop Block Variation - Sorted by Meeting Date
 *
 * Registers a core/query block variation that sorts articles by the
 * _article_meeting_date meta field (newest first).
 *
 * @package FallRiverMirror
 * @since 1.0.0
 */

(function () {
    var MEETING_DATE_VARIATION_NAMESPACE = 'fr-mirror/query-meeting-date';
    var MEETING_DATE_FILTER_KEY = '_article_meeting_date';

    function tryRegister() {
        if (
            typeof wp === 'undefined' ||
            !wp.blocks ||
            !wp.blocks.registerBlockVariation ||
            !wp.hooks ||
            !wp.hooks.addFilter
        ) {
            setTimeout( tryRegister, 100 );
            return;
        }


        wp.blocks.registerBlockVariation( 'core/query', {
            name: 'filtered-by-meeting-date',
            title: 'Query: Sorted by Meeting Date',
            description: 'Displays articles sorted by meeting date (newest first).',
            keywords: [ 'meeting', 'date', 'articles' ],
            icon: 'calendar-alt',
            scope: [ 'inserter', 'block' ],
            isDefault: false,
            attributes: {
                namespace: MEETING_DATE_VARIATION_NAMESPACE,
                __frmCustomFieldFilter: MEETING_DATE_FILTER_KEY,
                query: {
                    postType: 'article',
                    perPage: 10,
                    offset: 0,
                    order: 'desc',
                    inherit: false,
                    namespace: MEETING_DATE_VARIATION_NAMESPACE,
                    __frmCustomFieldFilter: MEETING_DATE_FILTER_KEY,
                },
            },
            // Shorthand: variation is active when these attrs match the defaults above.
            isActive: [ 'namespace', '__frmCustomFieldFilter' ],
            innerBlocks: [
                [ 'core/post-template', {}, [
                    [ 'core/post-title', { isLink: true } ],
                ] ],
            ],
        } );
    }

    tryRegister();
})();
