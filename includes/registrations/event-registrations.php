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

    if ( ! is_array( $answers ) ) { return new WP_Error( 'invalid_answers', 'Invalid registration data.' ); }
    $result = b2b_save_unique_registration( $event_id, $answers );
    if ( ! is_wp_error( $result ) ) {
        // The writer has committed and released its lock before mail is attempted.
        b2b_send_registration_notification( $result, 'received' );
        b2b_send_registration_notification( $result, 'organizer' );
    }
    return $result;
}

/** Send one best-effort plain-text message without changing the saved result. */
function b2b_send_registration_notification( $registration_id, $type ) {
    $messages = array(
        'received'  => array( 'Registration received', 'Your registration has been received and is awaiting review.' ),
        'organizer' => array( 'New event registration', 'A new registration is awaiting your review. Log in to manage it using the link below.' ),
        'approved'  => array( 'Registration approved', 'Your registration has been approved.' ),
        'rejected'  => array( 'Registration not approved', 'Your registration has not been approved.' ),
        'pending'   => array( 'Registration returned to review', 'Your registration has returned to review and is awaiting a new decision. It is not currently approved.' ),
    );
    if ( ! isset( $messages[ $type ] ) ) { return; }

    $failure = 'invalid_context';
    try {
        $event_id = (int) get_post_meta( $registration_id, 'event_id', true );
        $event = get_post( $event_id );
        if ( ! $event || 'event' !== $event->post_type ) { return; }

        $failure = 'invalid_recipient';
        $recipient = get_post_meta( $registration_id, '_b2b_attendee_email', true );
        $url = get_permalink( $event_id );
        if ( 'organizer' === $type ) {
            $company_id = (int) get_post_meta( $event_id, 'organizer_company_id', true );
            $company = $company_id ? get_post( $company_id ) : null;
            $owner = $company && 'company' === $company->post_type ? get_userdata( $company->post_author ) : false;
            if ( ! $owner || (int) get_user_meta( $owner->ID, 'company_id', true ) !== $company_id
                || ! b2b_user_can_manage_event( $owner->ID, $event_id ) ) {
                return;
            }
            $recipient = $owner->user_email;
            $url = b2b_get_manage_event_url( $event_id );
        }
        if ( ! is_string( $recipient ) || strpbrk( $recipient, "\r\n" ) || ! is_email( $recipient ) ) { return; }

        $title = sanitize_text_field( wp_specialchars_decode( wp_strip_all_tags( $event->post_title ), ENT_QUOTES ) );
        $body = sprintf( "Event: %s\nRegistration: #%d\n\n%s\n\n%s\n\nEventExpo B2B",
            $title, $registration_id, $messages[ $type ][1], esc_url_raw( $url, array( 'http', 'https' ) ) );
        $failure = 'mail_failed';
        if ( wp_mail( $recipient, 'EventExpo B2B: ' . $messages[ $type ][0], $body, array(
            'From: EventExpo B2B <info@eventexpo.ba>',
            'Content-Type: text/plain; charset=UTF-8',
        ) ) ) {
            $failure = '';
        }
    } catch ( Throwable $error ) {
        $failure = 'mail_exception';
    } finally {
        if ( $failure ) {
            // IDs and fixed codes only: transport errors can contain private data.
            error_log( sprintf( 'EventExpo notification: registration=%d type=%s error=%s', $registration_id, $type, $failure ) );
        }
    }
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

    $event_title = $event && 'event' === $event->post_type
        ? $event->post_title
        : 'Unknown event';
    $has_answers = is_array( $answers ) && ! empty( $answers );
    $answer_rows = b2b_get_registration_answer_rows( $post->ID, $event_id );

    echo b2b_render_template(
        'admin/registration-details',
        compact( 'event_title', 'status', 'has_answers', 'answer_rows' )
    );
}

/**
 * Get registrations for a specific event.
 */
function b2b_get_event_registrations( $event_id, $page = 1, $search = '', $status = '' ) {
    $empty = array( 'posts' => array(), 'total' => 0, 'pages' => 0, 'page' => 1, 'counts' => array( 'total' => 0, 'approved' => 0, 'pending' => 0 ) );
    if ( 'event' !== get_post_type( $event_id ) || ! b2b_user_can_manage_event( get_current_user_id(), $event_id ) ) {
        return $empty;
    }
    $base = array(
        'post_type' => 'event_registration', 'post_status' => 'private',
        'posts_per_page' => 25, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ),
        'meta_query' => array( array( 'key' => 'event_id', 'value' => $event_id, 'type' => 'NUMERIC' ) ),
    );
    $status_clause = static function ( $value ) {
        if ( 'pending' === $value ) {
            return array( 'relation' => 'OR',
                array( 'key' => 'registration_status', 'value' => 'pending' ),
                array( 'key' => 'registration_status', 'value' => '' ),
                array( 'key' => 'registration_status', 'compare' => 'NOT EXISTS' ),
            );
        }
        return array( 'key' => 'registration_status', 'value' => $value );
    };
    $counts = array();
    foreach ( array( 'total', 'approved', 'pending' ) as $count_status ) {
        $args = $base;
        $args['posts_per_page'] = 1;
        $args['fields'] = 'ids';
        if ( 'total' !== $count_status ) {
            $args['meta_query'][] = $status_clause( $count_status );
        }
        $count_query = new WP_Query( $args );
        $counts[ $count_status ] = (int) $count_query->found_posts;
    }
    if ( in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
        $base['meta_query'][] = $status_clause( $status );
    }
    if ( '' !== $search ) {
        if ( preg_match( '/^#([1-9][0-9]*)$/D', $search, $number ) ) {
            $base['post__in'] = array( absint( $number[1] ) );
        } else {
            $base['meta_query'][] = array( 'key' => 'registration_answers', 'value' => $search, 'compare' => 'LIKE' );
        }
    }
    // Count from page one: WP_Query skips found_posts for an empty out-of-range page.
    $count_args = $base;
    $count_args['posts_per_page'] = 1;
    $count_args['fields'] = 'ids';
    $matches = new WP_Query( $count_args );
    $total = (int) $matches->found_posts;
    $pages = (int) ceil( $total / $base['posts_per_page'] );
    $base['paged'] = min( max( 1, (int) $page ), max( 1, $pages ) );
    $base['no_found_rows'] = true;
    $query = new WP_Query( $base );
    return array( 'posts' => $query->posts, 'total' => $total, 'pages' => $pages, 'page' => $base['paged'], 'counts' => $counts );
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

    global $wpdb;
    $lock = 'ee-email-migrate-' . md5( $wpdb->prefix );
    $suppressed = $wpdb->suppress_errors( true );
    if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
        $wpdb->suppress_errors( $suppressed );
        return new WP_Error( 'storage_busy', 'Registration is temporarily busy. Please try again.' );
    }
    $reconnect_retries = $wpdb->reconnect_retries;
    $wpdb->reconnect_retries = 0;
    try {
        // Read fresh values after waiting for another status writer or migration.
        wp_cache_delete( $registration_id, 'post_meta' );
        $event_id = absint( get_post_meta( $registration_id, 'event_id', true ) );
        wp_cache_delete( $event_id, 'post_meta' );
        wp_cache_delete( get_current_user_id(), 'user_meta' );
        if ( ! $event_id || 'event' !== get_post_type( $event_id )
            || ! b2b_user_can_manage_event( get_current_user_id(), $event_id ) ) {
            return new WP_Error( 'access_denied', 'You cannot manage this registration.' );
        }
        $current_status = get_post_meta( $registration_id, 'registration_status', true ) ?: 'pending';
        if ( $current_status === $new_status ) { return true; }

        $updated = update_post_meta( $registration_id, 'registration_status', $new_status );
        if ( false === $updated || get_post_meta( $registration_id, 'registration_status', true ) !== $new_status ) {
            return new WP_Error( 'update_failed', 'Could not update registration status.' );
        }
    } finally {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        $wpdb->reconnect_retries = $reconnect_retries;
        $wpdb->suppress_errors( $suppressed );
    }

    b2b_send_registration_notification( $registration_id, $new_status );
    return true;
}
