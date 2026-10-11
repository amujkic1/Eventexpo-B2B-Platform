<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get registration fields for an event.
 *
 * @param int $event_id Event ID.
 * @return array
 */
function b2b_get_event_registration_fields( $event_id ) {

    $fields = get_post_meta(
        $event_id,
        'registration_fields',
        true
    );

    if ( ! is_array( $fields ) ) {
        return array();
    }

    return $fields;
}

/**
 * Handle event registration before page rendering.
 */
function b2b_handle_event_registration_request() {

    if (
        ! isset( $_SERVER['REQUEST_METHOD'] ) ||
        'POST' !== $_SERVER['REQUEST_METHOD'] ||
        ! isset( $_POST['b2b_submit_registration'] )
    ) {
        return;
    }

    $event_id = isset( $_POST['b2b_registration_event_id'] )
        ? absint( $_POST['b2b_registration_event_id'] )
        : 0; 

    if ( ! $event_id ) {
        return;
    }

    $fields = b2b_get_event_registration_fields( $event_id );
    $message = '';

    if (
        isset( $_SERVER['REQUEST_METHOD'] ) &&
        'POST' === $_SERVER['REQUEST_METHOD'] &&
        isset( $_POST['b2b_submit_registration'] )
    ) {
        $nonce = isset( $_POST['b2b_registration_submit_nonce'] )
            && is_string( $_POST['b2b_registration_submit_nonce'] )
            ? sanitize_text_field(
                wp_unslash( $_POST['b2b_registration_submit_nonce'] )
            )
            : '';

        $submitted_event_id = isset( $_POST['b2b_registration_event_id'] )
            ? absint( $_POST['b2b_registration_event_id'] )
            : 0;

        if (
            $submitted_event_id !== (int) $event_id ||
            ! wp_verify_nonce(
                $nonce,
                'b2b_submit_registration_' . $event_id
            )
        ) {
            $message = 'Security check failed.';
        } elseif (
            'event' !== get_post_type( $event_id ) ||
            'publish' !== get_post_status( $event_id )
        ) {
            $message = 'Registration is not available.';
        } elseif ( empty( $fields ) ) {
            $message = 'Registration is not available.';
        } else {

            $answers = array();
            $errors  = array();

            foreach ( $fields as $field ) {

                if (
                    ! is_array( $field ) ||
                    empty( $field['name'] ) ||
                    empty( $field['type'] )
                ) {
                    continue;
                }

                $name = sanitize_key( $field['name'] );
                $type = $field['type'];

                if ( ! in_array( $type, array( 'text', 'email', 'number' ), true ) ) {
                    continue;
                }

                $raw_value = isset( $_POST[ $name ] ) &&
                    is_string( $_POST[ $name ] )
                    ? wp_unslash( $_POST[ $name ] )
                    : '';

                $value = sanitize_text_field( $raw_value );

                if ( ! empty( $field['required'] ) && '' === $value ) {
                    $errors[] = 'Please complete all required fields.';
                    break;
                }

                if ( '' !== $value ) {
                    if ( 'email' === $type && ! is_email( $value ) ) {
                        $errors[] = 'Please enter a valid email address.';
                        break;
                    }

                    if ( 'number' === $type && ! is_numeric( $value ) ) {
                        $errors[] = 'Please enter a valid number.';
                        break;
                    }
                }

                $answers[ $name ] = $value;
            }

            if ( ! empty( $errors ) ) {
                $message = $errors[0];
            } else {

                $result = b2b_save_event_registration(
                    $event_id,
                    $answers
                );

            if ( is_wp_error( $result ) ) {

                $message = 'Could not save registration.';

            } else {

                $redirect_url = add_query_arg(
                    'registration_success',
                    '1',
                    get_permalink( $event_id )
                );

                wp_safe_redirect( $redirect_url, 303 );
                exit;
            }
            }
        }
    }

    if ( ! empty( $message ) ) {

    $notice_id = wp_generate_uuid4();

    set_transient(
        'b2b_registration_notice_' . $notice_id,
        $message,
        5 * MINUTE_IN_SECONDS
    );

    $redirect_url = add_query_arg(
        'registration_notice',
        $notice_id,
        get_permalink( $event_id )
    );

    wp_safe_redirect( $redirect_url, 303 );
    exit;
}
}

add_action(
    'template_redirect',
    'b2b_handle_event_registration_request'
);

/**
 * Render the registration form for an event.
 */
function b2b_render_event_registration_form( $event_id ) {

    $fields = b2b_get_event_registration_fields( $event_id );

    $message = '';

    if (
        isset( $_GET['registration_success'] ) &&
        '1' === $_GET['registration_success']
    ) {
        $message = 'Registration submitted successfully.';
    }

    if ( isset( $_GET['registration_notice'] ) ) {

        $notice_id = sanitize_text_field(
            wp_unslash( $_GET['registration_notice'] )
        );

        $transient_key = 'b2b_registration_notice_' . $notice_id;

        $notice = get_transient( $transient_key );

        if ( false !== $notice ) {
            $message = $notice;
            delete_transient( $transient_key );
        }
    }
    
    if ( empty( $fields ) ) {
        return '<p>Registration is not available yet.</p>';
    }

    ob_start();
    ?>

    <div class="b2b-event-registration">

        <h2>Register for this Event</h2>

        <?php if ( ! empty( $message ) ) : ?>
            <p><?php echo esc_html( $message ); ?></p>
        <?php endif; ?>

        <form method="post">

            <?php
                wp_nonce_field(
                    'b2b_submit_registration_' . $event_id,
                    'b2b_registration_submit_nonce'
                );
                ?>

                <input
                    type="hidden"
                    name="b2b_registration_event_id"
                    value="<?php echo esc_attr( $event_id ); ?>"
                >

                <input
                    type="hidden"
                    name="b2b_submit_registration"
                    value="1"
            >

            <?php foreach ( $fields as $field ) : ?>

                <?php
                $name = isset( $field['name'] )
                    ? sanitize_key( $field['name'] )
                    : '';

                $label = isset( $field['label'] )
                    ? $field['label']
                    : '';

                $type = isset( $field['type'] )
                    ? $field['type']
                    : 'text';

                $required = ! empty( $field['required'] );

                if (
                    ! $name ||
                    ! in_array( $type, array( 'text', 'email', 'number' ), true )
                ) {
                    continue;
                }
                ?>

                <p>
                    <label for="<?php echo esc_attr( $name ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </label>

                    <input
                        type="<?php echo esc_attr( $type ); ?>"
                        id="<?php echo esc_attr( $name ); ?>"
                        name="<?php echo esc_attr( $name ); ?>"
                        <?php echo $required ? 'required' : ''; ?>
                    >
                </p>

            <?php endforeach; ?>

            <p>
                <button type="submit">
                    Submit Registration
                </button>
            </p>

        </form>

    </div>

    <?php
    return ob_get_clean();
}