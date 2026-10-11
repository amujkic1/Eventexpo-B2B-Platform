<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display and edit the company profile
 * connected to the currently logged-in user.
 */
function b2b_my_company() {

    if ( ! is_user_logged_in() ) {
        return '<p>Please log in to view your company.</p>';
    }

    $user_id = get_current_user_id();

     /*
     * Read company data.
     */
    $company_id = get_user_meta(
        $user_id,
        'company_id',
        true
    );

    if ( empty( $company_id ) ) {
        return '<p>No company profile is connected to your account.</p>';
    }

    $data = b2b_get_company_data( $company_id );

    $company = $data['company'];

    if (
        ! $company ||
        'company' !== $company->post_type ||
        (int) $company->post_author !== $user_id
    ) {
        return '<p>You do not have permission to access this company.</p>';
    }

    $website = $data['website'];
    $email   = $data['email'];
    $phone   = $data['phone'];
    $country = $data['country'];
    $city    = $data['city'];
    $address = $data['address'];

    $industries = get_terms(
    array(
        'taxonomy'   => 'industry',
        'hide_empty' => false,
    )
    );

    $company_industries = wp_get_post_terms(
        $company_id,
        'industry',
        array(
            'fields' => 'ids',
        )
    );

    $selected_industry = ! empty( $company_industries )
        ? $company_industries[0]
        : 0;

    $message = '';

    /*
     * Check and process form submission.
     */
    if (
        'POST' === $_SERVER['REQUEST_METHOD'] &&
        isset( $_POST['b2b_company_nonce'] )
    ) {

        $nonce = sanitize_text_field(
            wp_unslash( $_POST['b2b_company_nonce'] )
        );

        if (
            wp_verify_nonce(
                $nonce,
                'b2b_update_company'
            )
        ) {

            $company_name = isset( $_POST['company_name'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['company_name'] )
                )
                : '';

            $company_description = isset( $_POST['company_description'] )
                ? sanitize_textarea_field(
                    wp_unslash( $_POST['company_description'] )
                )
                : '';

            $industry_id = isset( $_POST['industry'] )
                ? absint( $_POST['industry'] )
                : 0;

            $website = isset( $_POST['website'] )
                ? esc_url_raw(
                    wp_unslash( $_POST['website'] )
                )
                : '';

            $email = isset( $_POST['email'] )
                ? sanitize_email(
                    wp_unslash( $_POST['email'] )
                )
                : '';

            $phone = isset( $_POST['phone'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['phone'] )
                )
                : '';

            $country = isset( $_POST['country'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['country'] )
                )
                : '';

            $city = isset( $_POST['city'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['city'] )
                )
                : '';

            $address = isset( $_POST['address'] )
                ? sanitize_text_field(
                    wp_unslash( $_POST['address'] )
                )
                : '';

            /*
             * Update company data.
             */
            if ( ! empty( $company_name ) ) {

                $result = wp_update_post(
                    array(
                        'ID'           => $company_id,
                        'post_title'   => $company_name,
                        'post_content' => $company_description,
                    ),
                    true
                );

                if ( is_wp_error( $result ) ) {

                    $message = 'The company profile could not be updated.';

                } else {

                    update_post_meta(
                        $company_id,
                        'website',
                        $website
                    );

                    update_post_meta(
                        $company_id,
                        'email',
                        $email
                    );

                    update_post_meta(
                        $company_id,
                        'phone',
                        $phone
                    );

                    update_post_meta(
                        $company_id,
                        'country',
                        $country
                    );

                    update_post_meta(
                        $company_id,
                        'city',
                        $city
                    );

                    update_post_meta(
                        $company_id,
                        'address',
                        $address
                    );

                    if (
                        ! empty( $_FILES['company_logo']['name'] )
                    ) {

                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';

                        $logo_id = media_handle_upload(
                            'company_logo',
                            $company_id
                        );

                        if ( ! is_wp_error( $logo_id ) ) {

                            set_post_thumbnail(
                                $company_id,
                                $logo_id
                            );
                        }
                    }

                    if ( $industry_id ) {

                        wp_set_object_terms(
                            $company_id,
                            array( $industry_id ),
                            'industry'
                        );

                    } else {

                        wp_set_object_terms(
                            $company_id,
                            array(),
                            'industry'
                        );
                    }

                    $message = 'Company profile updated successfully.';

                    // Reload the company with the updated data.
                    $company = get_post( $company_id );
                }

            } else {

                $message = 'Company name is required.';
            }
        }
    }

    ob_start();
    ?>

    <div class="b2b-company-profile">

        <?php if ( ! empty( $message ) ) : ?>

            <p>
                <?php echo esc_html( $message ); ?>
            </p>

        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">

            <?php
            wp_nonce_field(
                'b2b_update_company',
                'b2b_company_nonce'
            );
            ?>

            <p>
                <label for="company_name">
                    <strong>Company Name</strong>
                </label>
            </p>

            <p>
                <input
                    type="text"
                    id="company_name"
                    name="company_name"
                    value="<?php echo esc_attr( $company->post_title ); ?>"
                    required
                >
            </p>

            <p>
                <label for="company_description">
                    <strong>Description</strong>
                </label>
            </p>

            <p>
                <textarea
                    id="company_description"
                    name="company_description"
                    rows="8"
                ><?php echo esc_textarea( $company->post_content ); ?></textarea>
            </p>

            <p>
                <label for="industry">
                    <strong>Industry</strong>
                </label>
            </p>

            <p>
                <select
                    id="industry"
                    name="industry"
                >

                    <option value="">
                        Select Industry
                    </option>

                    <?php foreach ( $industries as $industry ) : ?>

                        <option
                            value="<?php echo esc_attr( $industry->term_id ); ?>"
                            <?php selected( $selected_industry, $industry->term_id ); ?>
                        >
                            <?php echo esc_html( $industry->name ); ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </p>

            <p>
                <label for="website">
                    <strong>Website</strong>
                </label>
            </p>

            <p>
                <input
                    type="url"
                    id="website"
                    name="website"
                    value="<?php echo esc_attr( $website ); ?>"
                    placeholder="https://example.com"
                >
            </p>

            <p>
                <label for="email">
                    <strong>Company Email</strong>
                </label>
            </p>

            <p>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo esc_attr( $email ); ?>"
                >
            </p>

            <p>
                <label for="phone">
                    <strong>Phone</strong>
                </label>
            </p>

            <p>
                <input
                    type="text"
                    id="phone"
                    name="phone"
                    value="<?php echo esc_attr( $phone ); ?>"
                >
            </p>

            <p>
                <label for="country">
                    <strong>Country</strong>
                </label>
            </p>

            <p>
                <input
                    type="text"
                    id="country"
                    name="country"
                    value="<?php echo esc_attr( $country ); ?>"
                >
            </p>

            <p>
                <label for="city">
                    <strong>City</strong>
                </label>
            </p>

            <p>
                <input
                    type="text"
                    id="city"
                    name="city"
                    value="<?php echo esc_attr( $city ); ?>"
                >
            </p>

            <p>
                <label for="address">
                    <strong>Address</strong>
                </label>
            </p>

            <p>
                <input
                    type="text"
                    id="address"
                    name="address"
                    value="<?php echo esc_attr( $address ); ?>"
                >
            </p>

            <p>
                <label for="company_logo">
                    <strong>Company Logo</strong>
                </label>
            </p>

            <?php if ( has_post_thumbnail( $company_id ) ) : ?>

                <p>
                    <?php
                    echo get_the_post_thumbnail(
                        $company_id,
                        'medium'
                    );
                    ?>
                </p>

            <?php endif; ?>

            <p>
                <input
                    type="file"
                    id="company_logo"
                    name="company_logo"
                    accept="image/*"
                >
            </p>

            <p>
                <button type="submit">
                    Save Changes
                </button>
            </p>

        </form>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode(
    'b2b_my_company',
    'b2b_my_company'
);