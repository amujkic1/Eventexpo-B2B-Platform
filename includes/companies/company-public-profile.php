<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the public profile of a company.
 */
function b2b_company_public_profile( $company_id ) {

    $data = b2b_get_company_data( $company_id );

    $company = $data['company'];

    if (
        ! $company ||
        'company' !== $company->post_type
    ) {
        return b2b_render_app( 'shared/message', array( 'message' => 'Company not found.' ) );
    }

    $website = $data['website'];
    $email   = $data['email'];
    $phone   = $data['phone'];
    $country = $data['country'];
    $city    = $data['city'];
    $address = $data['address'];

    $industries = get_the_terms(
        $company_id,
        'industry'
    );

    return b2b_render_app(
        'companies/company-public',
        compact( 'company_id', 'company', 'website', 'email', 'phone', 'country', 'city', 'address', 'industries' ),
        'companies'
    );
}

/**
 * Use the custom template for single company pages.
 */
function b2b_company_template( $template ) {

    if ( is_singular( 'company' ) ) {

        $custom_template = trailingslashit( dirname( __DIR__, 2 ) )
            . 'templates/companies/single-company.php';

        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }

    return $template;
}

add_filter(
    'single_template',
    'b2b_company_template'
);
