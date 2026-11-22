<?php
/** Attendee identity, transactional uniqueness, and non-destructive migration. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function b2b_registration_event_id( $value ) {
    if ( ! is_int( $value ) && ! is_string( $value ) ) { return 0; }
    if ( ! preg_match( '/^[1-9][0-9]*$/D', (string) $value ) ) { return 0; }
    $id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
    return false === $id ? 0 : $id;
}

function b2b_normalize_attendee_email( $value ) {
    if ( ! is_string( $value ) ) { return ''; }
    $email = strtolower( trim( $value ) );
    return is_email( $email ) ? $email : '';
}

/** Adopt an existing email identity once; never infer identity from its label. */
function b2b_attendee_email_field_name( $event_id ) {
    $name = get_post_meta( $event_id, '_b2b_attendee_email_field', true );
    if ( is_string( $name ) && '' !== $name ) { return $name; }
    global $wpdb;
    $suppressed = $wpdb->suppress_errors( true );
    $lock = 'ee-email-field-' . md5( $wpdb->prefix . ':' . $event_id );
    if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
        $wpdb->suppress_errors( $suppressed );
        return '';
    }
    try {
        // Bypass metadata cache after waiting for another connection.
        wp_cache_delete( $event_id, 'post_meta' );
        $name = get_post_meta( $event_id, '_b2b_attendee_email_field', true );
        if ( is_string( $name ) && '' !== $name ) { return $name; }
        $name = '';
        $raw = get_post_meta( $event_id, 'registration_fields', true );
        $names = array();
        foreach ( is_array( $raw ) ? $raw : array() as $field ) {
            if ( ! is_array( $field ) || ! is_string( $field['name'] ?? null ) ) { continue; }
            $names[] = $field['name'];
            if ( 'email' === ( $field['type'] ?? '' ) && sanitize_key( $field['name'] ) === $field['name'] ) {
                $name = $field['name'];
                break;
            }
        }
        if ( ! $name ) {
            $name = 'ee_attendee_email';
            // Do not repurpose an unrelated custom field with the reserved name.
            while ( in_array( $name, $names, true ) ) { $name .= '_system'; }
        }
        if ( ! update_post_meta( $event_id, '_b2b_attendee_email_field', $name ) ) { return ''; }
        return $name;
    } finally {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        $wpdb->suppress_errors( $suppressed );
    }
}

function b2b_email_table() {
    global $wpdb;
    return $wpdb->prefix . 'b2b_registration_emails';
}

/** Read-only diagnostics; SHOW fallback supports restricted shared-host accounts. */
function b2b_email_storage_diagnostics() {
    global $wpdb;
    $suppressed = $wpdb->suppress_errors( true );
    $results = array();
    try {
        foreach ( array( 'WordPress posts' => $wpdb->posts, 'WordPress metadata' => $wpdb->postmeta, 'Email uniqueness index' => b2b_email_table() ) as $role => $table ) {
            $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
            if ( ! $engine ) {
                $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
                $engine = is_array( $status ) ? ( $status['Engine'] ?? '' ) : '';
            }
            $results[ $role ] = is_string( $engine ) && preg_match( '/^[a-zA-Z0-9_]+$/D', $engine ) ? $engine : 'Unavailable';
        }
        return $results;
    } finally {
        $wpdb->suppress_errors( $suppressed );
    }
}

function b2b_email_storage_check() {
    foreach ( b2b_email_storage_diagnostics() as $role => $engine ) {
        if ( 'Unavailable' === $engine ) {
            return new WP_Error( 'table_unavailable', $role . ' could not be found or inspected. Ask your host to check table access and CREATE/ALTER permissions for the WordPress database user.' );
        }
        if ( 'innodb' !== strtolower( $engine ) ) {
            return new WP_Error( 'non_transactional_table', $role . ' uses ' . $engine . ', but InnoDB is required. Ask your host to review and convert the affected table after confirming your backup. EventExpo does not convert it automatically.' );
        }
    }
    return true;
}

/** Fail closed on non-transactional engines; never convert production tables. */
function b2b_email_storage_ready() {
    return '1' === get_option( 'b2b_email_schema_version' ) && true === b2b_email_storage_check();
}

function b2b_install_email_storage() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = b2b_email_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE $table (
        event_id bigint(20) unsigned NOT NULL,
        email_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        registration_id bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (event_id,email_hash),
        KEY registration_id (registration_id)
    ) ENGINE=InnoDB $charset;" );
    update_option( 'b2b_email_schema_version', '1', false );
    $check = b2b_email_storage_check();
    if ( is_wp_error( $check ) ) { return $check; }
    if ( '1' !== get_option( 'b2b_email_schema_version' ) ) { return new WP_Error( 'schema_version_failed', 'The database could not save the schema version. Check WordPress options-table write permissions.' ); }
    return true;
}

/** Unique claim + registration and metadata use one database transaction. */
function b2b_save_unique_registration( $event_id, array $answers ) {
    global $wpdb;
    $event_id = b2b_registration_event_id( $event_id );
    if ( ! $event_id || 'event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
        return new WP_Error( 'invalid_event', 'Registration is not available.' );
    }
    $name = b2b_attendee_email_field_name( $event_id );
    $email = $name ? b2b_normalize_attendee_email( $answers[ $name ] ?? null ) : '';
    if ( ! $email ) { return new WP_Error( 'invalid_email', 'Please enter a valid attendee email address.' ); }
    if ( '1' !== get_option( 'b2b_email_migration_version' ) || ! b2b_email_storage_ready() ) {
        return new WP_Error( 'storage_unavailable', 'Registration is temporarily unavailable. Please try again later.' );
    }
    $lock = 'ee-email-migrate-' . md5( $wpdb->prefix );
    $suppressed = $wpdb->suppress_errors( true );
    if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
        $wpdb->suppress_errors( $suppressed );
        return new WP_Error( 'storage_busy', 'Registration is temporarily busy. Please try again.' );
    }
    $reconnect_retries = $wpdb->reconnect_retries;
    $wpdb->reconnect_retries = 0;
    $registration_id = 0;
    try {
        $version = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", 'b2b_email_migration_version' ) );
        if ( '1' !== $version ) { throw new RuntimeException( 'migration' ); }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'transaction' ); }
        $table = b2b_email_table();
        $hash = hash( 'sha256', $email );
        $claim = $wpdb->query( $wpdb->prepare( "INSERT INTO $table (event_id,email_hash,registration_id) VALUES (%d,%s,0)", $event_id, $hash ) );
        if ( false === $claim ) {
            $owner = $wpdb->get_var( $wpdb->prepare( "SELECT registration_id FROM $table WHERE event_id = %d AND email_hash = %s", $event_id, $hash ) );
            if ( null !== $owner ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'duplicate_registration', 'You have already registered for this event using this email address.' );
            }
            throw new RuntimeException( 'claim' );
        }
        $registration_id = wp_insert_post( array( 'post_type' => 'event_registration', 'post_status' => 'private', 'post_title' => 'Registration - Event ' . $event_id ), true );
        if ( is_wp_error( $registration_id ) || ! $registration_id ) { $registration_id = 0; throw new RuntimeException( 'insert' ); }
        foreach ( array( 'event_id' => $event_id, 'registration_answers' => $answers, 'registration_status' => 'pending',
            'registration_field_snapshot' => b2b_get_event_registration_fields( $event_id ), '_b2b_attendee_email' => $email ) as $key => $value ) {
            if ( ! add_post_meta( $registration_id, $key, wp_slash( $value ), true ) ) { throw new RuntimeException( 'metadata' ); }
        }
        if ( 1 !== $wpdb->query( $wpdb->prepare( "UPDATE $table SET registration_id = %d WHERE event_id = %d AND email_hash = %s", $registration_id, $event_id, $hash ) ) ) { throw new RuntimeException( 'claim_update' ); }
        if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'commit' ); }
        clean_post_cache( $registration_id );
        return $registration_id;
    } catch ( Throwable $error ) {
        $wpdb->query( 'ROLLBACK' );
        if ( $registration_id ) { clean_post_cache( $registration_id ); }
        return new WP_Error( 'save_failed', 'Could not save registration. Your answers are retained; please try again.' );
    } finally {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        $wpdb->reconnect_retries = $reconnect_retries;
        $wpdb->suppress_errors( $suppressed );
    }
}
