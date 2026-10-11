<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register Event Registration Custom Post Type.
 */
function b2b_register_event_registration_cpt() {

    register_post_type( 'event_registration', array(

        'labels' => array(
            'name'          => 'Event Registrations',
            'singular_name' => 'Event Registration',
        ),

        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => false,
        'supports'           => array( 'title' ),
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'exclude_from_search'=> true,

    ) );
}

add_action(
    'init',
    'b2b_register_event_registration_cpt'
);

/**
 * Save a registration for an event.
 */
function b2b_save_event_registration( $event_id, $answers ) {

    if ( 'event' !== get_post_type( $event_id ) ) {
        return new WP_Error(
            'invalid_event',
            'Invalid event.'
        );
    }

    if ( ! is_array( $answers ) ) {
        return new WP_Error(
            'invalid_answers',
            'Invalid registration data.'
        );
    }

    $registration_id = wp_insert_post(
        array(
            'post_type'   => 'event_registration',
            'post_status' => 'private',
            'post_title'  => 'Registration - Event ' . $event_id,
        ),
        true
    );

    if ( is_wp_error( $registration_id ) ) {
        return $registration_id;
    }

    update_post_meta(
        $registration_id,
        'event_id',
        $event_id
    );

    update_post_meta(
        $registration_id,
        'registration_answers',
        $answers
    );

    update_post_meta(
        $registration_id,
        'registration_status',
        'pending'
    );

    return $registration_id;
}

/**
 * Add registration details meta box.
 */
function b2b_add_registration_details_meta_box() {

    add_meta_box(
        'b2b_registration_details',
        'Registration Details',
        'b2b_render_registration_details_meta_box',
        'event_registration',
        'normal',
        'high'
    );
}

add_action(
    'add_meta_boxes',
    'b2b_add_registration_details_meta_box'
);


/**
 * Display registration details.
 */
function b2b_render_registration_details_meta_box( $post ) {

    $event_id = absint(
        get_post_meta( $post->ID, 'event_id', true )
    );

    $answers = get_post_meta(
        $post->ID,
        'registration_answers',
        true
    );

    $status = get_post_meta(
        $post->ID,
        'registration_status',
        true
    );

    $event = get_post( $event_id );

    echo '<p><strong>Event:</strong> ';

    echo $event && 'event' === $event->post_type
        ? esc_html( $event->post_title )
        : 'Unknown event';

    echo '</p>';

    echo '<p><strong>Status:</strong> ' .
        esc_html( $status ?: 'pending' ) .
        '</p>';

    echo '<hr>';

    echo '<h3>Submitted Answers</h3>';

    if ( ! is_array( $answers ) || empty( $answers ) ) {
        echo '<p>No answers available.</p>';
        return;
    }

    $fields = b2b_get_event_registration_fields( $event_id );

    foreach ( $fields as $field ) {

        if ( empty( $field['name'] ) ) {
            continue;
        }

        $name = sanitize_key( $field['name'] );

        $label = isset( $field['label'] )
            ? $field['label']
            : $name;

        $value = isset( $answers[ $name ] )
            ? $answers[ $name ]
            : '';

        echo '<p>';

        echo '<strong>' . esc_html( $label ) . ':</strong> ';

        echo esc_html( is_scalar( $value ) ? (string) $value : '' );

        echo '</p>';
    }
}

/**
 * Get registrations for a specific event.
 */
function b2b_get_event_registrations( $event_id ) {

    if (
        'event' !== get_post_type( $event_id ) ||
        ! b2b_user_can_manage_event(
            get_current_user_id(),
            $event_id
        )
    ) {
        return array();
    }

    return get_posts(
        array(
            'post_type'      => 'event_registration',
            'post_status'    => 'private',
            'posts_per_page' => 100,
            'meta_query'     => array(
                array(
                    'key'     => 'event_id',
                    'value'   => $event_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
        )
    );
}

/**
 * Update the status of an event registration.
 */
function b2b_update_registration_status( $registration_id, $new_status ) {

    $registration_id = absint( $registration_id );

    if ( 'event_registration' !== get_post_type( $registration_id ) ) {
        return new WP_Error(
            'invalid_registration',
            'Invalid registration.'
        );
    }

    $allowed_statuses = array(
        'pending',
        'approved',
        'rejected',
    );

    if ( ! in_array( $new_status, $allowed_statuses, true ) ) {
        return new WP_Error(
            'invalid_status',
            'Invalid registration status.'
        );
    }

    $event_id = absint(
        get_post_meta( $registration_id, 'event_id', true )
    );

    if (
        ! $event_id ||
        'event' !== get_post_type( $event_id ) ||
        ! b2b_user_can_manage_event(
            get_current_user_id(),
            $event_id
        )
    ) {
        return new WP_Error(
            'access_denied',
            'You cannot manage this registration.'
        );
    }

    $updated = update_post_meta(
        $registration_id,
        'registration_status',
        $new_status
    );

    if ( false === $updated ) {
        $current_status = get_post_meta(
            $registration_id,
            'registration_status',
            true
        );

        if ( $current_status !== $new_status ) {
            return new WP_Error(
                'update_failed',
                'Could not update registration status.'
            );
        }
    }

    return true;
}