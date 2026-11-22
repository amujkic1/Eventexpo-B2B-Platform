<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

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

/** Save an authorized profile using sanitized values supplied by its handler. */
function b2b_update_company_profile( $company_id, array $data, $upload_logo ) {
    $result = wp_update_post( array(
        'ID' => $company_id,
        'post_title' => $data['company_name'],
        'post_content' => $data['company_description'],
    ), true );
    if ( is_wp_error( $result ) ) {
        return new WP_Error( 'save_failed', 'The company profile could not be updated.' );
    }
    foreach ( array( 'website', 'email', 'phone', 'country', 'city', 'address' ) as $key ) {
        update_post_meta( $company_id, $key, $data[ $key ] );
    }
    if ( $upload_logo ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $logo_id = media_handle_upload( 'company_logo', $company_id );
        if ( ! is_wp_error( $logo_id ) ) { set_post_thumbnail( $company_id, $logo_id ); }
    }
    wp_set_object_terms( $company_id, $data['industry_id'] ? array( $data['industry_id'] ) : array(), 'industry' );
    return true;
}


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
