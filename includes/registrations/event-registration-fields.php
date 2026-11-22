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

    $fields = get_post_meta( $event_id, 'registration_fields', true );
    $fields = is_array( $fields ) ? $fields : array();
    if ( 'event' !== get_post_type( $event_id ) ) { return $fields; }
    $identity = b2b_attendee_email_field_name( $event_id );
    if ( ! $identity ) { return array(); } // Fail closed if the stable mapping cannot be saved.
    $found = false;
    foreach ( $fields as &$field ) {
        if ( is_array( $field ) && ( $field['name'] ?? '' ) === $identity ) {
            $field['type'] = 'email';
            $field['required'] = true;
            $field['system'] = true;
            $found = true;
        }
    }
    unset( $field );
    if ( ! $found ) {
        array_unshift( $fields, array( 'name' => $identity, 'label' => 'Attendee email', 'type' => 'email', 'required' => true, 'system' => true ) );
    }
    return $fields;
}

/** Mutate one stable field identity; never rewrite submitted answers. */
function b2b_mutate_registration_field( $event_id, $action, $name, $values = array() ) {
    if ( 'event' !== get_post_type( $event_id ) || ! is_user_logged_in()
        || ! b2b_user_can_edit_event( get_current_user_id(), $event_id ) ) {
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
    if ( null !== $index && ! empty( $fields[ $index ]['system'] )
        && ( 'delete' === $action || 'email' !== ( $values['type'] ?? '' ) || true !== ( $values['required'] ?? false ) ) ) {
        return new WP_Error( 'system_field', 'The attendee email field must remain required and cannot be deleted or changed to another type.' );
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

/**
 * Prepare registration answers for both admin and frontend templates.
 */
function b2b_registration_answer_rows( array $fields, $answers ) {

    $rows = array();

    if ( ! is_array( $answers ) ) {
        return $rows;
    }

    $seen = array();
    foreach ( $fields as $field ) {
        if ( ! is_array( $field ) || empty( $field['name'] ) ) {
            continue;
        }

        $name = sanitize_key( $field['name'] );
        if ( ! array_key_exists( $name, $answers ) ) { continue; }
        $seen[ $name ] = true;
        $rows[] = array(
            'label' => isset( $field['label'] ) ? $field['label'] : $name,
            'value' => isset( $answers[ $name ] ) && is_scalar( $answers[ $name ] )
                ? (string) $answers[ $name ]
                : '',
        );
    }

    // Legacy or externally removed field definitions must not hide stored answers.
    foreach ( $answers as $name => $value ) {
        if ( ! isset( $seen[ $name ] ) && is_scalar( $value ) ) {
            $rows[] = array( 'label' => (string) $name . ' (historical field)', 'value' => (string) $value );
        }
    }

    return $rows;
}
