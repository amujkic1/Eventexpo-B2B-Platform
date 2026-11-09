<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ee-page-heading"><div><h1>My events</h1><p><?php echo esc_html( count( $events ) ); ?> events organized by your company</p></div></div>
<?php if ( $events ) : ?>
<div class="ee-event-list">
    <?php foreach ( $events as $event ) : $date = b2b_date_parts( $event_dates[ $event->ID ] ); ?>
    <article class="ee-card ee-event-item">
        <div class="ee-date"><span><?php echo esc_html( $date['month'] ); ?></span><strong><?php echo esc_html( $date['day'] ); ?></strong></div>
        <div class="ee-grow"><div class="ee-row"><h2><?php echo esc_html( $event->post_title ); ?></h2><span class="ee-badge ee-badge-<?php echo esc_attr( $event->post_status ); ?>"><?php echo esc_html( b2b_status_label( $event->post_status ) ); ?></span></div><div class="ee-meta"><span><?php echo b2b_icon( 'calendar-days' ); ?><?php echo esc_html( $date['label'] ); ?></span></div></div>
        <div class="ee-actions"><?php if ( 'publish' === $event->post_status ) : ?><a class="ee-button ee-button-subtle" href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>">View event</a><?php endif; ?><a class="ee-button" href="<?php echo esc_url( b2b_get_manage_event_url( $event->ID ) ); ?>">Manage <?php echo b2b_icon( 'arrow-right' ); ?></a></div>
    </article>
    <?php endforeach; ?>
</div>
<?php else : ?><div class="ee-empty"><?php echo b2b_icon( 'calendar-days' ); ?><h2>No events to manage</h2><p>Your company's events will appear here.</p></div><?php endif; ?>
