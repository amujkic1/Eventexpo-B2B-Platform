<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

    <div class="b2b-public-company-profile">

        <?php if ( has_post_thumbnail( $company_id ) ) : ?>

            <div class="b2b-company-logo">
                <?php
                echo get_the_post_thumbnail(
                    $company_id,
                    'medium'
                );
                ?>
            </div>

        <?php endif; ?>

        <h1>
            <?php echo esc_html( $company->post_title ); ?>
        </h1>

        <?php if ( ! empty( $industries ) && ! is_wp_error( $industries ) ) : ?>

            <p>
                <strong>Industry:</strong>
                <?php echo esc_html( $industries[0]->name ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $company->post_content ) ) : ?>

            <p>
                <?php echo esc_html( $company->post_content ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $country ) ) : ?>

            <p>
                <strong>Country:</strong>
                <?php echo esc_html( $country ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $city ) ) : ?>

            <p>
                <strong>City:</strong>
                <?php echo esc_html( $city ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $address ) ) : ?>

            <p>
                <strong>Address:</strong>
                <?php echo esc_html( $address ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $website ) ) : ?>

            <p>
                <strong>Website:</strong>
                <a
                    href="<?php echo esc_url( $website ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?php echo esc_html( $website ); ?>
                </a>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $email ) ) : ?>

            <p>
                <strong>Email:</strong>
                <?php echo esc_html( $email ); ?>
            </p>

        <?php endif; ?>

        <?php if ( ! empty( $phone ) ) : ?>

            <p>
                <strong>Phone:</strong>
                <?php echo esc_html( $phone ); ?>
            </p>

        <?php endif; ?>

    </div>
