<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Persist an authorized frontend edit; preserve its existing validation rules. */
function b2b_update_event_details( $event_id, $title, $description, $event_type_id, array $meta ) {
    foreach ( array( 'start_date', 'end_date' ) as $date_field ) {
        if ( '' !== $meta[ $date_field ] && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $meta[ $date_field ] ) ) {
            $meta[ $date_field ] = '';
        }
    }
    if ( '' === $title ) { return new WP_Error( 'invalid_title', 'Event title is required.' ); }
    if ( '' !== $meta['start_date'] && '' !== $meta['end_date'] && $meta['end_date'] < $meta['start_date'] ) {
        return new WP_Error( 'invalid_dates', 'End date cannot be before the start date.' );
    }
    $result = wp_update_post( array( 'ID' => $event_id, 'post_title' => $title, 'post_content' => $description ), true );
    if ( is_wp_error( $result ) ) { return new WP_Error( 'save_failed', 'Could not save event.' ); }
    foreach ( $meta as $key => $value ) { update_post_meta( $event_id, $key, $value ); }
    if ( $event_type_id && term_exists( $event_type_id, 'event_type' ) ) {
        wp_set_object_terms( $event_id, array( $event_type_id ), 'event_type' );
    } else {
        wp_set_object_terms( $event_id, array(), 'event_type' );
    }
    return true;
}

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

    if ( ! $user_id || 'event' !== get_post_type( $event_id ) ) { return false; }

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

/** Viewing a company's workspace does not imply permission to edit during review. */
function b2b_user_can_edit_event( $user_id, $event_id ) {
    return b2b_user_can_manage_event( $user_id, $event_id )
        && ( user_can( $user_id, 'manage_options' )
            || in_array( get_post_status( $event_id ), array( 'draft', 'publish' ), true ) );
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

    return add_query_arg(
        'event_id',
        absint( $event_id ),
        b2b_page_url( 'b2b_manage_event', 'manage-event' )
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

/** Create only a draft, with the company taken from the signed-in account. */
function b2b_create_event_draft( $title ) {
    $user_id = get_current_user_id();
    $company_id = (int) get_user_meta( $user_id, 'company_id', true );
    if ( ! $user_id || 'company' !== get_post_type( $company_id ) ) {
        return new WP_Error( 'access_denied', 'A company profile is required to create an event.' );
    }
    $title = is_string( $title ) ? sanitize_text_field( $title ) : '';
    if ( '' === $title ) { return new WP_Error( 'invalid_title', 'Event title is required.' ); }

    $event_id = wp_insert_post( wp_slash( array(
        'post_type' => 'event', 'post_status' => 'draft', 'post_author' => $user_id,
        'post_title' => $title, 'meta_input' => array( 'organizer_company_id' => $company_id ),
    ) ), true );
    if ( is_wp_error( $event_id ) ) { return $event_id; }
    if ( (int) get_post_meta( $event_id, 'organizer_company_id', true ) !== $company_id ) {
        wp_delete_post( $event_id, true );
        return new WP_Error( 'save_failed', 'Could not connect the event to your company. Please try again.' );
    }
    return $event_id;
}

/** Submission changes only the native post status; publication stays in WP Admin. */
function b2b_submit_event_for_approval( $event_id ) {
    if ( ! b2b_user_can_manage_event( get_current_user_id(), $event_id ) ) {
        return new WP_Error( 'access_denied', 'You cannot submit this event.' );
    }
    if ( 'draft' !== get_post_status( $event_id ) ) {
        return new WP_Error( 'invalid_status', 'Only a draft event can be submitted for approval.' );
    }
    return wp_update_post( array( 'ID' => $event_id, 'post_status' => 'pending' ), true );
}
