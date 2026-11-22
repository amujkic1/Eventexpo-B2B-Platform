<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the Event custom post type.
 */
function b2b_register_event_post_type() {

    $labels = array(
        'name'              => 'Events',
        'singular_name'     => 'Event',
        'search_items'      => 'Search Events',
        'all_items'         => 'All Events',
        'edit_item'         => 'Edit Event',
        'add_new_item'      => 'Add New Event',
        'new_item_name'     => 'New Event',
        'menu_name'         => 'Events',
    );

    $args = array(
        'labels'       => $labels,
        'public'       => true,
        'show_in_rest' => true,
        'has_archive'  => false,
        'rewrite'      => array( 'slug' => 'events' ),
        'menu_icon'    => 'dashicons-calendar-alt',
        // Organizers use the company-authorized frontend; native editors are admin-only.
        'capability_type' => array( 'b2b_event', 'b2b_events' ),
        'map_meta_cap' => true,
        'capabilities' => array_fill_keys( array(
            'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts',
            'delete_posts', 'delete_private_posts', 'delete_published_posts',
            'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts',
        ), 'manage_options' ),
        'supports'     => array(
            'title',
            'editor',
            'thumbnail',
        ),
    );

    register_post_type(
        'event',
        $args
    );
}

add_action(
    'init',
    'b2b_register_event_post_type'
);

function b2b_register_event_type_taxonomy() {

    $labels = array(
        'name'              => 'Event Types',
        'singular_name'     => 'Event Type',
        'search_items'      => 'Search Event Types',
        'all_items'         => 'All Event Types',
        'parent_item'       => 'Parent Event Type',
        'parent_item_colon' => 'Parent Event Type:',
        'edit_item'         => 'Edit Event Type',
        'update_item'       => 'Update Event Type',
        'add_new_item'      => 'Add New Event Type',
        'new_item_name'     => 'New Event Type Name',
        'menu_name'         => 'Event Types',
    );

    $args = array(
        'labels'            => $labels,
        'public'            => true,
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array( 'slug' => 'event-type' ),
    );

    register_taxonomy(
        'event_type',
        array( 'event' ),
        $args
    );

}

add_action(
    'init',
    'b2b_register_event_type_taxonomy'
);
