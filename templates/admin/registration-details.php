<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<p><strong>Event:</strong> <?php echo esc_html( $event_title ); ?></p>
<p><strong>Status:</strong> <?php echo esc_html( $status ?: 'pending' ); ?></p>
<hr>
<h3>Submitted Answers</h3>
<?php if ( ! $has_answers ) : ?>
    <p>No answers available.</p>
<?php else : ?>
    <?php echo b2b_render_template( 'registration-answers', compact( 'answer_rows' ) ); ?>
<?php endif; ?>
