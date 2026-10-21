<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the B2B user dashboard.
 */
function b2b_dashboard() {

    if ( ! is_user_logged_in() ) {
        return b2b_render_template( 'message', array( 'message' => 'Please log in to access your B2B dashboard.' ) );
    }

    $user = wp_get_current_user();

    $company_id = get_user_meta(
        $user->ID,
        'company_id',
        true
    );

    $events = $company_id
        ? b2b_get_manageable_events( $user->ID )
        : array();

    $event_dates = array();
    foreach ( $events as $event ) {
        $event_dates[ $event->ID ] = get_post_meta( $event->ID, 'start_date', true );
    }
    $company_title = $company_id ? get_the_title( $company_id ) : '';

    return b2b_render_template(
        'dashboard',
        compact( 'user', 'company_id', 'events', 'event_dates', 'company_title' )
    );
}

add_shortcode(
    'b2b_dashboard',
    'b2b_dashboard'
);
