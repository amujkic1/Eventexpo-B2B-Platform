<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'eventexpo registrations-migrate', function () {
        $result = b2b_migrate_registration_emails();
        b2b_record_email_migration_result( $result );
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
    $result = isset( $_GET['migration'] ) && is_string( $_GET['migration'] ) ? $_GET['migration'] : '';
    $last_error = get_option( 'b2b_email_migration_error', array() );
    $storage_diagnostics = b2b_email_storage_diagnostics();
    $page = isset( $_GET['review_page'] ) && is_scalar( $_GET['review_page'] ) ? max( 1, absint( $_GET['review_page'] ) ) : 1;
    $query = new WP_Query( array( 'post_type' => 'event_registration', 'post_status' => array_keys( get_post_stati() ), 'posts_per_page' => 50, 'paged' => $page, 'meta_key' => '_b2b_email_review' ) );
    $review_rows = array();
    foreach ( $query->posts as $post ) {
        $review_rows[] = array( 'id' => $post->ID, 'edit_url' => get_edit_post_link( $post->ID ),
            'issues' => implode( ', ', (array) get_post_meta( $post->ID, '_b2b_email_review', true ) ) );
    }
    $total = $query->found_posts;
    $pages = $query->max_num_pages;
    $previous_url = $page > 1 ? add_query_arg( 'review_page', $page - 1 ) : '';
    $next_url = $page < $pages ? add_query_arg( 'review_page', $page + 1 ) : '';
    echo b2b_render_template( 'admin/email-review',
        compact( 'result', 'last_error', 'storage_diagnostics', 'review_rows', 'total', 'previous_url', 'next_url' ) );
}
add_action( 'admin_post_b2b_migrate_emails', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'b2b_migrate_emails' );
    $result = b2b_migrate_registration_emails();
    b2b_record_email_migration_result( $result );
    wp_safe_redirect( add_query_arg( array( 'page' => 'b2b-email-review', 'migration' => is_wp_error( $result ) ? 'failed' : 'complete' ), admin_url( 'tools.php' ) ), 303 );
    exit;
} );
