<?php
/** Registration mail delivery; called only after persistence succeeds. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Send one best-effort plain-text message without changing the saved result. */
function b2b_send_registration_notification( $registration_id, $type ) {
    $messages = array(
        'received'  => array( 'Registration received', 'Your registration has been received and is awaiting review.' ),
        'organizer' => array( 'New event registration', 'A new registration is awaiting your review. Log in to manage it using the link below.' ),
        'approved'  => array( 'Registration approved', 'Your registration has been approved.' ),
        'rejected'  => array( 'Registration not approved', 'Your registration has not been approved.' ),
        'pending'   => array( 'Registration returned to review', 'Your registration has returned to review and is awaiting a new decision. It is not currently approved.' ),
    );
    if ( ! isset( $messages[ $type ] ) ) { return; }

    $failure = 'invalid_context';
    try {
        $event_id = (int) get_post_meta( $registration_id, 'event_id', true );
        $event = get_post( $event_id );
        if ( ! $event || 'event' !== $event->post_type ) { return; }

        $failure = 'invalid_recipient';
        $recipient = get_post_meta( $registration_id, '_b2b_attendee_email', true );
        $url = get_permalink( $event_id );
        if ( 'organizer' === $type ) {
            $company_id = (int) get_post_meta( $event_id, 'organizer_company_id', true );
            $company = $company_id ? get_post( $company_id ) : null;
            $owner = $company && 'company' === $company->post_type ? get_userdata( $company->post_author ) : false;
            if ( ! $owner || (int) get_user_meta( $owner->ID, 'company_id', true ) !== $company_id
                || ! b2b_user_can_manage_event( $owner->ID, $event_id ) ) {
                return;
            }
            $recipient = $owner->user_email;
            $url = b2b_get_manage_event_url( $event_id );
        }
        if ( ! is_string( $recipient ) || strpbrk( $recipient, "\r\n" ) || ! is_email( $recipient ) ) { return; }

        $title = sanitize_text_field( wp_specialchars_decode( wp_strip_all_tags( $event->post_title ), ENT_QUOTES ) );
        $body = sprintf( "Event: %s\nRegistration: #%d\n\n%s\n\n%s\n\nEventExpo B2B",
            $title, $registration_id, $messages[ $type ][1], esc_url_raw( $url, array( 'http', 'https' ) ) );
        $failure = 'mail_failed';
        if ( wp_mail( $recipient, 'EventExpo B2B: ' . $messages[ $type ][0], $body, array(
            'From: EventExpo B2B <info@eventexpo.ba>',
            'Content-Type: text/plain; charset=UTF-8',
        ) ) ) {
            $failure = '';
        }
    } catch ( Throwable $error ) {
        $failure = 'mail_exception';
    } finally {
        if ( $failure ) {
            // IDs and fixed codes only: transport errors can contain private data.
            error_log( sprintf( 'EventExpo notification: registration=%d type=%s error=%s', $registration_id, $type, $failure ) );
        }
    }
}
