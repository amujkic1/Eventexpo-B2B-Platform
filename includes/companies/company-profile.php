<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display and edit the company profile
 * connected to the currently logged-in user.
 */
function b2b_my_company() {

    if ( ! is_user_logged_in() ) {
        return b2b_render_app( 'shared/message', array( 'message' => 'Please log in to view your company.' ) );
    }

    $user_id = get_current_user_id();

     /*
     * Read company data.
     */
    $company_id = get_user_meta(
        $user_id,
        'company_id',
        true
    );

    if ( empty( $company_id ) ) {
        return b2b_render_app( 'shared/message', array( 'message' => 'No company profile is connected to your account.' ) );
    }

    $data = b2b_get_company_data( $company_id );

    $company = $data['company'];

    if (
        ! $company ||
        'company' !== $company->post_type ||
        (int) $company->post_author !== $user_id
    ) {
        return b2b_render_app( 'shared/message', array( 'message' => 'You do not have permission to access this company.' ) );
    }

    $website = $data['website'];
    $email   = $data['email'];
    $phone   = $data['phone'];
    $country = $data['country'];
    $city    = $data['city'];
    $address = $data['address'];

    $industries = get_terms(
    array(
        'taxonomy'   => 'industry',
        'hide_empty' => false,
    )
    );

    $company_industries = wp_get_post_terms(
        $company_id,
        'industry',
        array(
            'fields' => 'ids',
        )
    );

    $selected_industry = ! empty( $company_industries )
        ? $company_industries[0]
        : 0;

    $message = '';

    /*
     * Check and process form submission.
     */
    if (
        'POST' === $_SERVER['REQUEST_METHOD'] &&
        isset( $_POST['b2b_company_nonce'] )
    ) {

        $nonce = sanitize_text_field(
            wp_unslash( $_POST['b2b_company_nonce'] )
        );

        if (
            wp_verify_nonce(
                $nonce,
                'b2b_update_company'
            )
        ) {

            $company_name = isset( $_POST['company_name'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['company_name'] )
                )
                : '';

            $company_description = isset( $_POST['company_description'] )
                ? sanitize_textarea_field(
                    wp_unslash( $_POST['company_description'] )
                )
                : '';

            $industry_id = isset( $_POST['industry'] )
                ? absint( $_POST['industry'] )
                : 0;

            $website = isset( $_POST['website'] )
                ? esc_url_raw(
                    wp_unslash( $_POST['website'] )
                )
                : '';

            $email = isset( $_POST['email'] )
                ? sanitize_email(
                    wp_unslash( $_POST['email'] )
                )
                : '';

            $phone = isset( $_POST['phone'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['phone'] )
                )
                : '';

            $country = isset( $_POST['country'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['country'] )
                )
                : '';

            $city = isset( $_POST['city'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['city'] )
                )
                : '';

            $address = isset( $_POST['address'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['address'] )
                )
                : '';

            /*
             * Update company data.
             */
            if ( ! empty( $company_name ) ) {

                $result = b2b_update_company_profile(
                    $company_id,
                    compact( 'company_name', 'company_description', 'website', 'email', 'phone', 'country', 'city', 'address', 'industry_id' ),
                    ! empty( $_FILES['company_logo']['name'] )
                );
                if ( is_wp_error( $result ) ) {
                    $message = $result->get_error_message();
                } else {
                    $message = 'Company profile updated successfully.';
                    $company = get_post( $company_id );
                }

            } else {

                $message = 'Company name is required.';
            }
        }
    }

    return b2b_render_app(
        'companies/company-profile',
        compact( 'company_id', 'company', 'website', 'email', 'phone', 'country', 'city', 'address', 'industries', 'selected_industry', 'message' ),
        'companies'
    );
}

add_shortcode(
    'b2b_my_company',
    'b2b_my_company'
);
