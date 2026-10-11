<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * List the events the current user can manage.
 */
function b2b_manage_event_list() {

    $events = b2b_get_manageable_events( get_current_user_id() );

    ob_start();
    ?>

    <div class="b2b-manage-event-list">

        <?php if ( ! empty( $events ) ) : ?>

            <p>Select an event to manage:</p>

            <ul>

                <?php foreach ( $events as $event ) : ?>

                    <?php $start_date = get_post_meta( $event->ID, 'start_date', true ); ?>

                    <li>
                        <a href="<?php echo esc_url( b2b_get_manage_event_url( $event->ID ) ); ?>">
                            <?php echo esc_html( $event->post_title ); ?>
                        </a>

                        <?php if ( ! empty( $start_date ) ) : ?>
                            (<?php echo esc_html( $start_date ); ?>)
                        <?php endif; ?>

                        <?php if ( 'publish' !== $event->post_status ) : ?>
                            <em><?php echo esc_html( ucfirst( $event->post_status ) ); ?></em>
                        <?php endif; ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        <?php else : ?>

            <p>You don't have any events to manage.</p>

        <?php endif; ?>

    </div>

    <?php
    return ob_get_clean();
}

/**
 * Frontend event management.
 */
function b2b_manage_event_shortcode() {

    if ( ! is_user_logged_in() ) {
        return '<p>Please log in to manage an event.</p>';
    }

    $event_id = isset( $_GET['event_id'] )
        ? absint( $_GET['event_id'] )
        : 0;

    if ( ! $event_id ) {
        return b2b_manage_event_list();
    }

    if ( get_post_type( $event_id ) !== 'event' ) {
        return '<p>Invalid event.</p>';
    }

    if ( ! b2b_user_can_manage_event(
        get_current_user_id(),
        $event_id
    ) ) {
        return '<p>Access denied.</p>';
    }

    $event_types = get_terms(
        array(
            'taxonomy'   => 'event_type',
            'hide_empty' => false,
        )
    );

    if ( is_wp_error( $event_types ) ) {
        $event_types = array();
    }

    $message = '';

    if (
    isset( $_SERVER['REQUEST_METHOD'] ) &&
    'POST' === $_SERVER['REQUEST_METHOD'] &&
    isset( $_POST['b2b_update_registration_status'] )
) {
    $registration_id = isset( $_POST['registration_id'] )
        ? absint( $_POST['registration_id'] )
        : 0;

    $new_status = isset( $_POST['new_status'] )
        && is_string( $_POST['new_status'] )
        ? sanitize_key( wp_unslash( $_POST['new_status'] ) )
        : '';

    $nonce = isset( $_POST['b2b_status_nonce'] )
        && is_string( $_POST['b2b_status_nonce'] )
        ? sanitize_text_field(
            wp_unslash( $_POST['b2b_status_nonce'] )
        )
        : '';

    if (
        ! wp_verify_nonce(
            $nonce,
            'b2b_update_registration_' . $registration_id
        )
    ) {
        $message = 'Security check failed.';
    } elseif (
        (int) get_post_meta( $registration_id, 'event_id', true )
        !== (int) $event_id
    ) {
        $message = 'Invalid registration for this event.';
    } else {
        $result = b2b_update_registration_status(
            $registration_id,
            $new_status
        );

        $message = is_wp_error( $result )
            ? $result->get_error_message()
            : 'Registration status updated successfully.';
    }
}

    /*
     * Check and process form submission.
     */
    if (
        isset( $_SERVER['REQUEST_METHOD'] ) &&
        'POST' === $_SERVER['REQUEST_METHOD'] &&
        isset( $_POST['b2b_save_event'] )
    ) {

        $nonce = isset( $_POST['b2b_event_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['b2b_event_nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'b2b_save_event_' . $event_id ) ) {
            return '<p>Security check failed.</p>';
        }

        $event_title = isset( $_POST['event_title'] ) &&
            is_string( $_POST['event_title'] )
            ? sanitize_text_field( wp_unslash( $_POST['event_title'] ) )
            : '';

        $event_description = isset( $_POST['event_description'] )
            ? wp_kses_post(
                wp_unslash( $_POST['event_description'] )
            )
            : '';

        $event_type_id = isset( $_POST['event_type'] )
            ? absint( $_POST['event_type'] )
            : 0;

        $meta = array();

        foreach ( array( 'start_date', 'end_date', 'location', 'city', 'country' ) as $field ) {

            $meta[ $field ] = isset( $_POST[ $field ] )
                ? sanitize_text_field(
                    wp_unslash( $_POST[ $field ] )
                )
                : '';
        }

        foreach ( array( 'start_date', 'end_date' ) as $date_field ) {

            if (
                '' !== $meta[ $date_field ] &&
                ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $meta[ $date_field ] )
            ) {
                $meta[ $date_field ] = '';
            }
        }

        if ( '' === $event_title ) {

            $message = 'Event title is required.';

        } elseif (
            '' !== $meta['start_date'] &&
            '' !== $meta['end_date'] &&
            $meta['end_date'] < $meta['start_date']
        ) {

            $message = 'End date cannot be before the start date.';

        } else {

            $result = wp_update_post(
                array(
                    'ID'           => $event_id,
                    'post_title'   => $event_title,
                    'post_content' => $event_description,
                ),
                true
            );

            if ( is_wp_error( $result ) ) {

                $message = 'Could not save event.';

            } else {

                foreach ( $meta as $key => $value ) {

                    update_post_meta(
                        $event_id,
                        $key,
                        $value
                    );
                }

                if (
                    $event_type_id &&
                    term_exists( $event_type_id, 'event_type' )
                ) {

                    wp_set_object_terms(
                        $event_id,
                        array( $event_type_id ),
                        'event_type'
                    );

                } else {

                    wp_set_object_terms(
                        $event_id,
                        array(),
                        'event_type'
                    );
                }

                $message = 'Event updated successfully.';
            }
        }
    }

    // Load the event after a possible update.
    $data  = b2b_get_event_data( $event_id );
    $event = $data['event'];

    $current_event_types = wp_get_post_terms(
        $event_id,
        'event_type',
        array(
            'fields' => 'ids',
        )
    );

    $selected_event_type = ! empty( $current_event_types ) && ! is_wp_error( $current_event_types )
        ? $current_event_types[0]
        : 0;

    if (
        'POST' === $_SERVER['REQUEST_METHOD'] &&
        isset( $_POST['b2b_add_registration_field'] )
    ) {
        $nonce = isset( $_POST['b2b_registration_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['b2b_registration_nonce'] ) )
            : '';

        if (
            ! wp_verify_nonce(
                $nonce,
                'b2b_add_registration_field_' . $event_id
            )
        ) {
            return '<p>Security check failed.</p>';
        }

        $label = isset( $_POST['field_label'] ) &&
            is_string( $_POST['field_label'] )
            ? sanitize_text_field( wp_unslash( $_POST['field_label'] ) )
            : '';

        $type = isset( $_POST['field_type'] ) &&
            is_string( $_POST['field_type'] )
            ? sanitize_key( wp_unslash( $_POST['field_type'] ) )
            : '';

        $allowed_types = array( 'text', 'email', 'number' );

        if ( '' === $label || ! in_array( $type, $allowed_types, true ) ) {
            return '<p>Invalid registration field.</p>';
        }

        $fields = b2b_get_event_registration_fields( $event_id );

        $field_name = 'field_' . str_replace(
            '-',
            '',
            wp_generate_uuid4()
        );

        $fields[] = array(
            'name'     => $field_name,
            'label'    => $label,
            'type'     => $type,
            'required' => ! empty( $_POST['field_required'] ),
        );

        update_post_meta(
            $event_id,
            'registration_fields',
            $fields
        );

        $message = 'Registration field added successfully.';
    }

    ob_start();
    ?>

    <div class="b2b-manage-event">

        <h2>Manage: <?php echo esc_html( $event->post_title ); ?></h2>

        <p>
            <a href="<?php echo esc_url( get_permalink( $event_id ) ); ?>">
                View Event
            </a>
        </p>

        <?php if ( ! empty( $message ) ) : ?>

            <p>
                <?php echo esc_html( $message ); ?>
            </p>

        <?php endif; ?>

        <form method="post">

            <?php wp_nonce_field( 'b2b_save_event_' . $event_id, 'b2b_event_nonce' ); ?>

            <input type="hidden" name="b2b_save_event" value="1">

            <p>
                <label for="event_title">
                    <strong>Event Title</strong>
                </label>
            </p>

            <p>
                <input
                    type="text"
                    id="event_title"
                    name="event_title"
                    value="<?php echo esc_attr( $event->post_title ); ?>"
                    required
                >
            </p>

            <p>
                <label for="event_description">
                    <strong>Description</strong>
                </label>
            </p>

            <p>
                <textarea
                    id="event_description"
                    name="event_description"
                    rows="8"
                ><?php echo esc_textarea( $event->post_content ); ?></textarea>
            </p>

            <p>
                <label for="event_type">
                    <strong>Event Type</strong>
                </label>
            </p>

            <p>
                <select
                    id="event_type"
                    name="event_type"
                >

                    <option value="">
                        Select Event Type
                    </option>

                    <?php foreach ( $event_types as $event_type ) : ?>

                        <option
                            value="<?php echo esc_attr( $event_type->term_id ); ?>"
                            <?php selected( $selected_event_type, $event_type->term_id ); ?>
                        >
                            <?php echo esc_html( $event_type->name ); ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </p>

            <p>
                <label for="start_date">
                    <strong>Start Date</strong>
                </label>
            </p>

            <p>
                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?php echo esc_attr( $data['start_date'] ); ?>"
                >
            </p>

            <p>
                <label for="end_date">
                    <strong>End Date</strong>
                </label>
            </p>

            <p>
                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?php echo esc_attr( $data['end_date'] ); ?>"
                >
            </p>

            <p>
                <label for="location">
                    <strong>Location</strong>
                </label>
            </p>

            <p>
                <input
                    type="text"
                    id="location"
                    name="location"
                    value="<?php echo esc_attr( $data['location'] ); ?>"
                >
            </p>

            <p>
                <label for="city">
                    <strong>City</strong>
                </label>
            </p>

            <p>
                <input
                    type="text"
                    id="city"
                    name="city"
                    value="<?php echo esc_attr( $data['city'] ); ?>"
                >
            </p>

            <p>
                <label for="country">
                    <strong>Country</strong>
                </label>
            </p>

            <p>
                <input
                    type="text"
                    id="country"
                    name="country"
                    value="<?php echo esc_attr( $data['country'] ); ?>"
                >
            </p>

            <p>
                <button type="submit">Save Changes</button>
            </p>

        </form>

    </div>

    <h2>Registration Form Builder</h2>

    <?php
        $registration_fields = b2b_get_event_registration_fields( $event_id );
        ?>

        <?php if ( ! empty( $registration_fields ) ) : ?>

            <h3>Existing Registration Fields</h3>

            <ul>
                <?php foreach ( $registration_fields as $field ) : ?>

                    <li>
                        <strong>
                            <?php echo esc_html( $field['label'] ); ?>
                        </strong>

                        — <?php echo esc_html( $field['type'] ); ?>

                        <?php if ( ! empty( $field['required'] ) ) : ?>
                            (Required)
                        <?php endif; ?>
                    </li>

                <?php endforeach; ?>
            </ul>

        <?php else : ?>

            <p>No registration fields added yet.</p>

        <?php endif; ?>

<form method="post">

    <?php
    wp_nonce_field(
        'b2b_add_registration_field_' . $event_id,
        'b2b_registration_nonce'
    );
    ?>

    <input
        type="hidden"
        name="b2b_add_registration_field"
        value="1"
    >

    <p>
        <label for="field_label">Field Label</label>
        <input
            type="text"
            id="field_label"
            name="field_label"
            required
        >
    </p>

    <p>
        <label for="field_type">Field Type</label>
        <select id="field_type" name="field_type">
            <option value="text">Text</option>
            <option value="email">Email</option>
            <option value="number">Number</option>
        </select>
    </p>

    <p>
        <label>
            <input type="checkbox" name="field_required" value="1">
            Required
        </label>
    </p>

    <button type="submit">Add Field</button>

</form>

<h2>Event Registrations</h2>

<?php
$registrations = b2b_get_event_registrations( $event_id );
?>

<?php if ( ! empty( $registrations ) ) : ?>

    <table>
        <thead>
            <tr>
                <th>Registration ID</th>
                <th>Date</th>
                <th>Status</th>
                <th>Answers</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ( $registrations as $registration ) : ?>

                <?php
                $status = get_post_meta(
                    $registration->ID,
                    'registration_status',
                    true
                );
                ?>

                <tr>
                    <td>
                        <?php echo esc_html( $registration->ID ); ?>
                    </td>

                    <td>
                        <?php
                        echo esc_html(
                            get_the_date( 'Y-m-d H:i', $registration )
                        );
                        ?>
                    </td>

                    <td>
                        <?php echo esc_html( $status ?: 'pending' ); ?>
                    </td>

                    <td>
                        <?php
                        $answers = get_post_meta(
                            $registration->ID,
                            'registration_answers',
                            true
                        );

                        $fields = b2b_get_event_registration_fields( $event_id );

                        if ( is_array( $answers ) ) {

                            foreach ( $fields as $field ) {

                                if (
                                    ! is_array( $field ) ||
                                    empty( $field['name'] )
                                ) {
                                    continue;
                                }

                                $name = sanitize_key( $field['name'] );

                                $label = isset( $field['label'] )
                                    ? $field['label']
                                    : $name;

                                $value = isset( $answers[ $name ] )
                                    && is_scalar( $answers[ $name ] )
                                    ? (string) $answers[ $name ]
                                    : '';

                                echo '<p><strong>' .
                                    esc_html( $label ) .
                                    ':</strong> ' .
                                    esc_html( $value ) .
                                    '</p>';
                            }
                        }
                        ?>
                    </td>

                    <td>
    <?php foreach ( array(
        'approved' => 'Approve',
        'rejected' => 'Reject',
        'pending' => 'Reset to Pending'
    ) as $action_status => $button_label ) : ?>

        <form method="post" style="display:inline-block; margin:4px;">

            <?php
            wp_nonce_field(
                'b2b_update_registration_' . $registration->ID,
                'b2b_status_nonce'
            );
            ?>

            <input
                type="hidden"
                name="b2b_update_registration_status"
                value="1"
            >

            <input
                type="hidden"
                name="registration_id"
                value="<?php echo esc_attr( $registration->ID ); ?>"
            >

            <input
                type="hidden"
                name="new_status"
                value="<?php echo esc_attr( $action_status ); ?>"
            >

            <button
                type="submit"
                <?php disabled( $status, $action_status ); ?>
            >
                <?php echo esc_html( $button_label ); ?>
            </button>

        </form>

    <?php endforeach; ?>
</td>
                </tr>

            <?php endforeach; ?>
        </tbody>
    </table>

<?php else : ?>

    <p>No registrations yet.</p>

<?php endif; ?>

    <?php
    return ob_get_clean();
}

add_shortcode( 'b2b_manage_event', 'b2b_manage_event_shortcode' );
