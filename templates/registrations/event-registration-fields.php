<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$field_failure = $field_failure ?? array();
?>
<div class="ee-grid ee-two-column">
    <section><h2>Registration fields</h2>
        <p class="ee-muted ee-small">Changes apply to future submissions. Existing answers are preserved.</p>
        <?php if ( $registration_fields ) : ?><ul class="ee-field-list ee-builder-fields">
            <?php foreach ( $registration_fields as $field ) :
                $name = $field['name'];
                $failed_edit = ( $field_failure['name'] ?? '' ) === $name && 'edit' === ( $field_failure['action'] ?? '' );
                $edit = $failed_edit ? $field_failure['values'] : $field;
                $input_id = 'ee-edit-' . $name;
            ?>
            <li>
                <div class="ee-field-summary"><div><strong><?php echo esc_html( $field['label'] ); ?></strong><p class="ee-muted ee-small"><?php echo esc_html( ucfirst( $field['type'] ) ); ?></p></div><span class="ee-badge"><?php echo ! empty( $field['required'] ) ? 'Required' : 'Optional'; ?></span></div>
                <div class="ee-field-controls">
                    <details class="ee-field-editor" <?php echo $failed_edit ? 'open' : ''; ?>>
                        <summary>Edit <span class="screen-reader-text"><?php echo esc_html( $field['label'] ); ?></span></summary>
                        <form method="post" action="#ee-registration-fields" class="ee-form">
                            <input type="hidden" name="b2b_field_nonce" value="<?php echo esc_attr( wp_create_nonce( 'b2b_registration_field_' . $event_id . '_edit_' . $name ) ); ?>">
                            <input type="hidden" name="b2b_field_action" value="edit">
                            <input type="hidden" name="field_name" value="<?php echo esc_attr( $name ); ?>">
                            <div class="ee-field"><label for="<?php echo esc_attr( $input_id ); ?>-label">Field label</label><input id="<?php echo esc_attr( $input_id ); ?>-label" name="field_label" value="<?php echo esc_attr( $edit['label'] ); ?>" required></div>
                            <div class="ee-field"><label for="<?php echo esc_attr( $input_id ); ?>-type">Field type</label><select id="<?php echo esc_attr( $input_id ); ?>-type" name="field_type"><?php foreach ( array( 'text', 'email', 'number' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $edit['type'], $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option><?php endforeach; ?></select></div>
                            <label class="ee-check"><input type="checkbox" name="field_required" value="1" <?php checked( ! empty( $edit['required'] ) ); ?>>Required</label>
                            <p class="ee-muted ee-small">Type and required changes affect new submissions only. Historical answers keep their original values.</p>
                            <button type="submit" class="ee-button">Save field</button>
                        </form>
                    </details>
                    <details class="ee-field-delete" <?php echo ( $field_failure['name'] ?? '' ) === $name && 'delete' === ( $field_failure['action'] ?? '' ) ? 'open' : ''; ?>>
                        <summary>Delete <span class="screen-reader-text"><?php echo esc_html( $field['label'] ); ?></span></summary>
                        <form method="post" action="#ee-registration-fields" class="ee-form">
                            <input type="hidden" name="b2b_field_nonce" value="<?php echo esc_attr( wp_create_nonce( 'b2b_registration_field_' . $event_id . '_delete_' . $name ) ); ?>">
                            <input type="hidden" name="b2b_field_action" value="delete">
                            <input type="hidden" name="field_name" value="<?php echo esc_attr( $name ); ?>">
                            <p>Remove “<?php echo esc_html( $field['label'] ); ?>” from future registrations? Existing answers will remain available.</p>
                            <label class="ee-check"><input type="checkbox" name="confirm_delete" value="1" required>I confirm deletion of this field.</label>
                            <button type="submit" class="ee-button">Delete field</button>
                        </form>
                    </details>
                </div>
            </li>
            <?php endforeach; ?>
        </ul><?php else : ?><div class="ee-empty"><?php echo b2b_icon( 'plus' ); ?><h2>No fields yet</h2><p>No registration fields have been added.</p></div><?php endif; ?>
    </section>
    <?php $add = 'add' === ( $field_failure['action'] ?? '' ) ? $field_failure['values'] : array( 'label' => '', 'type' => 'text', 'required' => false ); ?>
    <aside class="ee-card"><h2>Add a field</h2><form method="post" action="#ee-registration-fields" class="ee-form ee-builder-add">
        <input type="hidden" name="b2b_field_nonce" value="<?php echo esc_attr( wp_create_nonce( 'b2b_registration_field_' . $event_id . '_add_' ) ); ?>">
        <input type="hidden" name="b2b_field_action" value="add">
        <div class="ee-field"><label for="field_label">Field label <span aria-hidden="true">*</span></label><input type="text" id="field_label" name="field_label" value="<?php echo esc_attr( $add['label'] ); ?>" required></div>
        <div class="ee-field"><label for="field_type">Field type</label><select id="field_type" name="field_type"><?php foreach ( array( 'text', 'email', 'number' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $add['type'], $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option><?php endforeach; ?></select></div>
        <label class="ee-check"><input type="checkbox" name="field_required" value="1" <?php checked( ! empty( $add['required'] ) ); ?>>Required</label>
        <button class="ee-button" type="submit"><?php echo b2b_icon( 'plus' ); ?>Add field</button>
    </form></aside>
</div>
