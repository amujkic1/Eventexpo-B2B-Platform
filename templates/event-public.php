<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

    <div class="b2b-public-event-profile">

        <?php if ( has_post_thumbnail( $event_id ) ) : ?>

            <div class="b2b-event-image">
                <?php
                echo get_the_post_thumbnail(
                    $event_id,
                    'large'
                );
                ?>
            </div>

        <?php endif; ?>

        <h1>
            <?php echo esc_html( $event->post_title ); ?>
        </h1>

        <?php if ( $can_manage ) : ?>

            <p>
                <a href="<?php echo esc_url(
                    b2b_get_manage_event_url( $event_id )
                ); ?>">
                    Manage Event
                </a>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $event_types ) && ! is_wp_error( $event_types ) ) : ?>

            <p>
                <strong>Event Type:</strong>
                <?php echo esc_html( $event_types[0]->name ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $organizer_company_id ) ) : ?>

        <?php
        if (
            $organizer_company &&
            'company' === $organizer_company->post_type
        ) :
        ?>

            <p>
                <strong>Organizer:</strong>

                <a href="<?php echo esc_url(
                    get_permalink( $organizer_company_id )
                ); ?>">
                    <?php echo esc_html(
                        $organizer_company->post_title
                    ); ?>
                </a>
            </p>

        <?php endif; ?>

    <?php endif; ?>

        <?php if ( ! empty( $start_date ) ) : ?>

            <p>
                <strong>Start Date:</strong>
                <?php echo esc_html( $start_date ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $end_date ) ) : ?>

            <p>
                <strong>End Date:</strong>
                <?php echo esc_html( $end_date ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $location ) ) : ?>

            <p>
                <strong>Location:</strong>
                <?php echo esc_html( $location ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $city ) ) : ?>

            <p>
                <strong>City:</strong>
                <?php echo esc_html( $city ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $country ) ) : ?>

            <p>
                <strong>Country:</strong>
                <?php echo esc_html( $country ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $event->post_content ) ) : ?>

            <div class="b2b-event-description">
                <?php echo wp_kses_post( $event->post_content ); ?>
            </div>

        <?php endif; ?>

        <?php
            echo $registration_form;
        ?>  

    </div>
