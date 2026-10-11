<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the public profile of a company.
 */
function b2b_company_public_profile( $company_id ) {

    $data = b2b_get_company_data( $company_id );

    $company = $data['company'];

    if (
        ! $company ||
        'company' !== $company->post_type
    ) {
        return '<p>Company not found.</p>';
    }

    $website = $data['website'];
    $email   = $data['email'];
    $phone   = $data['phone'];
    $country = $data['country'];
    $city    = $data['city'];
    $address = $data['address'];

    $industries = get_the_terms(
        $company_id,
        'industry'
    );

    ob_start();
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

    <?php

    return ob_get_clean();
}

/**
 * Use the custom template for single company pages.
 */
function b2b_company_template( $template ) {

    if ( is_singular( 'company' ) ) {

        $custom_template = plugin_dir_path( __DIR__ )
            . 'templates/single-company.php';

        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }

    return $template;
}

add_filter(
    'single_template',
    'b2b_company_template'
);