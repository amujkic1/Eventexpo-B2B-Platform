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

/**
 * Register the Event Details meta box.
 */
function b2b_add_event_details_meta_box() {

    add_meta_box(
        'b2b_event_details',
        'Event Details',
        'b2b_render_event_details_meta_box',
        'event',
        'normal',
        'default'
    );
}

add_action(
    'add_meta_boxes',
    'b2b_add_event_details_meta_box'
);

/**
 * Render the Event Details meta box.
 */
function b2b_render_event_details_meta_box( $post ) {

    $start_date = get_post_meta(
        $post->ID,
        'start_date',
        true
    );

    $end_date = get_post_meta(
        $post->ID,
        'end_date',
        true
    );

    $location = get_post_meta(
        $post->ID,
        'location',
        true
    );

    $city = get_post_meta(
        $post->ID,
        'city',
        true
    );

    $country = get_post_meta(
        $post->ID,
        'country',
        true
    );

    $organizer_company_id = get_post_meta(
        $post->ID,
        'organizer_company_id',
        true
    );

    $companies = get_posts(
        array(
            'post_type'      => 'company',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    echo b2b_render_template(
        'admin/event-details',
        compact( 'start_date', 'end_date', 'location', 'city', 'country', 'organizer_company_id', 'companies' )
    );
}

/**
 * Save the Event Details meta box data.
 */
function b2b_save_event_details( $post_id ) {

    if (
        ! isset( $_POST['b2b_event_details_nonce'] ) ||
        ! wp_verify_nonce(
            sanitize_text_field(
                wp_unslash( $_POST['b2b_event_details_nonce'] )
            ),
            'b2b_save_event_details'
        )
    ) {
        return;
    }

    if (
        defined( 'DOING_AUTOSAVE' ) &&
        DOING_AUTOSAVE
    ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = array(
        'start_date',
        'end_date',
        'location',
        'city',
        'country',
    );

    foreach ( $fields as $field ) {

        if ( isset( $_POST[ $field ] ) ) {

            $value = sanitize_text_field(
                wp_unslash( $_POST[ $field ] )
            );

            update_post_meta(
                $post_id,
                $field,
                $value
            );
        }
    }

    if ( isset( $_POST['organizer_company_id'] ) ) {

        $organizer_company_id = absint(
            $_POST['organizer_company_id']
        );

        if (
            $organizer_company_id > 0 &&
            'company' === get_post_type( $organizer_company_id )
        ) {
            update_post_meta(
                $post_id,
                'organizer_company_id',
                $organizer_company_id
            );
        } else {
            delete_post_meta(
                $post_id,
                'organizer_company_id'
            );
        }
    }
}

add_action(
    'save_post_event',
    'b2b_save_event_details'
);

/**
 * Get event data.
 *
 * @param int $event_id Event post ID.
 * @return array Event data.
 */
function b2b_get_event_data( $event_id ) {

    return array(
        'event'                => get_post( $event_id ),
        'start_date'           => get_post_meta( $event_id, 'start_date', true ),
        'end_date'             => get_post_meta( $event_id, 'end_date', true ),
        'location'             => get_post_meta( $event_id, 'location', true ),
        'city'                 => get_post_meta( $event_id, 'city', true ),
        'country'              => get_post_meta( $event_id, 'country', true ),
        'organizer_company_id' => get_post_meta( $event_id, 'organizer_company_id', true ),
    );
}

/**
 * Check whether a user can manage an event.
 *
 * Administrators can manage all events.
 * Other users can manage events organized by their company.
 *
 * @param int $user_id  User ID.
 * @param int $event_id Event ID.
 * @return bool
 */
function b2b_user_can_manage_event( $user_id, $event_id ) {

    if ( user_can( $user_id, 'manage_options' ) ) {
        return true;
    }

    $company_id = get_user_meta(
        $user_id,
        'company_id',
        true
    );

    if ( empty( $company_id ) ) {
        return false;
    }

    $organizer_company_id = get_post_meta(
        $event_id,
        'organizer_company_id',
        true
    );

    if ( empty( $organizer_company_id ) ) {
        return false;
    }

    return (int) $company_id === (int) $organizer_company_id;
}

/**
 * Get the frontend URL for managing an event.
 *
 * Points to the page containing the [b2b_manage_event] shortcode.
 * The page path can be changed with the b2b_manage_event_page_path filter.
 *
 * @param int $event_id Event ID.
 * @return string
 */
function b2b_get_manage_event_url( $event_id ) {

    $page_path = apply_filters(
        'b2b_manage_event_page_path',
        'manage-event'
    );

    return add_query_arg(
        'event_id',
        absint( $event_id ),
        home_url( '/' . trailingslashit( $page_path ) )
    );
}

/**
 * Get the events a user can manage.
 *
 * Administrators get all events.
 * Other users get events organized by their company.
 *
 * @param int $user_id User ID.
 * @return WP_Post[]
 */
function b2b_get_manageable_events( $user_id ) {

    $args = array(
        'post_type'      => 'event',
        'post_status'    => array( 'publish', 'draft', 'pending' ),
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if ( ! user_can( $user_id, 'manage_options' ) ) {

        $company_id = get_user_meta(
            $user_id,
            'company_id',
            true
        );

        if ( empty( $company_id ) ) {
            return array();
        }

        $args['meta_query'] = array(
            array(
                'key'   => 'organizer_company_id',
                'value' => absint( $company_id ),
            ),
        );
    }

    return get_posts( $args );
}
