<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
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

<?php if ( ! empty( $company_cards ) ) : ?>
    <?php foreach ( $company_cards as $card ) : ?>
        <div class="b2b-company-card">
            <?php if ( '' !== $card['logo'] ) : ?>
                <div class="b2b-company-logo"><?php echo $card['logo']; ?></div>
            <?php endif; ?>
            <h3><?php echo esc_html( $card['title'] ); ?></h3>
            <?php if ( ! empty( $card['industries'] ) && ! is_wp_error( $card['industries'] ) ) : ?>
                <p><strong>Industry:</strong> <?php echo esc_html( $card['industries'][0]->name ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $card['country'] ) ) : ?>
                <p><strong>Country:</strong> <?php echo esc_html( $card['country'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $card['city'] ) ) : ?>
                <p><strong>City:</strong> <?php echo esc_html( $card['city'] ); ?></p>
            <?php endif; ?>
            <p><a href="<?php echo esc_url( $card['url'] ); ?>">View Company</a></p>
        </div>
    <?php endforeach; ?>
<?php else : ?>
    <p>No companies found.</p>
<?php endif; ?>
