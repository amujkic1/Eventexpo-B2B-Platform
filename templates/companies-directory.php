<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ee-page-heading"><div><h1>Companies</h1><p><?php echo esc_html( count( $company_cards ) ); ?> companies<?php echo $search || $industry || $country ? ' matching your filters' : ' in the EventExpo network'; ?></p></div></div>
<form method="get">
    <div class="ee-search-bar">
        <label class="ee-search"><span class="screen-reader-text">Search companies</span><?php echo b2b_icon( 'search' ); ?><input type="search" name="company_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search companies"></label>
        <label><span class="screen-reader-text">Country</span><select name="country"><option value="">All countries</option><?php foreach ( $countries as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $country, $option ); ?>><?php echo esc_html( $option ); ?></option><?php endforeach; ?></select></label>
        <button class="ee-button" type="submit">Search</button>
    </div>
    <fieldset class="ee-chip-group"><legend class="screen-reader-text">Industry</legend>
        <label class="ee-chip"><input type="radio" name="industry" value="" <?php checked( $industry, '' ); ?>><span>All industries</span></label>
        <?php if ( ! is_wp_error( $industry_terms ) ) : foreach ( $industry_terms as $term ) : ?><label class="ee-chip"><input type="radio" name="industry" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( $industry, $term->slug ); ?>><span><?php echo esc_html( $term->name ); ?></span></label><?php endforeach; endif; ?>
    </fieldset>
</form>
<?php if ( $company_cards ) : ?>
<div class="ee-grid ee-company-grid">
    <?php foreach ( $company_cards as $card ) : ?>
    <article class="ee-card ee-company-card">
        <div class="ee-row"><div class="ee-logo"><?php echo $card['logo'] ?: esc_html( b2b_initials( $card['title'] ) ); ?></div><div class="ee-grow"><h2><?php echo esc_html( $card['title'] ); ?></h2><?php if ( ! empty( $card['industries'] ) && ! is_wp_error( $card['industries'] ) ) : ?><p class="ee-muted ee-small"><?php echo esc_html( $card['industries'][0]->name ); ?></p><?php endif; ?></div></div>
        <p class="ee-description"><?php echo esc_html( $card['description'] ?: 'Company profile on EventExpo.' ); ?></p>
        <footer><div class="ee-meta"><span><?php echo b2b_icon( 'map-pin' ); ?><?php echo esc_html( implode( ', ', array_filter( array( $card['city'], $card['country'] ) ) ) ?: 'Location not provided' ); ?></span></div><a href="<?php echo esc_url( $card['url'] ); ?>" aria-label="<?php echo esc_attr( 'View ' . $card['title'] ); ?>">View <?php echo b2b_icon( 'chevron-right' ); ?></a></footer>
    </article>
    <?php endforeach; ?>
</div>
<?php else : ?><div class="ee-empty"><?php echo b2b_icon( 'search' ); ?><h2>No companies found</h2><p>No companies match the selected filters.</p><a class="ee-button ee-button-outline" href="<?php echo esc_url( b2b_page_url( 'b2b_companies_directory', 'companies' ) ); ?>">Clear filters</a></div><?php endif; ?>
