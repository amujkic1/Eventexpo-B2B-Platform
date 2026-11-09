<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ee-crumb"><a href="<?php echo esc_url( b2b_page_url( 'b2b_companies_directory', 'companies' ) ); ?>">Companies</a><?php echo b2b_icon( 'chevron-right' ); ?><span><?php echo esc_html( $company->post_title ); ?></span></div>
<header class="ee-profile-heading">
    <div class="ee-logo ee-logo-large"><?php echo has_post_thumbnail( $company_id ) ? get_the_post_thumbnail( $company_id, 'medium' ) : esc_html( b2b_initials( $company->post_title ) ); ?></div>
    <div class="ee-grow"><h1><?php echo esc_html( $company->post_title ); ?></h1><div class="ee-meta">
        <?php if ( ! empty( $industries ) && ! is_wp_error( $industries ) ) : ?><span><?php echo b2b_icon( 'building-2' ); ?><?php echo esc_html( $industries[0]->name ); ?></span><?php endif; ?>
        <?php if ( $city || $country ) : ?><span><?php echo b2b_icon( 'map-pin' ); ?><?php echo esc_html( implode( ', ', array_filter( array( $city, $country ) ) ) ); ?></span><?php endif; ?>
        <?php if ( $website ) : ?><a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo b2b_icon( 'globe' ); ?><?php echo esc_html( wp_parse_url( $website, PHP_URL_HOST ) ?: $website ); ?></a><?php endif; ?>
    </div></div>
    <?php if ( $email ) : ?><div class="ee-actions"><a class="ee-button" href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo b2b_icon( 'mail' ); ?>Contact company</a></div><?php endif; ?>
</header>
<div class="ee-grid ee-two-column">
    <div class="ee-stack"><section class="ee-section"><h2>About</h2><div class="ee-prose"><?php echo esc_html( $company->post_content ?: 'No company description provided.' ); ?></div></section></div>
    <aside class="ee-stack"><section class="ee-card"><h2>Company details</h2><dl class="ee-detail-list">
        <?php if ( ! empty( $industries ) && ! is_wp_error( $industries ) ) : ?><div><dt>Industry</dt><dd><?php echo esc_html( $industries[0]->name ); ?></dd></div><?php endif; ?>
        <?php foreach ( array( 'Country' => $country, 'City' => $city, 'Address' => $address, 'Email' => $email, 'Phone' => $phone ) as $label => $value ) : if ( '' !== $value ) : ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><?php if ( 'Email' === $label ) : ?><a href="<?php echo esc_url( 'mailto:' . $value ); ?>"><?php echo esc_html( $value ); ?></a><?php else : echo esc_html( $value ); endif; ?></dd></div><?php endif; endforeach; ?>
    </dl></section></aside>
</div>
