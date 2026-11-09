<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php foreach ( $answer_rows as $answer ) : ?>
<p><strong><?php echo esc_html( $answer['label'] ); ?>:</strong> <?php echo esc_html( $answer['value'] ); ?></p>
<?php endforeach; ?>
