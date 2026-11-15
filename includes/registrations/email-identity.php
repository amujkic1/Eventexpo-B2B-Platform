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

/** Fail closed on non-transactional engines; never convert production tables. */
function b2b_email_storage_ready() {
    global $wpdb;
    if ( '1' !== get_option( 'b2b_email_schema_version' ) ) { return false; }
    $suppressed = $wpdb->suppress_errors( true );
    try {
        foreach ( array( $wpdb->posts, $wpdb->postmeta, b2b_email_table() ) as $table ) {
            $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
            if ( 'innodb' !== strtolower( (string) $engine ) ) { return false; }
        }
        return true;
    } finally {
        $wpdb->suppress_errors( $suppressed );
    }
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
    if ( ! b2b_email_storage_ready() ) { return new WP_Error( 'storage_unavailable', 'Email storage requires InnoDB for the posts, postmeta and email index tables.' ); }
    return true;
}

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
    if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
        return new WP_Error( 'migration_busy', 'Another migration is running.' );
    }
    $suppressed = $wpdb->suppress_errors( true );
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
        return new WP_Error( 'migration_failed', 'Email migration did not finish. Existing registrations are intact. Fix database access and rerun the migration.' );
    } finally {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        $wpdb->reconnect_retries = $reconnect_retries;
        $wpdb->suppress_errors( $suppressed );
    }
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

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'eventexpo registrations-migrate', function () {
        $result = b2b_migrate_registration_emails();
        if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
        WP_CLI::success( wp_json_encode( $result ) );
    } );
    WP_CLI::add_command( 'eventexpo registrations-audit', function () {
        $page = 1;
        do {
            $query = new WP_Query( array( 'post_type' => 'event_registration', 'post_status' => 'any', 'posts_per_page' => 100, 'paged' => $page++, 'meta_key' => '_b2b_email_review' ) );
            foreach ( $query->posts as $post ) { WP_CLI::log( 'Registration ' . $post->ID . ': ' . implode( ', ', (array) get_post_meta( $post->ID, '_b2b_email_review', true ) ) ); }
        } while ( $page <= $query->max_num_pages );
    } );
}

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( '1' !== get_option( 'b2b_email_migration_version' ) ) {
        echo '<div class="notice notice-warning"><p>EventExpo registration email protection requires migration. Open <a href="' . esc_url( admin_url( 'tools.php?page=b2b-email-review' ) ) . '">Tools → Registration email review</a> or run <code>wp eventexpo registrations-migrate</code> before accepting registrations.</p></div>';
    }
} );

add_action( 'admin_menu', function () {
    add_management_page( 'Registration email review', 'Registration email review', 'manage_options', 'b2b-email-review', 'b2b_email_review_page' );
} );
function b2b_email_review_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    echo '<div class="wrap"><h1>Registration email review</h1><p>Migration preserves all historical answers and reserves every usable legacy email. Multiple possible addresses and existing duplicates require manual review. Missing addresses cannot be matched retrospectively.</p>';
    $result = isset( $_GET['migration'] ) && is_string( $_GET['migration'] ) ? $_GET['migration'] : '';
    if ( 'complete' === $result ) { echo '<div class="notice notice-success"><p>Migration complete. Review any records listed below.</p></div>'; }
    if ( 'failed' === $result ) { echo '<div class="notice notice-error"><p>Migration could not finish. Check database permissions and InnoDB support, then rerun. Existing registrations remain intact.</p></div>'; }
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'b2b_migrate_emails' );
    echo '<input type="hidden" name="action" value="b2b_migrate_emails">';
    submit_button( 'Run or resume email migration' );
    echo '</form><p>For large sites use <code>wp eventexpo registrations-migrate</code>. Pause other registration imports and writers during migration.</p>';
    $page = isset( $_GET['review_page'] ) && is_scalar( $_GET['review_page'] ) ? max( 1, absint( $_GET['review_page'] ) ) : 1;
    $query = new WP_Query( array( 'post_type' => 'event_registration', 'post_status' => array_keys( get_post_stati() ), 'posts_per_page' => 50, 'paged' => $page, 'meta_key' => '_b2b_email_review' ) );
    echo '<h2>Records requiring review (' . esc_html( $query->found_posts ) . ')</h2><ul>';
    foreach ( $query->posts as $post ) {
        echo '<li><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">Registration ' . esc_html( $post->ID ) . '</a>: ' . esc_html( implode( ', ', (array) get_post_meta( $post->ID, '_b2b_email_review', true ) ) ) . '</li>';
    }
    echo '</ul>';
    if ( $page > 1 ) { echo '<a href="' . esc_url( add_query_arg( 'review_page', $page - 1 ) ) . '">Previous</a> '; }
    if ( $page < $query->max_num_pages ) { echo '<a href="' . esc_url( add_query_arg( 'review_page', $page + 1 ) ) . '">Next</a>'; }
    echo '</div>';
}
add_action( 'admin_post_b2b_migrate_emails', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'b2b_migrate_emails' );
    $result = b2b_migrate_registration_emails();
    wp_safe_redirect( add_query_arg( array( 'page' => 'b2b-email-review', 'migration' => is_wp_error( $result ) ? 'failed' : 'complete' ), admin_url( 'tools.php' ) ), 303 );
    exit;
} );
