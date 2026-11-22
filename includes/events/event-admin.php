<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Register the Event Details meta box.
 */
function b2b_add_event_details_meta_box() {

    add_meta_box(
        'b2b_event_details',
        'Event Details',
        'b2b_render_event_details_meta_box',
        'event',
        'normal',
        'default'
    );
}

add_action(
    'add_meta_boxes',
    'b2b_add_event_details_meta_box'
);

/**
 * Render the Event Details meta box.
 */
function b2b_render_event_details_meta_box( $post ) {

    $start_date = get_post_meta(
        $post->ID,
        'start_date',
        true
    );

    $end_date = get_post_meta(
        $post->ID,
        'end_date',
        true
    );

    $location = get_post_meta(
        $post->ID,
        'location',
        true
    );

    $city = get_post_meta(
        $post->ID,
        'city',
        true
    );

    $country = get_post_meta(
        $post->ID,
        'country',
        true
    );

    $organizer_company_id = get_post_meta(
        $post->ID,
        'organizer_company_id',
        true
    );

    $companies = get_posts(
        array(
            'post_type'      => 'company',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    echo b2b_render_template(
        'admin/event-details',
        compact( 'start_date', 'end_date', 'location', 'city', 'country', 'organizer_company_id', 'companies' )
    );
}

/**
 * Save the Event Details meta box data.
 */
function b2b_save_event_details( $post_id ) {

    if (
        ! isset( $_POST['b2b_event_details_nonce'] ) ||
        ! wp_verify_nonce(
            sanitize_text_field(
                wp_unslash( $_POST['b2b_event_details_nonce'] )
            ),
            'b2b_save_event_details'
        )
    ) {
        return;
    }

    if (
        defined( 'DOING_AUTOSAVE' ) &&
        DOING_AUTOSAVE
    ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = array(
        'start_date',
        'end_date',
        'location',
        'city',
        'country',
    );

    foreach ( $fields as $field ) {

        if ( isset( $_POST[ $field ] ) ) {

            $value = sanitize_text_field(
                wp_unslash( $_POST[ $field ] )
            );

            update_post_meta(
                $post_id,
                $field,
                $value
            );
        }
    }

    if ( isset( $_POST['organizer_company_id'] ) ) {

        $organizer_company_id = absint(
            $_POST['organizer_company_id']
        );

        if (
            $organizer_company_id > 0 &&
            'company' === get_post_type( $organizer_company_id )
        ) {
            update_post_meta(
                $post_id,
                'organizer_company_id',
                $organizer_company_id
            );
        } else {
            delete_post_meta(
                $post_id,
                'organizer_company_id'
            );
        }
    }
}

add_action(
    'save_post_event',
    'b2b_save_event_details'
);
