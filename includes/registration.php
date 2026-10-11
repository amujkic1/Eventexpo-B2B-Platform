<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create a company after a user
 * successfully registers through Ultimate Member.
 */
function b2b_create_company_after_registration( $user_id, $args ) {

    $existing_company_id = get_user_meta(
        $user_id,
        'company_id',
        true
    );

    if ( ! empty( $existing_company_id ) ) {
        return;
    }

    $company_name = get_user_meta(
        $user_id,
        'company_name',
        true
    );

    $company_name = sanitize_text_field( $company_name );

    if ( empty( $company_name ) ) {
        return;
    }

    $company_id = wp_insert_post(
        array(
            'post_type'   => 'company',
            'post_title'  => $company_name,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ),
        true
    );

    if ( is_wp_error( $company_id ) ) {
        return;
    }

    update_user_meta(
        $user_id,
        'company_id',
        $company_id
    );
}

add_action(
    'um_registration_complete',
    'b2b_create_company_after_registration',
    10,
    2
);


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