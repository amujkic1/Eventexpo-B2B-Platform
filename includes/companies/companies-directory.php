<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the companies directory.
 */
function b2b_companies_directory() {

    $search = isset( $_GET['company_search'] )
        ? sanitize_text_field(
            wp_unslash( $_GET['company_search'] )
        )
        : '';

    $industry = isset( $_GET['industry'] )
        ? sanitize_text_field(
            wp_unslash( $_GET['industry'])
        )
        : '';

    $args = array(
        'post_type'      => 'company',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        's' => $search,
    );

    $country = isset( $_GET['country'] ) && is_string( $_GET['country'] )
        ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';
    if ( '' !== $country ) {
        $args['meta_query'] = array( array( 'key' => 'country', 'value' => $country ) );
    }
    $countries = array();
    foreach ( get_posts( array( 'post_type' => 'company', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $listed_company ) {
        $listed_country = get_post_meta( $listed_company->ID, 'country', true );
        if ( is_string( $listed_country ) && '' !== $listed_country ) {
            $countries[ $listed_country ] = $listed_country;
        }
    }
    natcasesort( $countries );

    if ( ! empty( $industry ) ) {

    $args['tax_query'] = array(
            array(
                'taxonomy' => 'industry',
                'field'    => 'slug',
                'terms'    => $industry,
            ),
        );
    }

    $companies = new WP_Query( $args );

    $industry_terms = get_terms(
        array(
            'taxonomy' => 'industry',
            'hide_empty' => false,
        )
    ); 

    $company_cards = array();
    while ( $companies->have_posts() ) {
        $companies->the_post();
        $company_id = get_the_ID();
        $company_cards[] = array(
            'title'      => get_the_title(),
            'description' => wp_strip_all_tags( get_post( $company_id )->post_content ),
            'url'        => get_permalink( $company_id ),
            'logo'       => has_post_thumbnail() ? get_the_post_thumbnail( $company_id, 'medium' ) : '',
            'country'    => get_post_meta( $company_id, 'country', true ),
            'city'       => get_post_meta( $company_id, 'city', true ),
            'industries' => get_the_terms( $company_id, 'industry' ),
        );
    }
    wp_reset_postdata();

    return b2b_render_app(
        'companies/companies-directory',
        compact( 'search', 'industry', 'industry_terms', 'company_cards', 'country', 'countries' ),
        'companies'
    );
}

add_shortcode(
    'b2b_companies_directory',
    'b2b_companies_directory'
);
