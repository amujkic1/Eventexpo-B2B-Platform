<?php
/** Local regression check; run with the seeded localadmin and organizer accounts. */
if ( 'local' !== wp_get_environment_type() || ! function_exists( 'UM' ) ) {
    throw new RuntimeException( 'Run only in the local WordPress environment with Ultimate Member active.' );
}

$page_id = (int) UM()->config()->permalinks['register'];
wp_update_post( array( 'ID' => $page_id, 'post_name' => 'register-2' ) );
delete_option( 'b2b_company_registration_configured' );
foreach ( array( 0, get_user_by( 'login', 'organizer' )->ID ) as $user_id ) {
    wp_set_current_user( $user_id );
    b2b_update_company_registration_page();
    if ( 'register-2' !== get_post_field( 'post_name', $page_id ) ) {
        throw new RuntimeException( 'An unauthorized user changed the signup configuration.' );
    }
}
echo "PASS: anonymous users and organizers cannot run the setup\n";

wp_set_current_user( get_user_by( 'login', 'localadmin' )->ID );
$conflict_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish',
    'post_title' => 'Disposable signup slug conflict', 'post_name' => 'register-company' ) );
try {
    b2b_update_company_registration_page();
    if ( 'register-2' !== get_post_field( 'post_name', $page_id ) || get_option( 'b2b_company_registration_configured' ) ) {
        throw new RuntimeException( 'An occupied URL must leave the registration page untouched.' );
    }
    echo "PASS: occupied URL leaves the assigned registration page untouched\n";
} finally {
    wp_delete_post( $conflict_id, true );
}

b2b_update_company_registration_page();
if ( 'register-company' !== get_post_field( 'post_name', $page_id ) || ! get_option( 'b2b_company_registration_configured' ) ) {
    throw new RuntimeException( 'Administrator signup setup did not complete.' );
}
$form_id = (int) get_option( 'um_core_forms' )['register'];
$fields = get_post_meta( $form_id, '_um_custom_fields', true );
if ( empty( $fields['user_password']['force_confirm_pass'] ) || empty( $fields['user_login']['required'] )
    || 'subscriber' !== get_post_meta( $form_id, '_um_register_role', true ) ) {
    throw new RuntimeException( 'Native confirmation, username or Subscriber settings are missing.' );
}
echo "PASS: existing page renamed and native form configured for Subscriber signup\n";

try {
    $customized_fields = $fields;
    $customized_fields['user_email']['label'] = 'Administrator customization';
    update_post_meta( $form_id, '_um_custom_fields', wp_slash( $customized_fields ) );
    b2b_update_company_registration_page();
    if ( 'Administrator customization' !== get_post_meta( $form_id, '_um_custom_fields', true )['user_email']['label'] ) {
        throw new RuntimeException( 'Completed setup overwrote a later administrator change.' );
    }
    echo "PASS: setup does not repeat or overwrite later administrator changes\n";
} finally {
    update_post_meta( $form_id, '_um_custom_fields', wp_slash( $fields ) );
}
