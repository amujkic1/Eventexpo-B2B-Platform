<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<?php if ( $registration_results['counts']['total'] ) : ?>
<div class="ee-panel">
    <form method="get" action="#ee-registrations" class="ee-registration-toolbar">
        <input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">
        <?php if ( isset( $_GET['page_id'] ) ) : ?><input type="hidden" name="page_id" value="<?php echo esc_attr( absint( $_GET['page_id'] ) ); ?>"><?php endif; ?>
        <label class="ee-search"><span class="screen-reader-text">Search registration answers or number</span><?php echo b2b_icon( 'search' ); ?><input type="search" name="registration_search" value="<?php echo esc_attr( $registration_search ); ?>" placeholder="Search answers or #number…"></label>
        <label><span class="screen-reader-text">Registration status</span><select name="registration_status"><?php foreach ( array( '' => 'All statuses', 'approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $registration_status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
        <button class="ee-button" type="submit">Search</button>
    </form>
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
                    <form method="post" action="<?php echo esc_url( $registration_url . '#ee-registrations' ); ?>">
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
    <?php if ( ! $registration_rows ) : ?><p class="ee-filter-empty">No registrations match your filters.</p><?php endif; ?>
</div>
<p class="ee-muted ee-small" style="margin-top:12px"><?php echo esc_html( count( $registration_rows ) ); ?> shown · <?php echo esc_html( $registration_results['total'] ); ?> matching registrations</p>
<?php if ( $registration_results['pages'] > 1 ) : ?><nav class="ee-actions" aria-label="Registration pages" style="margin-top:16px">
    <?php if ( $registration_results['page'] > 1 ) : ?><a class="ee-button ee-button-outline" href="<?php echo esc_url( add_query_arg( 'registration_page', $registration_results['page'] - 1, $registration_url ) . '#ee-registrations' ); ?>">Previous</a><?php endif; ?>
    <span>Page <?php echo esc_html( $registration_results['page'] ); ?> of <?php echo esc_html( $registration_results['pages'] ); ?></span>
    <?php if ( $registration_results['page'] < $registration_results['pages'] ) : ?><a class="ee-button ee-button-outline" href="<?php echo esc_url( add_query_arg( 'registration_page', $registration_results['page'] + 1, $registration_url ) . '#ee-registrations' ); ?>">Next</a><?php endif; ?>
</nav><?php endif; ?>
<?php else : ?><div class="ee-empty"><?php echo b2b_icon( 'users' ); ?><h2>No registrations yet</h2><p>Submitted registrations will appear here.</p></div><?php endif; ?>
