<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the Company custom post type.
 */
function b2b_register_company_post_type() {

    $labels = array(
        'name'          => 'Companies',
        'singular_name' => 'Company',
        'add_new'       => 'Add New',
        'add_new_item'  => 'Add New Company',
        'edit_item'     => 'Edit Company',
        'new_item'      => 'New Company',
        'view_item'     => 'View Company',
        'search_items'  => 'Search Companies',
        'not_found'     => 'No companies found',
    );

    $args = array(
        'labels'       => $labels,
        'public'       => true,
        'show_in_rest' => true,
        'has_archive'  => false,
        'rewrite'      => array( 'slug' => 'companies' ),
        'menu_icon'    => 'dashicons-building',
        'supports'     => array( 'title', 'editor', 'thumbnail' ),
    );

    register_post_type( 'company', $args );
}

add_action( 'init', 'b2b_register_company_post_type' );


/**
 * Register the Industry taxonomy for companies.
 */
function b2b_register_industry_taxonomy() {

    $labels = array(
        'name'              => 'Industries',
        'singular_name'     => 'Industry',
        'search_items'      => 'Search Industries',
        'all_items'         => 'All Industries',
        'parent_item'       => 'Parent Industry',
        'parent_item_colon' => 'Parent Industry:',
        'edit_item'         => 'Edit Industry',
        'update_item'       => 'Update Industry',
        'add_new_item'      => 'Add New Industry',
        'new_item_name'     => 'New Industry Name',
        'menu_name'         => 'Industries',
    );

    $args = array(
        'labels'            => $labels,
        'public'            => true,
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array( 'slug' => 'industry' ),
    );

    register_taxonomy(
        'industry',
        array( 'company' ),
        $args
    );
}

add_action( 'init', 'b2b_register_industry_taxonomy' );

/**
 * Get company profile data.
 *
 * @param int $company_id Company post ID.
 * @return array Company profile data.
 */
function b2b_get_company_data( $company_id ) {

    return array(
        'company' => get_post( $company_id ),
        'website' => get_post_meta( $company_id, 'website', true ),
        'email'   => get_post_meta( $company_id, 'email', true ),
        'phone'   => get_post_meta( $company_id, 'phone', true ),
        'country' => get_post_meta( $company_id, 'country', true ),
        'city'    => get_post_meta( $company_id, 'city', true ),
        'address' => get_post_meta( $company_id, 'address', true ),
    );
}