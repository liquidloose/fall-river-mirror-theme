/**
 * Gutenberg Document Sidebar Panel for Artist Custom Fields
 * 
 * This adds a custom panel in the Document sidebar (right side) of Gutenberg
 * that allows editing artist custom fields directly in the block editor.
 */

(function () {
    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editor;
    const { useEntityProp } = wp.coreData;
    const { TextControl, TextareaControl } = wp.components;
    const { __ } = wp.i18n;
    const { useSelect, useDispatch } = wp.data;
    const { useState, useEffect } = wp.element;
    const apiFetch = wp.apiFetch;

    function ArtistMetaPanel() {
        // Only show on artist post type
        const postType = useSelect((select) =>
            select('core/editor').getCurrentPostType()
        );

        if (postType !== 'artist') {
            return null;
        }

        const postId = useSelect((select) =>
            select('core/editor').getCurrentPostId()
        );

        // Use local state for meta - we'll save it manually
        const [meta, setMeta] = useState({});
        const [isInitialized, setIsInitialized] = useState(false);
        const [isLoading, setIsLoading] = useState(true);

        // Initialize meta with saved values from REST API when component loads
        useEffect(function () {
            if (!postId || isInitialized) return;
            
            setIsLoading(true);

            // Fetch the post with meta fields from REST API to initialize
            // console.log('Initializing meta for post:', postId);
            apiFetch({
                path: '/wp/v2/artist/' + postId + '?context=edit'
            }).then(function (post) {
                // console.log('Fetched post meta:', post.meta);
                // Extract meta fields and ensure all expected fields are initialized
                const metaFields = {
                    _artist_first_name: '',
                    _artist_last_name: '',
                    _artist_title: '',
                    _artist_email: '',
                    _artist_website: '',
                    _artist_instagram: '',
                    _artist_bio_short: ''
                };
                if (post.meta) {
                    Object.keys(post.meta).forEach(function (key) {
                        if (key.startsWith('_artist_')) {
                            metaFields[key] = post.meta[key] || '';
                        }
                    });
                }
                // console.log('Setting initial meta:', metaFields);
                setMeta(metaFields);
                setIsInitialized(true);
                setIsLoading(false);
            }).catch(function (error) {
                console.error('Error fetching artist meta:', error);
                setIsInitialized(true);
                setIsLoading(false);
            });
        }, [postId, isInitialized]);

        // Hook into save to save meta via REST API
        const isSaving = useSelect(function (select) {
            return select('core/editor').isSavingPost();
        });
        const [wasSaving, setWasSaving] = useState(false);
        const [pendingMeta, setPendingMeta] = useState(null);

        // Save meta when save starts
        useEffect(function () {
            if (!wasSaving && isSaving && postId && isInitialized) {
                // Only save if we have meta data
                if (!meta || Object.keys(meta).length === 0) {
                    setWasSaving(isSaving);
                    return;
                }

                // Save meta fields via REST API
                // WordPress REST API expects meta in a 'meta' object
                // console.log('Saving meta:', JSON.stringify(meta, null, 2));
                setPendingMeta(meta);

                // Send meta in the correct format for WordPress REST API
                apiFetch({
                    path: '/wp/v2/artist/' + postId,
                    method: 'POST',
                    data: {
                        meta: meta
                    }
                }).then(function (response) {
                    // console.log('Meta saved successfully. Response meta:', JSON.stringify(response.meta, null, 2));
                    setPendingMeta(null);
                }).catch(function (error) {
                    console.error('Error saving artist meta:', error);
                    // console.error('Error details:', error.message, error.data);
                    setPendingMeta(null);
                });
            }
            setWasSaving(isSaving);
        }, [isSaving, postId, wasSaving, meta, isInitialized]);

        // Refresh after save completes
        useEffect(function () {
            if (wasSaving && !isSaving && postId && pendingMeta === null) {
                setTimeout(function () {
                    // console.log('Refreshing meta after save...');
                    apiFetch({
                        path: '/wp/v2/artist/' + postId + '?context=edit'
                    }).then(function (post) {
                        // console.log('Refreshed post meta:', JSON.stringify(post.meta, null, 2));
                        const metaFields = {
                            _artist_first_name: '',
                            _artist_last_name: '',
                            _artist_title: '',
                            _artist_email: '',
                            _artist_website: '',
                            _artist_instagram: '',
                            _artist_bio_short: ''
                        };
                        if (post.meta) {
                            Object.keys(post.meta).forEach(function (key) {
                                if (key.startsWith('_artist_')) {
                                    metaFields[key] = post.meta[key] || '';
                                }
                            });
                        }
                        // console.log('Setting refreshed meta:', JSON.stringify(metaFields, null, 2));
                        setMeta(metaFields);
                    }).catch(function (error) {
                        console.error('Error refreshing meta:', error);
                    });
                }, 500);
            }
        }, [isSaving, wasSaving, postId, pendingMeta]);

        // Show loading state while fetching initial data
        if (isLoading) {
            return wp.element.createElement(
                PluginDocumentSettingPanel,
                {
                    name: 'artist-information',
                    title: __('Artist Information', 'the-fall-river-mirror-clone'),
                    className: 'artist-meta-panel'
                },
                wp.element.createElement('p', {}, __('Loading...', 'the-fall-river-mirror-clone'))
            );
        }

        return wp.element.createElement(
            PluginDocumentSettingPanel,
            {
                name: 'artist-information',
                title: __('Artist Information', 'the-fall-river-mirror-clone'),
                className: 'artist-meta-panel'
            },
            // Name field removed - using post title instead
            wp.element.createElement(TextControl, {
                label: __('First Name', 'the-fall-river-mirror-clone'),
                value: (meta && meta._artist_first_name) || '',
                onChange: (value) => setMeta({ ...(meta || {}), _artist_first_name: value || '' })
            }),
            wp.element.createElement(TextControl, {
                label: __('Last Name', 'the-fall-river-mirror-clone'),
                value: (meta && meta._artist_last_name) || '',
                onChange: (value) => setMeta({ ...(meta || {}), _artist_last_name: value || '' })
            }),
            wp.element.createElement(TextControl, {
                label: __('Title/Position', 'the-fall-river-mirror-clone'),
                value: (meta && meta._artist_title) || '',
                onChange: (value) => setMeta({ ...(meta || {}), _artist_title: value || '' }),
                help: __('e.g., Photographer, Illustrator, etc.', 'the-fall-river-mirror-clone')
            }),
            wp.element.createElement(TextControl, {
                label: __('Email', 'the-fall-river-mirror-clone'),
                type: 'email',
                value: (meta && meta._artist_email) || '',
                onChange: (value) => setMeta({ ...(meta || {}), _artist_email: value || '' })
            }),
            wp.element.createElement(TextControl, {
                label: __('Website', 'the-fall-river-mirror-clone'),
                type: 'url',
                value: (meta && meta._artist_website) || '',
                onChange: (value) => setMeta({ ...(meta || {}), _artist_website: value || '' }),
                placeholder: 'https://example.com'
            }),
            wp.element.createElement(TextControl, {
                label: __('Instagram', 'the-fall-river-mirror-clone'),
                value: (meta && meta._artist_instagram) || '',
                onChange: (value) => setMeta({ ...(meta || {}), _artist_instagram: value || '' }),
                placeholder: '@username'
            }),
            wp.element.createElement(TextareaControl, {
                __nextHasNoMarginBottom: true,
                label: __('Short Bio', 'the-fall-river-mirror-clone'),
                value: (meta && meta._artist_bio_short) || '',
                onChange: (value) => setMeta({ ...(meta || {}), _artist_bio_short: value || '' }),
                help: __('A brief bio or description of the artist.', 'the-fall-river-mirror-clone'),
                rows: 3
            })
        );
    }

    registerPlugin('artist-meta-panel', {
        render: ArtistMetaPanel,
        icon: 'art'
    });
})();

