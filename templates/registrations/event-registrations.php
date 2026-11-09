<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<?php if ( $registration_rows ) : ?>
<div class="ee-panel">
    <div class="ee-registration-toolbar"><label class="ee-search"><span class="screen-reader-text">Search registrations</span><?php echo b2b_icon( 'search' ); ?><input type="search" placeholder="Search registrations" data-ee-registration-search></label><label><span class="screen-reader-text">Registration status</span><select data-ee-registration-status><option value="">All statuses</option><option value="approved">Approved</option><option value="pending">Pending</option><option value="rejected">Rejected</option></select></label></div>
    <div class="ee-table-scroll" tabindex="0" role="region" aria-label="Event registrations">
        <table class="ee-table"><thead><tr><th>Registration</th><th>Date</th><th>Submitted answers</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ( $registration_rows as $registration ) : $status = $registration['status'] ?: 'pending'; ?>
            <tr data-ee-registration-row data-status="<?php echo esc_attr( $status ); ?>">
                <td><strong>#<?php echo esc_html( $registration['id'] ); ?></strong></td>
                <td><?php echo esc_html( $registration['date'] ); ?></td>
                <td class="ee-answer"><?php echo b2b_render_template( 'registrations/registration-answers', array( 'answer_rows' => $registration['answers'] ) ); ?></td>
                <td><span class="ee-badge ee-badge-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
                <td><div class="ee-actions">
                    <?php foreach ( array( 'approved' => array( 'Approve', 'check' ), 'rejected' => array( 'Reject', 'x' ), 'pending' => array( 'Reset to pending', 'rotate-ccw' ) ) as $action_status => $action ) : ?>
                    <form method="post" action="#ee-registrations">
                        <?php wp_nonce_field( 'b2b_update_registration_' . $registration['id'], 'b2b_status_nonce' ); ?>
                        <input type="hidden" name="b2b_update_registration_status" value="1">
                        <input type="hidden" name="registration_id" value="<?php echo esc_attr( $registration['id'] ); ?>">
                        <input type="hidden" name="new_status" value="<?php echo esc_attr( $action_status ); ?>">
                        <button type="submit" class="ee-icon-button" title="<?php echo esc_attr( $action[0] ); ?>" aria-label="<?php echo esc_attr( $action[0] . ' registration ' . $registration['id'] ); ?>" <?php disabled( $status, $action_status ); ?>><?php echo b2b_icon( $action[1] ); ?></button>
                    </form>
                    <?php endforeach; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <p class="ee-filter-empty" data-ee-filter-empty hidden>No registrations match your filters.</p>
</div>
<p class="ee-muted ee-small" style="margin-top:12px"><?php echo esc_html( count( $registration_rows ) ); ?> registrations shown</p>
<?php else : ?><div class="ee-empty"><?php echo b2b_icon( 'users' ); ?><h2>No registrations yet</h2><p>Submitted registrations will appear here.</p></div><?php endif; ?>
