<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
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
