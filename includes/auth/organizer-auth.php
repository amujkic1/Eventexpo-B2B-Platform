<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Apply the company signup layout once, retaining the assigned UM page and form. */
function b2b_update_company_registration_page() {
    if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'UM' )
        || get_option( 'b2b_company_registration_configured' ) ) {
        return;
    }

    $page_id = (int) ( UM()->config()->permalinks['register'] ?? 0 );
    if ( ! $page_id ) { return; }
    $page = get_post( $page_id );
    if ( ! $page || 'page' !== $page->post_type
        || ! preg_match( '/' . get_shortcode_regex( array( 'ultimatemember' ) ) . '/', $page->post_content, $match ) ) {
        return;
    }
    $attributes = shortcode_parse_atts( $match[3] );
    $form_id = absint( $attributes['form_id'] ?? 0 );
    if ( 'um_form' !== get_post_type( $form_id ) || 'register' !== get_post_meta( $form_id, '_um_mode', true ) ) {
        return;
    }
    $fields = get_post_meta( $form_id, '_um_custom_fields', true );
    if ( ! is_array( $fields ) ) { return; }

    if ( 'register-company' !== wp_unique_post_slug( 'register-company', $page_id, $page->post_status, 'page', $page->post_parent )
        || is_wp_error( wp_update_post( array( 'ID' => $page_id, 'post_name' => 'register-company' ), true ) ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>EventExpo could not rename the registration page. Check whether another page already uses register-company.</p></div>';
        } );
        return;
    }

    $defaults = UM()->config()->core_form_meta['register']['_um_custom_fields'];
    $labels = array( 'first_name' => 'First name', 'last_name' => 'Last name',
        'user_login' => 'Username', 'user_email' => 'Email address', 'user_password' => 'Password' );
    $position = 1;
    foreach ( $labels as $key => $label ) {
        $fields[ $key ] = array_merge( $defaults[ $key ], $fields[ $key ] ?? array(), array(
            'label' => $label, 'position' => (string) $position++,
            'in_row' => '_um_row_1', 'in_sub_row' => '0', 'in_column' => '1',
        ) );
    }
    $fields['_um_row_1'] = $defaults['_um_row_1'];
    $fields['user_login']['required'] = 1;
    $fields['user_email']['required'] = 1;
    $fields['user_password']['force_confirm_pass'] = 1;
    $fields['user_password']['label_confirm_pass'] = 'Confirm password';
    $fields['company_name'] = array_merge( $fields['company_name'] ?? array(), array(
        'title' => 'Company name', 'metakey' => 'company_name', 'type' => 'text', 'label' => 'Company name',
        'required' => 1, 'public' => 0, 'editable' => true, 'position' => (string) $position,
        'in_row' => '_um_row_1', 'in_sub_row' => '0', 'in_column' => '1', 'in_group' => '',
    ) );
    update_post_meta( $form_id, '_um_custom_fields', wp_slash( $fields ) );
    if ( get_post_meta( $form_id, '_um_custom_fields', true ) !== $fields ) { return; }
    update_post_meta( $form_id, '_um_register_use_custom_settings', 1 );
    update_post_meta( $form_id, '_um_register_role', 'subscriber' );
    update_post_meta( $form_id, '_um_register_primary_btn_word', 'Register' );
    update_post_meta( $form_id, '_um_register_secondary_btn', 1 );
    update_post_meta( $form_id, '_um_register_secondary_btn_word', 'Sign in' );
    update_option( 'b2b_company_registration_configured', 1, false );
}
add_action( 'admin_init', 'b2b_update_company_registration_page' );


add_action(
    'um_registration_complete',
    'b2b_create_company_after_registration',
    10,
    2
);

/** UM validates before creating the WordPress account; whitespace is not a name. */
function b2b_validate_organizer_signup( $submitted, $form_data ) {
    if ( empty( $form_data['custom_fields']['company_name'] ) ) { return; }
    $name = $submitted['company_name'] ?? '';
    if ( ! is_string( $name ) || '' === sanitize_text_field( $name ) ) {
        UM()->form()->add_error( 'company_name', 'Company name is required.' );
    }
}
add_action( 'um_submit_form_errors_hook__registration', 'b2b_validate_organizer_signup', 20, 2 );

/** Native wp-login.php remains usable; UM's own redirect is configured per role. */
function b2b_organizer_login_redirect( $redirect, $requested_redirect, $user ) {
    if ( ! $user instanceof WP_User || user_can( $user, 'manage_options' ) ) { return $redirect; }
    $company_id = (int) get_user_meta( $user->ID, 'company_id', true );
    $company = $company_id ? get_post( $company_id ) : null;
    if ( $company && 'company' === $company->post_type && (int) $company->post_author === (int) $user->ID ) {
        return b2b_page_url( 'b2b_dashboard', 'b2b-dashboard' );
    }
    return $redirect;
}
add_filter( 'login_redirect', 'b2b_organizer_login_redirect', 10, 3 );


/**
 * Validate the email address during Ultimate Member registration.
 */
function b2b_validate_email( $key, $array, $args ) {

    if ( empty( $args[ $key ] ) ) {
        return;
    }

    $email = sanitize_email( $args[ $key ] );

    if ( ! is_email( $email ) ) {
        UM()->form()->add_error(
            $key,
            'Please enter a valid email address.'
        );

        return;
    }

    if ( email_exists( $email ) ) {
        UM()->form()->add_error(
            $key,
            'An account with this email address already exists. Please log in or reset your password.'
        );
    }
}

add_action(
    'um_custom_field_validation_b2b_email_validation',
    'b2b_validate_email',
    10,
    3
);
