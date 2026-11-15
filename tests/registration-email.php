<?php
/** Execute through local WP-CLI (see docs/registration-email.md). */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Local tests only.' ); }
$check = static function ( $value, $label ) { if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $label ); } WP_CLI::log( 'PASS: ' . $label ); };
$events = array(); $ids = array();
$original_user = get_current_user_id();
$create_event = static function ( $fields = array() ) use ( &$events ) {
    $id = wp_insert_post( array( 'post_type' => 'event', 'post_status' => 'publish', 'post_title' => 'Email regression fixture' ) );
    update_post_meta( $id, 'registration_fields', $fields );
    $organizer = get_user_by( 'login', 'organizer' );
    update_post_meta( $id, 'organizer_company_id', get_user_meta( $organizer->ID, 'company_id', true ) );
    $events[] = $id;
    return $id;
};
$save = static function ( $event, $email ) use ( &$ids ) {
    $result = b2b_save_event_registration( $event, array( b2b_attendee_email_field_name( $event ) => $email ) );
    if ( ! is_wp_error( $result ) ) { $ids[] = $result; }
    return $result;
};
try {
    $event = $create_event( array( array( 'name' => 'legacy_contact', 'label' => 'Renamable label', 'type' => 'email', 'required' => false ) ) );
    $other = $create_event();
    $fields = b2b_get_event_registration_fields( $event );
    $check( count( $fields ) === 1 && $fields[0]['system'] && $fields[0]['required'] && 'legacy_contact' === $fields[0]['name'], 'Existing email adopted once and made required' );
    $check( count( b2b_get_event_registration_fields( $other ) ) === 1, 'Email-less forms gain one stable system email' );
    $name = b2b_attendee_email_field_name( $event );
    foreach ( array( array( $event ), (string) $event . 'tampered', '0', '99999999999999999999999999' ) as $bad_event ) {
        $check( is_wp_error( b2b_save_event_registration( $bad_event, array( $name => 'invalid-event@example.com' ) ) ), 'Malformed event ID rejected' );
    }
    wp_update_post( array( 'ID' => $other, 'post_status' => 'draft' ) );
    $check( is_wp_error( $save( $other, 'draft@example.com' ) ), 'Unpublished events cannot accept registrations' );
    wp_update_post( array( 'ID' => $other, 'post_status' => 'publish' ) );
    wp_set_current_user( 0 );
    $id = $save( $event, '  Person@Example.com  ' );
    $check( ! is_wp_error( $id ), 'Anonymous first registration succeeds' );
    $check( 'person@example.com' === get_post_meta( $id, '_b2b_attendee_email', true ), 'Email is trimmed and lowercased in dedicated metadata' );
    $check( '  Person@Example.com  ' === get_post_meta( $id, 'registration_answers', true )[ $name ], 'Original answers remain intact' );
    foreach ( array( 'pending', 'approved', 'rejected' ) as $status ) {
        update_post_meta( $id, 'registration_status', $status );
        $duplicate = $save( $event, 'PERSON@EXAMPLE.COM' );
        $check( is_wp_error( $duplicate ) && 'duplicate_registration' === $duplicate->get_error_code(), $status . ' blocks case-insensitive duplicates' );
    }
    $check( ! is_wp_error( $save( $event, 'another@example.com' ) ), 'Different emails on the same event succeed' );
    $check( ! is_wp_error( $save( $other, 'person@example.com' ) ), 'Same email on different event succeeds' );
    $check( ! is_wp_error( $save( $event, 'person+tag@example.com' ) ) && ! is_wp_error( $save( $event, 'per.son@example.com' ) ), 'Plus addressing and dots are preserved' );
    foreach ( array( '', 'not-email', array( 'person@example.com' ) ) as $bad ) {
        $check( is_wp_error( $save( $event, $bad ) ), 'Missing, invalid and malformed identity rejected' );
    }
    $check( is_wp_error( b2b_save_event_registration( $event, array( 'unrelated_email' => 'new@example.com' ) ) ), 'Unrelated email cannot substitute for designated identity' );
    $organizer = get_user_by( 'login', 'organizer' ); wp_set_current_user( $organizer->ID );
    $check( ! is_wp_error( $save( $event, 'signedin@example.com' ) ), 'Authenticated registration succeeds' );
    $check( true === b2b_mutate_registration_field( $event, 'edit', $name, array( 'label' => 'New label', 'type' => 'email', 'required' => true ) ), 'System email label can be edited' );
    $check( is_wp_error( b2b_mutate_registration_field( $event, 'delete', $name ) ), 'System email cannot be deleted' );
    $check( is_wp_error( b2b_mutate_registration_field( $event, 'edit', $name, array( 'label' => 'Optional', 'type' => 'text', 'required' => false ) ) ), 'System email type and required flag protected' );
    $check( is_wp_error( $save( $event, 'person@example.com' ) ), 'Label edit does not bypass uniqueness' );
    $reject_insert = static function () { return true; };
    add_filter( 'wp_insert_post_empty_content', $reject_insert );
    $failed = $save( $event, 'retry@example.com' );
    remove_filter( 'wp_insert_post_empty_content', $reject_insert );
    $check( is_wp_error( $failed ) && ! is_wp_error( $save( $event, 'retry@example.com' ) ), 'Failed insert rolls back reservation; retry succeeds' );
    $fail_meta = static function ( $check, $post_id, $key ) { return 'registration_answers' === $key ? false : $check; };
    add_filter( 'add_post_metadata', $fail_meta, 10, 3 );
    $failed = $save( $event, 'retry-meta@example.com' );
    remove_filter( 'add_post_metadata', $fail_meta, 10 );
    $check( is_wp_error( $failed ) && ! is_wp_error( $save( $event, 'retry-meta@example.com' ) ), 'Failed metadata write rolls back registration and reservation' );
    $fail_commit = static function ( $sql ) { return 'COMMIT' === trim( $sql ) ? 'INVALID COMMIT TEST' : $sql; };
    add_filter( 'query', $fail_commit );
    $failed = $save( $event, 'retry-commit@example.com' );
    remove_filter( 'query', $fail_commit );
    $check( is_wp_error( $failed ) && ! is_wp_error( $save( $event, 'retry-commit@example.com' ) ), 'Failed commit rolls back reservation and allows retry' );
    update_option( 'b2b_email_migration_version', 'running', false );
    $blocked = $save( $event, 'migration-pending@example.com' );
    update_option( 'b2b_email_migration_version', '1', false );
    $check( is_wp_error( $blocked ) && 'storage_unavailable' === $blocked->get_error_code(), 'Incomplete migration fails closed' );
    // Historical records intentionally bypass the new writer to model pre-upgrade data.
    $legacy_ids = array();
    foreach ( array( 'person@example.com', 'PERSON@EXAMPLE.COM', '', array( 'legacy_contact' => 'primary@example.com', 'second_email' => 'secondary@example.com' ) ) as $value ) {
        $legacy = wp_insert_post( array( 'post_type' => 'event_registration', 'post_status' => 'private', 'post_title' => 'Historical fixture' ) );
        $ids[] = $legacy; $legacy_ids[] = $legacy;
        update_post_meta( $legacy, 'event_id', $event );
        update_post_meta( $legacy, 'registration_answers', is_array( $value ) ? $value : array( $name => $value ) );
        update_post_meta( $legacy, 'registration_status', 'rejected' );
        update_post_meta( $legacy, 'registration_field_snapshot', array( array( 'name' => $name, 'type' => 'email' ), array( 'name' => 'second_email', 'type' => 'email' ) ) );
    }
    $before = array();
    foreach ( $legacy_ids as $legacy ) { $before[ $legacy ] = array( get_post( $legacy )->post_date, get_post_meta( $legacy, 'registration_answers', true ), get_post_meta( $legacy, 'registration_status', true ) ); }
    $migration = b2b_migrate_registration_emails();
    $check( ! is_wp_error( $migration ), 'Migration succeeds with historical duplicates and missing emails' );
    $check( ! is_wp_error( b2b_migrate_registration_emails() ), 'Migration safely reruns' );
    foreach ( $legacy_ids as $legacy ) {
        $after = array( get_post( $legacy )->post_date, get_post_meta( $legacy, 'registration_answers', true ), get_post_meta( $legacy, 'registration_status', true ) );
        $check( $before[ $legacy ] === $after, 'Historical ID, timestamps, answers and status remain intact' );
    }
    $check( in_array( 'missing_email', get_post_meta( $legacy_ids[2], '_b2b_email_review', true ), true ), 'Missing legacy email is flagged for review' );
    $check( in_array( 'ambiguous_email', get_post_meta( $legacy_ids[3], '_b2b_email_review', true ), true ), 'Ambiguous legacy emails are flagged and conservatively reserved' );
    $check( is_wp_error( $save( $event, 'secondary@example.com' ) ), 'Ambiguous secondary legacy email also blocks duplicates' );
    $rows = b2b_get_registration_answer_rows( $id, $event );
    $check( ! empty( $rows ), 'Historical answers remain accessible' );
    WP_CLI::success( 'Email identity and storage checks passed.' );
} finally {
    global $wpdb;
    foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
    foreach ( $events as $event ) {
        $wpdb->delete( b2b_email_table(), array( 'event_id' => $event ), array( '%d' ) );
        wp_delete_post( $event, true );
    }
    wp_set_current_user( $original_user );
}
