<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the public profile of an event.
 */
function b2b_event_public_profile( $event_id ) {

    $data = b2b_get_event_data( $event_id );

    $event = $data['event'];

    $can_manage = b2b_user_can_manage_event(
        get_current_user_id(),
        $event_id
    );

    if (
        ! $event ||
        'event' !== $event->post_type
    ) {
        return b2b_render_template( 'message', array( 'message' => 'Event not found.' ) );
    }

    $start_date = $data['start_date'];
    $end_date   = $data['end_date'];
    $location   = $data['location'];
    $city       = $data['city'];
    $country    = $data['country'];
    $organizer_company_id = $data['organizer_company_id'];

    $event_types = get_the_terms(
        $event_id,
        'event_type'
    );

    $organizer_company = $organizer_company_id ? get_post( $organizer_company_id ) : null;
    $registration_form = b2b_render_event_registration_form( $event_id );

    return b2b_render_template(
        'event-public',
        compact( 'event_id', 'event', 'can_manage', 'start_date', 'end_date', 'location', 'city', 'country', 'organizer_company_id', 'event_types', 'organizer_company', 'registration_form' )
    );
}

/**
 * Use the custom template for single event pages.
 */
function b2b_event_single_template( $template ) {

    if ( is_singular( 'event' ) ) {

        $custom_template = plugin_dir_path( __DIR__ )
            . 'templates/single-event.php';

        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }

    return $template;
}

add_filter(
    'single_template',
    'b2b_event_single_template'
);
