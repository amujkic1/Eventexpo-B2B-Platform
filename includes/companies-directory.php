<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the companies directory.
 */
function b2b_companies_directory() {

    $search = isset( $_GET['company_search'] )
        ? sanitize_text_field(
            wp_unslash( $_GET['company_search'] )
        )
        : '';

    $industry = isset( $_GET['industry'] )
        ? sanitize_text_field(
            wp_unslash( $_GET['industry'])
        )
        : '';

    $args = array(
        'post_type'      => 'company',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        's' => $search,
    );

    if ( ! empty( $industry ) ) {

    $args['tax_query'] = array(
            array(
                'taxonomy' => 'industry',
                'field'    => 'slug',
                'terms'    => $industry,
            ),
        );
    }

    $companies = new WP_Query( $args );

    $industry_terms = get_terms(
        array(
            'taxonomy' => 'industry',
            'hide_empty' => false,
        )
    ); 

    ob_start();

    ?>

    <form method="get" class="b2b-company-search">

        <input
            type="search"
            name="company_search"
            value="<?php echo esc_attr( $search ); ?>"
            placeholder="Search companies..."
        >

        <select name="industry">

            <option value="">
                All industries
            </option>

            <?php foreach ( $industry_terms as $industry_term ) : ?>

                <option
                    value="<?php echo esc_attr( $industry_term->slug ); ?>"
                    <?php selected( $industry, $industry_term->slug ); ?>
                >
                    <?php echo esc_html( $industry_term->name ); ?>
                </option>

            <?php endforeach; ?>

        </select>

        <button type="submit">
            Search
        </button>

    </form>

    <?php

    if ( $companies->have_posts() ) {

        while ( $companies->have_posts() ) {

            $companies->the_post();

            $company_id = get_the_ID();

            $country = get_post_meta(
                $company_id,
                'country',
                true
            );

            $city = get_post_meta(
                $company_id,
                'city',
                true
            );

            $company_industries = get_the_terms(
                $company_id,
                'industry'
            );

            ?>

            <div class="b2b-company-card">

                <?php if ( has_post_thumbnail() ) : ?>

                    <div class="b2b-company-logo">
                        <?php the_post_thumbnail( 'medium' ); ?>
                    </div>

                <?php endif; ?>

                <h3>
                    <?php echo esc_html( get_the_title() ); ?>
                </h3>

                <?php if ( ! empty( $company_industries ) && ! is_wp_error( $company_industries ) ) : ?>

                    <p>
                        <strong>Industry:</strong>
                        <?php echo esc_html( $company_industries[0]->name ); ?>
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

                <p>
                    <a href="<?php echo esc_url( get_permalink( $company_id ) ); ?>">
                        View Company
                    </a>
                </p>


            </div>

            <?php
        }

    } else {

        echo '<p>No companies found.</p>';
    }

    wp_reset_postdata();

    return ob_get_clean();
}

add_shortcode(
    'b2b_companies_directory',
    'b2b_companies_directory'
);