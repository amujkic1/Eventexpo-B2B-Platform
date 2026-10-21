<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
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

<?php
echo b2b_render_template( 'event-registration-fields', compact( 'event_id', 'registration_fields' ) );
echo b2b_render_template( 'event-registrations', compact( 'registration_rows' ) );
?>
