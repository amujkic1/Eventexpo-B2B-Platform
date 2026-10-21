<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>    <h2>Registration Form Builder</h2>

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
