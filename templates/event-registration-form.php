<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
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
