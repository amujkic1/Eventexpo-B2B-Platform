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

/** Mutate one stable field identity; never rewrite submitted answers. */
function b2b_mutate_registration_field( $event_id, $action, $name, $values = array() ) {
    if ( 'event' !== get_post_type( $event_id ) || ! is_user_logged_in()
        || ! b2b_user_can_manage_event( get_current_user_id(), $event_id ) ) {
        return new WP_Error( 'forbidden', 'You are not authorized to manage these fields.' );
    }
    if ( ! in_array( $action, array( 'add', 'edit', 'delete' ), true ) ) {
        return new WP_Error( 'invalid_action', 'Invalid field action.' );
    }
    $fields = b2b_get_event_registration_fields( $event_id );
    $index = null;
    if ( 'add' !== $action ) {
        if ( ! is_string( $name ) || '' === $name || sanitize_key( $name ) !== $name ) {
            return new WP_Error( 'invalid_field', 'Invalid field identifier.' );
        }
        foreach ( $fields as $key => $field ) {
            if ( isset( $field['name'] ) && $field['name'] === $name ) {
                if ( null !== $index ) { return new WP_Error( 'invalid_field', 'Duplicate field identifier. No changes saved.' ); }
                $index = $key;
            }
        }
        if ( null === $index ) { return new WP_Error( 'invalid_field', 'This field no longer exists. Reload the form and try again.' ); }
    }
    if ( 'delete' === $action ) {
        $archive = get_post_meta( $event_id, 'registration_fields_archive', true );
        $archive = is_array( $archive ) ? $archive : array();
        $archive[ $name ] = $fields[ $index ];
        // Save the definition first, so a failed archive write cannot hide history.
        if ( ! update_post_meta( $event_id, 'registration_fields_archive', $archive )
            && get_post_meta( $event_id, 'registration_fields_archive', true ) !== $archive ) {
            return new WP_Error( 'save_failed', 'Could not preserve field history. Nothing was deleted.' );
        }
        unset( $fields[ $index ] );
        $fields = array_values( $fields );
    } else {
        if ( ! is_string( $values['label'] ?? null ) || ! is_string( $values['type'] ?? null )
            || ! in_array( $values['type'], array( 'text', 'email', 'number' ), true )
            || ! is_bool( $values['required'] ?? null ) ) {
            return new WP_Error( 'invalid_field', 'Enter a label, a supported field type, and a valid required setting.' );
        }
        $label = sanitize_text_field( $values['label'] );
        if ( '' === $label ) { return new WP_Error( 'invalid_label', 'Field label is required.' ); }
        if ( 'add' === $action ) {
            $name = 'field_' . str_replace( '-', '', wp_generate_uuid4() );
            $fields[] = array( 'name' => $name, 'label' => $label, 'type' => $values['type'], 'required' => $values['required'] );
        } else {
            $fields[ $index ] = array_merge( $fields[ $index ], array( 'label' => $label, 'type' => $values['type'], 'required' => $values['required'] ) );
        }
    }
    if ( ! update_post_meta( $event_id, 'registration_fields', $fields )
        && get_post_meta( $event_id, 'registration_fields', true ) !== $fields ) {
        return new WP_Error( 'save_failed', 'Could not save registration fields. Please try again.' );
    }
    return true;
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
    if ( ! $event_id || ! is_user_logged_in() || ! b2b_user_can_manage_event( get_current_user_id(), $event_id ) ) {
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

/** Snapshot first, then active and archived definitions, for legacy submissions. */
function b2b_get_registration_answer_rows( $registration_id, $event_id ) {
    $snapshot = get_post_meta( $registration_id, 'registration_field_snapshot', true );
    $archive = get_post_meta( $event_id, 'registration_fields_archive', true );
    $fields = array();
    foreach ( array( is_array( $snapshot ) ? $snapshot : array(), b2b_get_event_registration_fields( $event_id ), is_array( $archive ) ? $archive : array() ) as $definitions ) {
        foreach ( $definitions as $field ) {
            if ( is_array( $field ) && isset( $field['name'] ) && ! isset( $fields[ $field['name'] ] ) ) { $fields[ $field['name'] ] = $field; }
        }
    }
    return b2b_registration_answer_rows( array_values( $fields ), get_post_meta( $registration_id, 'registration_answers', true ) );
}
