<?php
/** Local integration checks for module boundaries; all mail is intercepted. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
    throw new RuntimeException( 'Local WP-CLI tests only.' );
}
$check = static function ( $condition, $label ) {
    if ( ! $condition ) { throw new RuntimeException( $label ); }
    WP_CLI::log( 'PASS: ' . $label );
};
$original_user = get_current_user_id();
$user_id = wp_insert_user( array( 'user_login' => 'ee-workflows-' . wp_generate_uuid4(),
    'user_email' => 'ee-workflows-' . wp_generate_uuid4() . '@example.com',
    'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
if ( is_wp_error( $user_id ) ) { throw new RuntimeException( 'Could not create fixture user.' ); }
$company_id = 0; $event_id = 0; $registration_id = 0; $mail = array();
$capture = static function ( $result, $args ) use ( &$mail, $check ) {
    global $wpdb;
    $check( '0' === (string) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Mail runs after the transaction' );
    $lock = 'ee-email-migrate-' . md5( $wpdb->prefix );
    $check( '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_FREE_LOCK(%s)', $lock ) ), 'Mail runs after releasing the write lock' );
    $mail[] = $args;
    return true;
};
add_filter( 'pre_wp_mail', $capture, 99, 2 );
try {
    update_user_meta( $user_id, 'company_name', 'Workflow fixture company' );
    $company_id = b2b_create_company_after_registration( $user_id, array() );
    $check( ! is_wp_error( $company_id ) && $company_id === b2b_create_company_after_registration( $user_id, array() ), 'Company creation is idempotent' );
    $check( (int) get_post_field( 'post_author', $company_id ) === $user_id, 'Company ownership is preserved' );
    wp_set_current_user( $user_id );
    $check( b2b_organizer_login_redirect( '/wp-admin/', '', get_userdata( $user_id ) ) === b2b_page_url( 'b2b_dashboard', 'b2b-dashboard' ), 'Organizer native login redirect is preserved' );
    $event_id = b2b_create_event_draft( 'Workflow fixture event' );
    $check( ! is_wp_error( $event_id ) && 'draft' === get_post_status( $event_id ), 'Organizer creates a draft' );
    $check( b2b_user_can_edit_event( $user_id, $event_id ), 'Owner can edit a draft' );
    $other = get_user_by( 'login', 'organizer' );
    $check( ! b2b_user_can_manage_event( $other->ID, $event_id ), 'Another company cannot manage the event' );
    $check( ! user_can( $user_id, get_post_type_object( 'event' )->cap->publish_posts ), 'Organizer cannot publish through native capabilities' );
    $check( ! is_wp_error( b2b_submit_event_for_approval( $event_id ) ) && 'pending' === get_post_status( $event_id ), 'Draft submission becomes pending' );
    $check( ! b2b_user_can_edit_event( $user_id, $event_id ) && b2b_user_can_manage_event( $user_id, $event_id ), 'Pending owner can view but cannot edit' );
    $check( is_wp_error( b2b_mutate_registration_field( $event_id, 'add', '', array( 'label' => 'Blocked', 'type' => 'text', 'required' => false ) ) ), 'Pending form mutation is rejected' );
    $admin = get_user_by( 'login', 'localadmin' );
    $check( b2b_user_can_edit_event( $admin->ID, $event_id ), 'Administrator can edit a pending event' );
    wp_update_post( array( 'ID' => $event_id, 'post_status' => 'draft' ) );
    $check( b2b_user_can_edit_event( $user_id, $event_id ), 'Returning to draft restores editing' );
    wp_update_post( array( 'ID' => $event_id, 'post_status' => 'publish' ) );
    $identity = b2b_attendee_email_field_name( $event_id );
    wp_set_current_user( 0 );
    $registration_id = b2b_save_event_registration( $event_id, array( $identity => 'workflow-attendee@example.com' ) );
    $check( ! is_wp_error( $registration_id ) && 2 === count( $mail ), 'Anonymous signup sends attendee and organizer notifications' );
    $check( 'workflow-attendee@example.com' === $mail[0]['to'] && get_userdata( $user_id )->user_email === $mail[1]['to'], 'Notification recipients are preserved' );
    wp_set_current_user( $other->ID );
    $check( is_wp_error( b2b_update_registration_status( $registration_id, 'approved' ) ) && 2 === count( $mail ), 'Other company cannot change registration status or trigger mail' );
    wp_set_current_user( $user_id );
    foreach ( array( 'approved', 'rejected', 'pending' ) as $status ) {
        $before = count( $mail );
        $check( true === b2b_update_registration_status( $registration_id, $status ) && $before + 1 === count( $mail ), $status . ' transition sends one notification' );
        $check( true === b2b_update_registration_status( $registration_id, $status ) && $before + 1 === count( $mail ), 'Unchanged status does not send another notification' );
    }
    $check( false !== strpos( end( $mail )['message'], 'not currently approved' ), 'Returned-to-pending notification remains explicit' );
    $fail_mail = static function () { return false; };
    add_filter( 'pre_wp_mail', $fail_mail, 100 );
    $check( true === b2b_update_registration_status( $registration_id, 'approved' )
        && 'approved' === get_post_meta( $registration_id, 'registration_status', true ), 'Mail failure does not undo the status update' );
    remove_filter( 'pre_wp_mail', $fail_mail, 100 );
    $check( is_wp_error( b2b_save_event_registration( $event_id, array( $identity => 'WORKFLOW-ATTENDEE@example.com' ) ) ), 'Status updates preserve duplicate protection' );
    WP_CLI::success( 'MVP workflow checks passed.' );
} finally {
    remove_filter( 'pre_wp_mail', $capture, 99 );
    if ( $registration_id && ! is_wp_error( $registration_id ) ) { wp_delete_post( $registration_id, true ); }
    if ( $event_id && ! is_wp_error( $event_id ) ) {
        global $wpdb;
        $wpdb->delete( b2b_email_table(), array( 'event_id' => $event_id ), array( '%d' ) );
        wp_delete_post( $event_id, true );
    }
    if ( $company_id && ! is_wp_error( $company_id ) ) { wp_delete_post( $company_id, true ); }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $user_id );
    wp_set_current_user( $original_user );
}
