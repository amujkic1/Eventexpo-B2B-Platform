<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ee-grid ee-two-column">
    <section><h2>Registration fields</h2>
        <?php if ( $registration_fields ) : ?><ul class="ee-field-list">
            <?php foreach ( $registration_fields as $field ) : ?>
            <li><div><strong><?php echo esc_html( $field['label'] ); ?></strong><p class="ee-muted ee-small"><?php echo esc_html( ucfirst( $field['type'] ) ); ?></p></div><span class="ee-badge"><?php echo ! empty( $field['required'] ) ? 'Required' : 'Optional'; ?></span></li>
            <?php endforeach; ?>
        </ul><?php else : ?><div class="ee-empty"><?php echo b2b_icon( 'plus' ); ?><h2>No fields yet</h2><p>No registration fields have been added.</p></div><?php endif; ?>
    </section>
    <aside class="ee-card"><h2>Add a field</h2><form method="post" action="#ee-registration-fields" class="ee-form" style="margin-top:20px">
        <?php wp_nonce_field( 'b2b_add_registration_field_' . $event_id, 'b2b_registration_nonce' ); ?>
        <input type="hidden" name="b2b_add_registration_field" value="1">
        <div class="ee-field"><label for="field_label">Field label <span aria-hidden="true">*</span></label><input type="text" id="field_label" name="field_label" required></div>
        <div class="ee-field"><label for="field_type">Field type</label><select id="field_type" name="field_type"><option value="text">Text</option><option value="email">Email</option><option value="number">Number</option></select></div>
        <label class="ee-check"><input type="checkbox" name="field_required" value="1">Required</label>
        <button class="ee-button" type="submit"><?php echo b2b_icon( 'plus' ); ?>Add field</button>
    </form></aside>
</div>
