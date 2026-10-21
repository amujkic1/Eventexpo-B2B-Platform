<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php wp_nonce_field( 'b2b_save_event_details', 'b2b_event_details_nonce' ); ?>

    <p>
        <label for="start_date">
            <strong>Start Date</strong>
        </label>
        <br>

        <input
            type="date"
            id="start_date"
            name="start_date"
            value="<?php echo esc_attr( $start_date ); ?>"
        >
    </p>

    <p>
        <label for="end_date">
            <strong>End Date</strong>
        </label>
        <br>

        <input
            type="date"
            id="end_date"
            name="end_date"
            value="<?php echo esc_attr( $end_date ); ?>"
        >
    </p>

    <p>
        <label for="location">
            <strong>Location</strong>
        </label>
        <br>

        <input
            type="text"
            id="location"
            name="location"
            value="<?php echo esc_attr( $location ); ?>"
        >
    </p>

    <p>
        <label for="city">
            <strong>City</strong>
        </label>
        <br>

        <input
            type="text"
            id="city"
            name="city"
            value="<?php echo esc_attr( $city ); ?>"
        >
    </p>

    <p>
        <label for="country">
            <strong>Country</strong>
        </label>
        <br>

        <input
            type="text"
            id="country"
            name="country"
            value="<?php echo esc_attr( $country ); ?>"
        >
    </p>

    <p>
        <label for="organizer_company_id">
            <strong>Organizer</strong>
        </label>
        <br>

        <select
            id="organizer_company_id"
            name="organizer_company_id"
        >

            <option value="">
                Select Organizer
            </option>

            <?php foreach ( $companies as $company ) : ?>

                <option
                    value="<?php echo esc_attr( $company->ID ); ?>"
                    <?php selected( $organizer_company_id, $company->ID ); ?>
                >
                    <?php echo esc_html( $company->post_title ); ?>
                </option>

            <?php endforeach; ?>

        </select>
    </p>
