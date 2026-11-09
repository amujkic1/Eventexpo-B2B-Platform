<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * List the events the current user can manage.
 */
function b2b_manage_event_list() {

    $events = b2b_get_manageable_events( get_current_user_id() );

    $event_dates = array();
    foreach ( $events as $event ) {
        $event_dates[ $event->ID ] = get_post_meta( $event->ID, 'start_date', true );
    }

    return b2b_render_app(
        'manage-event-list',
        compact( 'events', 'event_dates' ),
        'events'
    );
}

/**
 * Frontend event management.
 */
function b2b_manage_event_shortcode() {

    if ( ! is_user_logged_in() ) {
        return b2b_render_app( 'message', array( 'message' => 'Please log in to manage an event.' ) );
    }

    $event_id = isset( $_GET['event_id'] )
        ? absint( $_GET['event_id'] )
        : 0;

    if ( ! $event_id ) {
        return b2b_manage_event_list();
    }

    if ( get_post_type( $event_id ) !== 'event' ) {
        return b2b_render_app( 'message', array( 'message' => 'Invalid event.' ) );
    }

    if ( ! b2b_user_can_manage_event(
        get_current_user_id(),
        $event_id
    ) ) {
        return b2b_render_app( 'message', array( 'message' => 'Access denied.' ) );
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

        $nonce = isset( $_POST['b2b_event_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['b2b_event_nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'b2b_save_event_' . $event_id ) ) {
            return b2b_render_app( 'message', array( 'message' => 'Security check failed.' ) );
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

    if (
        'POST' === $_SERVER['REQUEST_METHOD'] &&
        isset( $_POST['b2b_add_registration_field'] )
    ) {
        $nonce = isset( $_POST['b2b_registration_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['b2b_registration_nonce'] ) )
            : '';

        if (
            ! wp_verify_nonce(
                $nonce,
                'b2b_add_registration_field_' . $event_id
            )
        ) {
            return b2b_render_app( 'message', array( 'message' => 'Security check failed.' ) );
        }

        $label = isset( $_POST['field_label'] ) &&
            is_string( $_POST['field_label'] )
            ? sanitize_text_field( wp_unslash( $_POST['field_label'] ) )
            : '';

        $type = isset( $_POST['field_type'] ) &&
            is_string( $_POST['field_type'] )
            ? sanitize_key( wp_unslash( $_POST['field_type'] ) )
            : '';

        $allowed_types = array( 'text', 'email', 'number' );

        if ( '' === $label || ! in_array( $type, $allowed_types, true ) ) {
            return b2b_render_app( 'message', array( 'message' => 'Invalid registration field.' ) );
        }

        $fields = b2b_get_event_registration_fields( $event_id );

        $field_name = 'field_' . str_replace(
            '-',
            '',
            wp_generate_uuid4()
        );

        $fields[] = array(
            'name'     => $field_name,
            'label'    => $label,
            'type'     => $type,
            'required' => ! empty( $_POST['field_required'] ),
        );

        update_post_meta(
            $event_id,
            'registration_fields',
            $fields
        );

        $message = 'Registration field added successfully.';
    }

    $registration_fields = b2b_get_event_registration_fields( $event_id );
    $registration_rows = array();
    foreach ( b2b_get_event_registrations( $event_id ) as $registration ) {
        $registration_rows[] = array(
            'id'      => $registration->ID,
            'date'    => get_the_date( 'Y-m-d H:i', $registration ),
            'status'  => get_post_meta( $registration->ID, 'registration_status', true ),
            'answers' => b2b_registration_answer_rows(
                $registration_fields,
                get_post_meta( $registration->ID, 'registration_answers', true )
            ),
        );
    }

    return b2b_render_app(
        'manage-event',
        compact( 'event_id', 'event', 'data', 'message', 'event_types', 'selected_event_type', 'registration_fields', 'registration_rows' ),
        'events'
    );
}

add_shortcode( 'b2b_manage_event', 'b2b_manage_event_shortcode' );
