<?php
/**
 * Register custom block styles and enqueue block stylesheets
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function the_fall_river_mirror_clone_register_block_styles() {
    // Register the custom group style
    register_block_style(
        'core/group',
        array(
            'name'  => 'the-fall-river-mirror-clone-box-shadow',
            'label' => __( 'Fall River Mirror Box Shadow', 'the-fall-river-mirror-clone' ),
            'isDefault' => false, 
        )
    );

    // Register the custom navigation
    register_block_style(
        'core/navigation',
        array(
            'name'  => 'the-fall-river-mirror-clone-navigation',
            'label' => __('Fall River Mirror Box Shadow', 'the-fall-river-mirror-clone'),
            'isDefault' => false,
        )
    );


    // Enqueue the group block stylesheet
    wp_enqueue_block_style(
        'core/group',
        array(
            'handle' => 'the-fall-river-mirror-clone-theme-group-style',
            'src'    => get_theme_file_uri('assets/css/blocks/core-group.css'),
            'path'   => get_theme_file_path('assets/css/blocks/core-group.css'),
            'deps'   => array(),
            'ver'    => wp_get_theme()->get('Version'),
        )
    );

    // Enqueue the group block stylesheet
    wp_enqueue_block_style(
        'core/group',
        array(
            'handle' => 'the-fall-river-mirror-clone-theme-group-style',
            'src'    => get_theme_file_uri( 'assets/css/blocks/core-group.css' ),
            'path'   => get_theme_file_path( 'assets/css/blocks/core-group.css' ),
            'deps'   => array(),
            'ver'    => wp_get_theme()->get( 'Version' ),
        )
    );

    // Enqueue the group block stylesheet
    wp_enqueue_block_style(
        'core/navigation',
        array(
            'handle' => 'the-fall-river-mirror-clone-theme-navigation-style',
            'src'    => get_theme_file_uri('assets/css/blocks/core-navigation.css'),
            'path'   => get_theme_file_path('assets/css/blocks/core-navigation.css'),
            'deps'   => array(),
            'ver'    => wp_get_theme()->get('Version'),
        )
    );
}
add_action( 'init', 'the_fall_river_mirror_clone_register_block_styles' );

