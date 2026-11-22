<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Handle the existing status POST from the event workspace. */
function b2b_handle_registration_status_request( $event_id ) {
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

    return $message;
}

/**
 * Handle event registration before page rendering.
 */
function b2b_handle_event_registration_request() {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['b2b_submit_registration'] ) ) {
        return;
    }
    $event_id = b2b_registration_event_id( $_POST['b2b_registration_event_id'] ?? '' );
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
            // Validate normalized identity; retain the original answer for display.
            $check_value = ! empty( $field['system'] ) ? b2b_normalize_attendee_email( $value ) : $value;
            $answers[ $name ] = $value;
            if ( ! empty( $field['required'] ) && '' === $value ) {
                $errors[ $name ] = 'Please complete this required field.';
            } elseif ( '' !== $value && 'email' === $field['type'] && ! is_email( $check_value ) ) {
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
                $message = 'duplicate_registration' === $result->get_error_code()
                    ? $result->get_error_message() : ( 'invalid_email' === $result->get_error_code()
                    ? 'Please enter a valid attendee email address.' : 'Could not save registration. Your answers are retained; please try again.' );
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

    if ( 'event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
        return b2b_render_template( 'shared/message', array( 'message' => 'Registration is available only for published events.' ) );
    }

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


/** Process builder POSTs before output, then redirect on success (PRG). */
function b2b_handle_registration_field_request() {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['b2b_field_action'] ) ) { return; }
    $event_value = $_GET['event_id'] ?? '';
    $event_id = is_string( $event_value ) && preg_match( '/^[1-9][0-9]*$/D', $event_value ) ? (int) $event_value : 0;
    $action = isset( $_POST['b2b_field_action'] ) && is_string( $_POST['b2b_field_action'] ) ? wp_unslash( $_POST['b2b_field_action'] ) : '';
    $name = isset( $_POST['field_name'] ) && is_string( $_POST['field_name'] ) ? wp_unslash( $_POST['field_name'] ) : '';
    $nonce = isset( $_POST['b2b_field_nonce'] ) && is_string( $_POST['b2b_field_nonce'] ) ? wp_unslash( $_POST['b2b_field_nonce'] ) : '';
    $values = array(
        'label' => isset( $_POST['field_label'] ) && is_string( $_POST['field_label'] ) ? sanitize_text_field( wp_unslash( $_POST['field_label'] ) ) : '',
        'type' => isset( $_POST['field_type'] ) && is_string( $_POST['field_type'] ) ? wp_unslash( $_POST['field_type'] ) : '',
        'required' => isset( $_POST['field_required'] ),
    );
    $error = '';
    if ( ! $event_id || ! is_user_logged_in() || ! b2b_user_can_edit_event( get_current_user_id(), $event_id ) ) {
        $error = 'You are not authorized to manage these fields.';
    } elseif ( ! wp_verify_nonce( $nonce, 'b2b_registration_field_' . $event_id . '_' . $action . '_' . $name ) ) {
        $error = 'Security check failed. Reload the page and try again.';
    } elseif ( isset( $_POST['field_required'] ) && ( ! is_string( $_POST['field_required'] ) || '1' !== $_POST['field_required'] ) ) {
        $error = 'Invalid required setting.';
    } elseif ( 'delete' === $action && ( $_POST['confirm_delete'] ?? null ) !== '1' ) {
        $error = 'Confirm deletion before deleting this field.';
    } elseif ( isset( $_POST['field_name'] ) && ! is_string( $_POST['field_name'] ) ) {
        $error = 'Invalid field identifier.';
    } else {
        $result = b2b_mutate_registration_field( $event_id, $action, $name, $values );
        if ( is_wp_error( $result ) ) { $error = $result->get_error_message(); }
    }
    if ( $error ) {
        $GLOBALS['b2b_field_failure'] = array( 'message' => $error, 'name' => $name, 'action' => $action, 'values' => $values );
        nocache_headers();
        return;
    }
    $results = array( 'add' => 'added', 'edit' => 'updated', 'delete' => 'deleted' );
    wp_safe_redirect( add_query_arg( 'field_result', $results[ $action ], b2b_get_manage_event_url( $event_id ) ) . '#ee-registration-fields', 303 );
    exit;
}
add_action( 'template_redirect', 'b2b_handle_registration_field_request' );
