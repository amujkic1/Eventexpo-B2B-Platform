<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the B2B user dashboard.
 */
function b2b_dashboard() {

    if ( ! is_user_logged_in() ) {
        return b2b_render_app( 'shared/message', array( 'message' => 'Please log in to access your B2B dashboard.' ) );
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
    $company_data = $company_id ? b2b_get_company_data( $company_id ) : array();
    $company_industries = $company_id ? get_the_terms( $company_id, 'industry' ) : array();
    $company_industry = ! empty( $company_industries ) && ! is_wp_error( $company_industries ) ? $company_industries[0]->name : '';
    $profile_checks = array(
        'Logo and description' => $company_id && has_post_thumbnail( $company_id ) && ! empty( $company_data['company']->post_content ),
        'Industry' => '' !== $company_industry,
        'Location' => ! empty( $company_data['city'] ) && ! empty( $company_data['country'] ),
        'Contact information' => ! empty( $company_data['email'] ) || ! empty( $company_data['phone'] ),
    );
    $upcoming_events = array_values( array_filter( $events, function ( $event ) use ( $event_dates ) {
        return 'publish' === $event->post_status && ( $event_dates[ $event->ID ] ?? '' ) >= wp_date( 'Y-m-d' );
    } ) );
    usort( $upcoming_events, function ( $left, $right ) use ( $event_dates ) {
        return strcmp( $event_dates[ $left->ID ], $event_dates[ $right->ID ] );
    } );
    $next_event = $upcoming_events[0] ?? null;
    $next_event_data = $next_event ? b2b_get_event_data( $next_event->ID ) : array();

    return b2b_render_app(
        'dashboard/dashboard',
        compact( 'user', 'company_id', 'events', 'event_dates', 'company_title', 'company_industry', 'profile_checks', 'next_event', 'next_event_data' ),
        'dashboard'
    );
}

add_shortcode(
    'b2b_dashboard',
    'b2b_dashboard'
);
