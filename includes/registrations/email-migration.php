<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Conservative legacy inference: reserve every plausible typed email, report ambiguity. */
function b2b_legacy_registration_emails( $registration_id, $event_id ) {
    $answers = get_post_meta( $registration_id, 'registration_answers', true );
    $snapshot = get_post_meta( $registration_id, 'registration_field_snapshot', true );
    $raw = get_post_meta( $event_id, 'registration_fields', true );
    $archive = get_post_meta( $event_id, 'registration_fields_archive', true );
    $keys = array( 'email', 'ee_attendee_email', b2b_attendee_email_field_name( $event_id ) );
    foreach ( array( $snapshot, $raw, $archive ) as $definitions ) {
        foreach ( is_array( $definitions ) ? $definitions : array() as $field ) {
            if ( is_array( $field ) && 'email' === ( $field['type'] ?? '' ) && is_string( $field['name'] ?? null ) ) { $keys[] = $field['name']; }
        }
    }
    $emails = array();
    $stored = b2b_normalize_attendee_email( get_post_meta( $registration_id, '_b2b_attendee_email', true ) );
    if ( $stored ) { $emails[ $stored ] = true; }
    foreach ( array_unique( $keys ) as $key ) {
        $email = b2b_normalize_attendee_email( is_array( $answers ) ? ( $answers[ $key ] ?? '' ) : '' );
        if ( $email ) { $emails[ $email ] = true; }
    }
    return array_keys( $emails );
}

/** Explicit CLI migration, resumable and safe to rerun. No answer/status/date updates. */
function b2b_migrate_registration_emails() {
    global $wpdb;
    $lock = 'ee-email-migrate-' . md5( $wpdb->prefix );
    $suppressed = $wpdb->suppress_errors( true );
    $acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );
    if ( '1' !== (string) $acquired ) {
        $wpdb->suppress_errors( $suppressed );
        return new WP_Error( '0' === (string) $acquired ? 'migration_busy' : 'lock_unavailable', '0' === (string) $acquired
            ? 'Another migration or registration write is running. Wait briefly and retry.'
            : 'The database could not acquire the migration lock. Ask your host whether MySQL GET_LOCK and RELEASE_LOCK are supported for this database user.' );
    }
    $reconnect_retries = $wpdb->reconnect_retries;
    // wpdb normally retries a lost query on a new, autocommit connection.
    // That would escape our transaction. Let connection loss fail instead.
    $wpdb->reconnect_retries = 0;
    try {
        // Share a DB lock with writers, so a rerun cannot race new submissions.
        update_option( 'b2b_email_migration_version', 'running', false );
        $installed = b2b_install_email_storage();
        if ( is_wp_error( $installed ) ) { return $installed; }
        $counts = array( 'processed' => 0, 'missing_email' => 0, 'ambiguous_email' => 0, 'duplicate_email' => 0, 'invalid_event' => 0 );
        $cursor = 0;
        do {
            $ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = 'event_registration' AND ID > %d ORDER BY ID LIMIT 200", $cursor ) );
            if ( $wpdb->last_error ) { throw new RuntimeException( 'read' ); }
            foreach ( $ids as $id ) {
                $cursor = (int) $id;
                $event_id = b2b_registration_event_id( get_post_meta( $id, 'event_id', true ) );
                $issues = array();
                if ( 'event' !== get_post_type( $event_id ) ) {
                    $issues[] = 'invalid_event';
                    $emails = array();
                } else {
                    $emails = b2b_legacy_registration_emails( $id, $event_id );
                    if ( ! $emails ) { $issues[] = 'missing_email'; }
                    if ( count( $emails ) > 1 ) { $issues[] = 'ambiguous_email'; }
                }
                if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'transaction' ); }
                foreach ( $emails as $email ) {
                    $hash = hash( 'sha256', $email );
                    $table = b2b_email_table();
                    if ( false === $wpdb->query( $wpdb->prepare( "INSERT INTO $table (event_id,email_hash,registration_id) VALUES (%d,%s,%d) ON DUPLICATE KEY UPDATE registration_id = registration_id", $event_id, $hash, $id ) ) ) { throw new RuntimeException( 'claim' ); }
                    $owner = $wpdb->get_var( $wpdb->prepare( "SELECT registration_id FROM $table WHERE event_id = %d AND email_hash = %s", $event_id, $hash ) );
                    if ( (int) $owner !== (int) $id ) { $issues[] = 'duplicate_email'; }
                }
                if ( 1 === count( $emails ) ) {
                    update_post_meta( $id, '_b2b_attendee_email', $emails[0] );
                    if ( $wpdb->last_error || get_post_meta( $id, '_b2b_attendee_email', true ) !== $emails[0] ) { throw new RuntimeException( 'metadata' ); }
                }
                if ( $issues ) {
                    $review = array_values( array_unique( $issues ) );
                    update_post_meta( $id, '_b2b_email_review', $review );
                    if ( get_post_meta( $id, '_b2b_email_review', true ) !== $review ) { throw new RuntimeException( 'review' ); }
                } else {
                    delete_post_meta( $id, '_b2b_email_review' );
                    if ( get_post_meta( $id, '_b2b_email_review', true ) ) { throw new RuntimeException( 'review' ); }
                }
                if ( $wpdb->last_error || false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'commit' ); }
                clean_post_cache( $id );
                $counts['processed']++;
                foreach ( array_unique( $issues ) as $issue ) { $counts[ $issue ]++; }
            }
        } while ( count( $ids ) === 200 );
        update_option( 'b2b_email_migration_version', '1', false );
        if ( '1' !== get_option( 'b2b_email_migration_version' ) ) { throw new RuntimeException( 'version' ); }
        return $counts;
    } catch ( Throwable $error ) {
        $wpdb->query( 'ROLLBACK' );
        if ( isset( $id ) ) { clean_post_cache( $id ); }
        $messages = array(
            'read' => 'Could not read the registrations list. Check database read permissions.',
            'transaction' => 'Could not start a database transaction. Ask your host to check transaction support.',
            'claim' => 'Could not write to the email uniqueness index. Check its schema and INSERT/UPDATE permissions.',
            'metadata' => 'Could not save normalized email metadata. Check postmeta write permissions and metadata-filter plugins.',
            'review' => 'Could not save migration review flags. Check postmeta write permissions and metadata-filter plugins.',
            'commit' => 'Could not commit a migration transaction. Check database connectivity and transaction support.',
            'version' => 'Could not save migration completion. Check options-table write permissions.',
        );
        return new WP_Error( 'migration_failed', $messages[ $error->getMessage() ] ?? 'Migration stopped unexpectedly. Ask your host to inspect the PHP error log; do not share logs containing attendee data.' );
    } finally {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        $wpdb->reconnect_retries = $reconnect_retries;
        $wpdb->suppress_errors( $suppressed );
    }
}


/** Store only our safe error message, never SQL, email addresses or exception text. */
function b2b_record_email_migration_result( $result ) {
    if ( is_wp_error( $result ) ) {
        update_option( 'b2b_email_migration_error', array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ), false );
    } else {
        delete_option( 'b2b_email_migration_error' );
    }
}
