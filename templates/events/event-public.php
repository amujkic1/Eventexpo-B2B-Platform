<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$date = b2b_date_parts( $start_date );
?>
<div class="ee-crumb"><a href="<?php echo esc_url( b2b_page_url( 'b2b_manage_event', 'manage-event' ) ); ?>">My events</a><?php echo b2b_icon( 'chevron-right' ); ?><span><?php echo esc_html( $event->post_title ); ?></span></div>
<div class="ee-page-heading"><div><h1><?php echo esc_html( $event->post_title ); ?></h1><div class="ee-meta"><?php if ( ! empty( $event_types ) && ! is_wp_error( $event_types ) ) : ?><span class="ee-badge"><?php echo esc_html( $event_types[0]->name ); ?></span><?php endif; ?><span><?php echo b2b_icon( 'calendar-days' ); ?><?php echo esc_html( $date['label'] ); ?><?php if ( $end_date && $end_date !== $start_date ) : ?> &ndash; <?php echo esc_html( b2b_date_parts( $end_date )['label'] ); ?><?php endif; ?></span><span><?php echo b2b_icon( 'map-pin' ); ?><?php echo esc_html( implode( ', ', array_filter( array( $location, $city, $country ) ) ) ?: 'Location to be announced' ); ?></span></div></div><?php if ( $can_manage ) : ?><a class="ee-button ee-button-outline" href="<?php echo esc_url( b2b_get_manage_event_url( $event_id ) ); ?>"><?php echo b2b_icon( 'settings-2' ); ?>Manage event</a><?php endif; ?></div>
<div class="ee-grid ee-two-column">
    <div class="ee-stack">
        <?php if ( has_post_thumbnail( $event_id ) ) : ?><figure class="ee-event-image"><?php echo get_the_post_thumbnail( $event_id, 'large' ); ?></figure><?php endif; ?>
        <section class="ee-section"><h2>About this event</h2><div class="ee-prose"><?php echo $event->post_content ? wp_kses_post( $event->post_content ) : 'No event description provided.'; ?></div></section>
        <?php if ( $organizer_company && 'company' === $organizer_company->post_type ) : ?><section class="ee-section"><h2>Organizer</h2><a class="ee-row" href="<?php echo esc_url( get_permalink( $organizer_company_id ) ); ?>"><span class="ee-logo"><?php echo esc_html( b2b_initials( $organizer_company->post_title ) ); ?></span><strong><?php echo esc_html( $organizer_company->post_title ); ?></strong><?php echo b2b_icon( 'arrow-right' ); ?></a></section><?php endif; ?>
    </div>
    <aside class="ee-stack"><?php echo $registration_form; ?></aside>
</div>
