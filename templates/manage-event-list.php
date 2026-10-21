<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

    <div class="b2b-manage-event-list">

        <?php if ( ! empty( $events ) ) : ?>

            <p>Select an event to manage:</p>

            <ul>

                <?php foreach ( $events as $event ) : ?>

                    <?php $start_date = $event_dates[ $event->ID ]; ?>

                    <li>
                        <a href="<?php echo esc_url( b2b_get_manage_event_url( $event->ID ) ); ?>">
                            <?php echo esc_html( $event->post_title ); ?>
                        </a>

                        <?php if ( ! empty( $start_date ) ) : ?>
                            (<?php echo esc_html( $start_date ); ?>)
                        <?php endif; ?>

                        <?php if ( 'publish' !== $event->post_status ) : ?>
                            <em><?php echo esc_html( ucfirst( $event->post_status ) ); ?></em>
                        <?php endif; ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        <?php else : ?>

            <p>You don't have any events to manage.</p>

        <?php endif; ?>

    </div>
