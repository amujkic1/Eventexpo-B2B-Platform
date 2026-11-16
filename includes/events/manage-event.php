<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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

/** Process new lifecycle actions before output, then redirect to prevent POST replay. */
function b2b_handle_event_lifecycle_request() {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' )
        || ( ! isset( $_POST['b2b_create_event'] ) && ! isset( $_POST['b2b_submit_event'] ) ) ) { return; }
    $creating = isset( $_POST['b2b_create_event'] );
    $event_id = isset( $_GET['event_id'] ) && is_string( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
    $nonce = isset( $_POST['b2b_lifecycle_nonce'] ) && is_string( $_POST['b2b_lifecycle_nonce'] )
        ? wp_unslash( $_POST['b2b_lifecycle_nonce'] ) : '';
    $action = $creating ? 'b2b_create_event' : 'b2b_submit_event_' . $event_id;
    $title = isset( $_POST['event_title'] ) && is_string( $_POST['event_title'] )
        ? sanitize_text_field( wp_unslash( $_POST['event_title'] ) ) : '';
    if ( ! is_user_logged_in() || ! wp_verify_nonce( $nonce, $action ) ) {
        $result = new WP_Error( 'security_check', 'Security check failed. Reload the page and try again.' );
    } else {
        $result = $creating ? b2b_create_event_draft( $title ) : b2b_submit_event_for_approval( $event_id );
    }
    if ( is_wp_error( $result ) ) {
        $GLOBALS['b2b_event_lifecycle_failure'] = array( 'message' => $result->get_error_message(), 'title' => $title );
        nocache_headers();
        return;
    }
    $url = add_query_arg( 'event_result', $creating ? 'created' : 'submitted', b2b_get_manage_event_url( $result ) );
    wp_safe_redirect( $url . ( $creating ? '#ee-event-details' : '' ), 303 );
    exit;
}
add_action( 'template_redirect', 'b2b_handle_event_lifecycle_request' );

/**
 * List the events the current user can manage.
 */
function b2b_manage_event_list() {

    $events = b2b_get_manageable_events( get_current_user_id() );
    $company_id = (int) get_user_meta( get_current_user_id(), 'company_id', true );
    $can_create_event = 'company' === get_post_type( $company_id );
    $lifecycle_failure = $GLOBALS['b2b_event_lifecycle_failure'] ?? array();

    $event_dates = array();
    foreach ( $events as $event ) {
        $event_dates[ $event->ID ] = get_post_meta( $event->ID, 'start_date', true );
    }

    return b2b_render_app(
        'events/manage-event-list',
        compact( 'events', 'event_dates', 'can_create_event', 'lifecycle_failure' ),
        'events'
    );
}

/**
 * Frontend event management.
 */
function b2b_manage_event_shortcode() {

    if ( ! is_user_logged_in() ) {
        return b2b_render_app( 'shared/message', array( 'message' => 'Please log in to manage an event.' ) );
    }

    $event_id = isset( $_GET['event_id'] )
        ? absint( $_GET['event_id'] )
        : 0;

    if ( ! $event_id ) {
        return b2b_manage_event_list();
    }

    if ( get_post_type( $event_id ) !== 'event' ) {
        return b2b_render_app( 'shared/message', array( 'message' => 'Invalid event.' ) );
    }

    if ( ! b2b_user_can_manage_event(
        get_current_user_id(),
        $event_id
    ) ) {
        return b2b_render_app( 'shared/message', array( 'message' => 'Access denied.' ) );
    }

    $event_types = get_terms(
        array(
            'taxonomy'   => 'event_type',
            'hide_empty' => false,
        )
    );

    if ( is_wp_error( $event_types ) ) {
        $event_types = array();
    }

    $message = '';
    $can_edit_event = b2b_user_can_edit_event( get_current_user_id(), $event_id );

    if (
    isset( $_SERVER['REQUEST_METHOD'] ) &&
    'POST' === $_SERVER['REQUEST_METHOD'] &&
    isset( $_POST['b2b_update_registration_status'] )
) {
    $registration_id = isset( $_POST['registration_id'] )
        ? absint( $_POST['registration_id'] )
        : 0;

    $new_status = isset( $_POST['new_status'] )
        && is_string( $_POST['new_status'] )
        ? sanitize_key( wp_unslash( $_POST['new_status'] ) )
        : '';

    $nonce = isset( $_POST['b2b_status_nonce'] )
        && is_string( $_POST['b2b_status_nonce'] )
        ? sanitize_text_field(
            wp_unslash( $_POST['b2b_status_nonce'] )
        )
        : '';

    if (
        ! wp_verify_nonce(
            $nonce,
            'b2b_update_registration_' . $registration_id
        )
    ) {
        $message = 'Security check failed.';
    } elseif (
        (int) get_post_meta( $registration_id, 'event_id', true )
        !== (int) $event_id
    ) {
        $message = 'Invalid registration for this event.';
    } else {
        $result = b2b_update_registration_status(
            $registration_id,
            $new_status
        );

        $message = is_wp_error( $result )
            ? $result->get_error_message()
            : 'Registration status updated successfully.';
    }
}

    /*
     * Check and process form submission.
     */
    if (
        isset( $_SERVER['REQUEST_METHOD'] ) &&
        'POST' === $_SERVER['REQUEST_METHOD'] &&
        isset( $_POST['b2b_save_event'] )
    ) {

        if ( ! $can_edit_event ) {
            return b2b_render_app( 'shared/message', array( 'message' => 'This event is pending approval and cannot be edited until an administrator returns it to draft.' ) );
        }

        $nonce = isset( $_POST['b2b_event_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['b2b_event_nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'b2b_save_event_' . $event_id ) ) {
            return b2b_render_app( 'shared/message', array( 'message' => 'Security check failed.' ) );
        }

        $event_title = isset( $_POST['event_title'] ) &&
            is_string( $_POST['event_title'] )
            ? sanitize_text_field( wp_unslash( $_POST['event_title'] ) )
            : '';

        $event_description = isset( $_POST['event_description'] )
            ? wp_kses_post(
                wp_unslash( $_POST['event_description'] )
            )
            : '';

        $event_type_id = isset( $_POST['event_type'] )
            ? absint( $_POST['event_type'] )
            : 0;

        $meta = array();

        foreach ( array( 'start_date', 'end_date', 'location', 'city', 'country' ) as $field ) {

            $meta[ $field ] = isset( $_POST[ $field ] )
                ? sanitize_text_field(
                    wp_unslash( $_POST[ $field ] )
                )
                : '';
        }

        foreach ( array( 'start_date', 'end_date' ) as $date_field ) {

            if (
                '' !== $meta[ $date_field ] &&
                ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $meta[ $date_field ] )
            ) {
                $meta[ $date_field ] = '';
            }
        }

        if ( '' === $event_title ) {

            $message = 'Event title is required.';

        } elseif (
            '' !== $meta['start_date'] &&
            '' !== $meta['end_date'] &&
            $meta['end_date'] < $meta['start_date']
        ) {

            $message = 'End date cannot be before the start date.';

        } else {

            $result = wp_update_post(
                array(
                    'ID'           => $event_id,
                    'post_title'   => $event_title,
                    'post_content' => $event_description,
                ),
                true
            );

            if ( is_wp_error( $result ) ) {

                $message = 'Could not save event.';

            } else {

                foreach ( $meta as $key => $value ) {

                    update_post_meta(
                        $event_id,
                        $key,
                        $value
                    );
                }

                if (
                    $event_type_id &&
                    term_exists( $event_type_id, 'event_type' )
                ) {

                    wp_set_object_terms(
                        $event_id,
                        array( $event_type_id ),
                        'event_type'
                    );

                } else {

                    wp_set_object_terms(
                        $event_id,
                        array(),
                        'event_type'
                    );
                }

                $message = 'Event updated successfully.';
            }
        }
    }

    // Load the event after a possible update.
    $data  = b2b_get_event_data( $event_id );
    $event = $data['event'];

    $current_event_types = wp_get_post_terms(
        $event_id,
        'event_type',
        array(
            'fields' => 'ids',
        )
    );

    $selected_event_type = ! empty( $current_event_types ) && ! is_wp_error( $current_event_types )
        ? $current_event_types[0]
        : 0;

    $field_messages = array(
        'added' => 'Registration field added successfully.',
        'updated' => 'Registration field updated successfully.',
        'deleted' => 'Registration field deleted. Historical answers are preserved.',
    );
    $field_result = isset( $_GET['field_result'] ) && is_string( $_GET['field_result'] ) ? $_GET['field_result'] : '';
    if ( isset( $field_messages[ $field_result ] ) ) { $message = $field_messages[ $field_result ]; }
    $field_failure = $GLOBALS['b2b_field_failure'] ?? array();
    if ( $field_failure ) { $message = $field_failure['message']; }
    $event_messages = array( 'created' => 'Draft created. Add the event details and registration fields, save your changes, then submit for approval.',
        'submitted' => 'Event submitted for approval. Editing is locked while EventExpo reviews it.' );
    $event_result = isset( $_GET['event_result'] ) && is_string( $_GET['event_result'] ) ? $_GET['event_result'] : '';
    if ( isset( $event_messages[ $event_result ] ) ) { $message = $event_messages[ $event_result ]; }
    $lifecycle_failure = $GLOBALS['b2b_event_lifecycle_failure'] ?? array();
    if ( $lifecycle_failure ) { $message = $lifecycle_failure['message']; }

    $registration_fields = b2b_get_event_registration_fields( $event_id );
    $registration_search = isset( $_GET['registration_search'] ) && is_string( $_GET['registration_search'] ) ? sanitize_text_field( wp_unslash( $_GET['registration_search'] ) ) : '';
    $registration_status = isset( $_GET['registration_status'] ) && is_string( $_GET['registration_status'] ) ? sanitize_key( wp_unslash( $_GET['registration_status'] ) ) : '';
    if ( ! in_array( $registration_status, array( 'approved', 'pending', 'rejected' ), true ) ) { $registration_status = ''; }
    $registration_page = isset( $_GET['registration_page'] ) ? max( 1, absint( $_GET['registration_page'] ) ) : 1;
    $registration_results = b2b_get_event_registrations( $event_id, $registration_page, $registration_search, $registration_status );
    $registration_url = add_query_arg( array( 'registration_search' => $registration_search, 'registration_status' => $registration_status, 'registration_page' => $registration_results['page'] ), b2b_get_manage_event_url( $event_id ) );
    $registration_rows = array();
    foreach ( $registration_results['posts'] as $registration ) {
        $registration_rows[] = array(
            'id'      => $registration->ID,
            'date'    => get_the_date( 'Y-m-d H:i', $registration ),
            'status'  => get_post_meta( $registration->ID, 'registration_status', true ),
            'answers' => b2b_get_registration_answer_rows( $registration->ID, $event_id ),
        );
    }

    return b2b_render_app(
        'events/manage-event',
        compact( 'event_id', 'event', 'data', 'message', 'event_types', 'selected_event_type', 'registration_fields', 'registration_rows', 'registration_results', 'registration_search', 'registration_status', 'registration_url', 'field_failure', 'can_edit_event' ),
        'events'
    );
}

add_shortcode( 'b2b_manage_event', 'b2b_manage_event_shortcode' );
