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
            'url'        => get_permalink( $company_id ),
            'logo'       => has_post_thumbnail() ? get_the_post_thumbnail( $company_id, 'medium' ) : '',
            'country'    => get_post_meta( $company_id, 'country', true ),
            'city'       => get_post_meta( $company_id, 'city', true ),
            'industries' => get_the_terms( $company_id, 'industry' ),
        );
    }
    wp_reset_postdata();

    return b2b_render_template(
        'companies-directory',
        compact( 'search', 'industry', 'industry_terms', 'company_cards' )
    );
}

add_shortcode(
    'b2b_companies_directory',
    'b2b_companies_directory'
);
