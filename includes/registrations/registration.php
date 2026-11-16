<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create a company after a user
 * successfully registers through Ultimate Member.
 */
function b2b_create_company_after_registration( $user_id, $args ) {
    if ( ! get_userdata( $user_id ) ) { return new WP_Error( 'invalid_user', 'A registered user is required.' ); }
    $company_id = (int) get_user_meta( $user_id, 'company_id', true );
    $company = $company_id ? get_post( $company_id ) : null;
    if ( $company && 'company' === $company->post_type && (int) $company->post_author === (int) $user_id ) {
        return $company_id;
    }

    $company_name = get_user_meta( $user_id, 'company_name', true );
    $company_name = is_string( $company_name ) ? sanitize_text_field( $company_name ) : '';
    if ( '' === $company_name ) { return new WP_Error( 'missing_company_name', 'Company name is required.' ); }

    // Recover an existing owned company if the user link was lost; do not duplicate it.
    $owned = get_posts( array( 'post_type' => 'company', 'post_status' => 'any',
        'author' => $user_id, 'posts_per_page' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
    $company_id = $owned ? (int) $owned[0] : wp_insert_post( wp_slash( array(
        'post_type' => 'company', 'post_title' => $company_name,
        'post_status' => 'publish', 'post_author' => $user_id,
    ) ), true );
    if ( is_wp_error( $company_id ) ) {
        error_log( sprintf( 'EventExpo signup: user=%d error=company_insert_failed', $user_id ) );
        return $company_id;
    }

    update_user_meta( $user_id, 'company_id', $company_id );
    if ( (int) get_user_meta( $user_id, 'company_id', true ) !== $company_id ) {
        if ( ! $owned ) { wp_delete_post( $company_id, true ); }
        error_log( sprintf( 'EventExpo signup: user=%d error=company_link_failed', $user_id ) );
        return new WP_Error( 'company_link_failed', 'Your company profile could not be connected. Please contact EventExpo.' );
    }
    return $company_id;
}

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
