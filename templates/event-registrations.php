<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<h2>Event Registrations</h2>

<?php if ( ! empty( $registration_rows ) ) : ?>
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
            <?php foreach ( $registration_rows as $registration ) : ?>
                <tr>
                    <td><?php echo esc_html( $registration['id'] ); ?></td>
                    <td><?php echo esc_html( $registration['date'] ); ?></td>
                    <td><?php echo esc_html( $registration['status'] ?: 'pending' ); ?></td>
                    <td>
                        <?php echo b2b_render_template( 'registration-answers', array( 'answer_rows' => $registration['answers'] ) ); ?>
                    </td>
                    <td>
                        <?php foreach ( array(
                            'approved' => 'Approve',
                            'rejected' => 'Reject',
                            'pending'  => 'Reset to Pending',
                        ) as $action_status => $button_label ) : ?>
                            <form method="post" style="display:inline-block; margin:4px;">
                                <?php wp_nonce_field( 'b2b_update_registration_' . $registration['id'], 'b2b_status_nonce' ); ?>
                                <input type="hidden" name="b2b_update_registration_status" value="1">
                                <input type="hidden" name="registration_id" value="<?php echo esc_attr( $registration['id'] ); ?>">
                                <input type="hidden" name="new_status" value="<?php echo esc_attr( $action_status ); ?>">
                                <button type="submit" <?php disabled( $registration['status'], $action_status ); ?>>
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
