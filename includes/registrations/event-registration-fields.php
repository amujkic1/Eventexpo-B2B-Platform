<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get registration fields for an event.
 *
 * @param int $event_id Event ID.
 * @return array
 */
function b2b_get_event_registration_fields( $event_id ) {

    $fields = get_post_meta(
        $event_id,
        'registration_fields',
        true
    );

    if ( ! is_array( $fields ) ) {
        return array();
    }

    return $fields;
}

/**
 * Handle event registration before page rendering.
 */
function b2b_handle_event_registration_request() {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['b2b_submit_registration'] ) ) {
        return;
    }
    $event_id = isset( $_POST['b2b_registration_event_id'] ) ? absint( $_POST['b2b_registration_event_id'] ) : 0;
    if ( ! $event_id ) { return; }

    $fields = b2b_get_event_registration_fields( $event_id );
    $answers = array();
    $errors = array();
    $message = '';
    $nonce = isset( $_POST['b2b_registration_submit_nonce'] ) && is_string( $_POST['b2b_registration_submit_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['b2b_registration_submit_nonce'] ) ) : '';

    if ( ! wp_verify_nonce( $nonce, 'b2b_submit_registration_' . $event_id ) ) {
        $message = 'Your session expired. Please submit the form again.';
    } elseif ( 'event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) || ! $fields ) {
        $message = 'Registration is not available.';
    } else {
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) || empty( $field['name'] ) || ! in_array( $field['type'] ?? '', array( 'text', 'email', 'number' ), true ) ) {
                continue;
            }
            $name = sanitize_key( $field['name'] );
            $value = isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
            $answers[ $name ] = $value;
            if ( ! empty( $field['required'] ) && '' === $value ) {
                $errors[ $name ] = 'Please complete this required field.';
            } elseif ( '' !== $value && 'email' === $field['type'] && ! is_email( $value ) ) {
                $errors[ $name ] = 'Please enter a valid email address.';
            } elseif ( '' !== $value && 'number' === $field['type'] && ! is_numeric( $value ) ) {
                $errors[ $name ] = 'Please enter a valid number.';
            }
        }
        if ( $errors ) {
            $message = 'Please correct the highlighted fields.';
        } else {
            $result = b2b_save_event_registration( $event_id, $answers );
            if ( is_wp_error( $result ) ) {
                $message = 'Could not save registration. Your answers are retained; please try again.';
            } else {
                wp_safe_redirect( add_query_arg( 'registration_success', '1', get_permalink( $event_id ) ), 303 );
                exit;
            }
        }
    }
    // Keep failures in this request: answers never enter a URL or shared cache.
    $GLOBALS['b2b_registration_failure'] = compact( 'event_id', 'message', 'answers', 'errors' );
    nocache_headers();
}

add_action(
    'template_redirect',
    'b2b_handle_event_registration_request'
);

/**
 * Render the registration form for an event.
 */
function b2b_render_event_registration_form( $event_id ) {

    $fields = b2b_get_event_registration_fields( $event_id );
    $answers = array();
    $errors = array();
    $failure = $GLOBALS['b2b_registration_failure'] ?? array();
    $has_error = $failure && (int) $failure['event_id'] === (int) $event_id;

    $message = '';

    if (
        isset( $_GET['registration_success'] ) &&
        '1' === $_GET['registration_success']
    ) {
        $message = 'Registration submitted successfully.';
    }

    if ( $has_error ) {
        $message = $failure['message'];
        $answers = $failure['answers'];
        $errors = $failure['errors'];
    }

    if ( empty( $fields ) ) {
        return b2b_render_template( 'shared/message', array( 'message' => 'Registration is not available yet.' ) );
    }

    return b2b_render_template(
        'registrations/event-registration-form',
        compact( 'event_id', 'fields', 'message', 'answers', 'errors', 'has_error' )
    );
}
