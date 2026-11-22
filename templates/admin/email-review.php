<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
echo '<div class="wrap"><h1>Registration email review</h1><p>Migration preserves all historical answers and reserves every usable legacy email. Multiple possible addresses and existing duplicates require manual review. Missing addresses cannot be matched retrospectively.</p>';
if ( 'complete' === $result ) { echo '<div class="notice notice-success"><p>Migration complete. Review any records listed below.</p></div>'; }
if ( 'failed' === $result || $last_error ) {
    echo '<div class="notice notice-error"><p>Migration could not finish. Existing registrations remain intact.</p>';
    if ( is_array( $last_error ) && isset( $last_error['message'], $last_error['code'] ) ) {
        echo '<p><strong>Reason:</strong> ' . esc_html( $last_error['message'] ) . ' <code>' . esc_html( $last_error['code'] ) . '</code></p>';
    }
    echo '</div>';
}
echo '<h2>Database checks (read only)</h2><ul>';
foreach ( $storage_diagnostics as $role => $engine ) {
    echo '<li>' . esc_html( $role ) . ': <strong>' . esc_html( $engine ) . '</strong></li>';
}
echo '</ul><p>All three tables must use InnoDB. An unavailable email index before the first successful installation may indicate missing CREATE permissions. These checks do not alter table engines.</p>';
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
wp_nonce_field( 'b2b_migrate_emails' );
echo '<input type="hidden" name="action" value="b2b_migrate_emails">';
submit_button( 'Run or resume email migration' );
echo '</form><p>For large sites use <code>wp eventexpo registrations-migrate</code>. Pause other registration imports and writers during migration.</p>';
echo '<h2>Records requiring review (' . esc_html( $total ) . ')</h2><ul>';
foreach ( $review_rows as $row ) {
    echo '<li><a href="' . esc_url( $row['edit_url'] ) . '">Registration ' . esc_html( $row['id'] ) . '</a>: ' . esc_html( $row['issues'] ) . '</li>';
}
echo '</ul>';
if ( $previous_url ) { echo '<a href="' . esc_url( $previous_url ) . '">Previous</a> '; }
if ( $next_url ) { echo '<a href="' . esc_url( $next_url ) . '">Next</a>'; }
echo '</div>';
