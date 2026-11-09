<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<section class="ee-card ee-registration-form">
    <h2>Register for this event</h2>
    <?php if ( $message ) : ?><div class="ee-notice" role="status"><?php echo esc_html( $message ); ?></div><?php endif; ?>
    <form method="post" class="ee-form">
        <?php wp_nonce_field( 'b2b_submit_registration_' . $event_id, 'b2b_registration_submit_nonce' ); ?>
        <input type="hidden" name="b2b_registration_event_id" value="<?php echo esc_attr( $event_id ); ?>">
        <input type="hidden" name="b2b_submit_registration" value="1">
        <?php foreach ( $fields as $field ) :
            $name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
            $label = $field['label'] ?? '';
            $type = $field['type'] ?? 'text';
            $required = ! empty( $field['required'] );
            if ( ! $name || ! in_array( $type, array( 'text', 'email', 'number' ), true ) ) { continue; }
        ?>
        <div class="ee-field"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?><?php if ( $required ) : ?> <span aria-hidden="true">*</span><?php endif; ?></label><input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo $required ? 'required' : ''; ?>></div>
        <?php endforeach; ?>
        <button class="ee-button" type="submit">Submit registration <?php echo b2b_icon( 'arrow-right' ); ?></button>
    </form>
</section>
