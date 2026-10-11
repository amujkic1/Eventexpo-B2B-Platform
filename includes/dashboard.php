<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the B2B user dashboard.
 */
function b2b_dashboard() {

    if ( ! is_user_logged_in() ) {
        return '<p>Please log in to access your B2B dashboard.</p>';
    }

    $user = wp_get_current_user();

    $company_id = get_user_meta(
        $user->ID,
        'company_id',
        true
    );

    $events = $company_id
        ? b2b_get_manageable_events( $user->ID )
        : array();

    ob_start();
    ?>

    <div class="b2b-dashboard">

        <h2>
            Welcome, <?php echo esc_html( $user->first_name ); ?>
        </h2>

        <?php if ( $company_id ) : ?>

            <p>
                Your company:
                <strong>
                    <?php echo esc_html( get_the_title( $company_id ) ); ?>
                </strong>
            </p>

            <h3>My Events</h3>

            <?php if ( ! empty( $events ) ) : ?>

                <ul class="b2b-dashboard-events">

                    <?php foreach ( $events as $event ) : ?>

                        <?php $start_date = get_post_meta( $event->ID, 'start_date', true ); ?>

                        <li>
                            <strong>
                                <?php echo esc_html( $event->post_title ); ?>
                            </strong>

                            <?php if ( ! empty( $start_date ) ) : ?>
                                (<?php echo esc_html( $start_date ); ?>)
                            <?php endif; ?>

                            <?php if ( 'publish' !== $event->post_status ) : ?>
                                <em><?php echo esc_html( ucfirst( $event->post_status ) ); ?></em>
                            <?php endif; ?>

                            &ndash;

                            <?php if ( 'publish' === $event->post_status ) : ?>
                                <a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>">
                                    View
                                </a>
                                |
                            <?php endif; ?>

                            <a href="<?php echo esc_url( b2b_get_manage_event_url( $event->ID ) ); ?>">
                                Manage
                            </a>
                        </li>

                    <?php endforeach; ?>

                </ul>

            <?php else : ?>

                <p>
                    Your company is not organizing any events yet.
                </p>

            <?php endif; ?>

        <?php else : ?>

            <p>
                No company profile is connected to your account.
            </p>

        <?php endif; ?>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode(
    'b2b_dashboard',
    'b2b_dashboard'
);