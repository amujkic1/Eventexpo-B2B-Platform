<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ee-page-heading"><div><h1>My events</h1><p><?php echo esc_html( count( $events ) ); ?> events organized by your company</p></div></div>
<?php if ( $lifecycle_failure ) : ?><div class="ee-notice ee-notice-error" role="alert"><?php echo esc_html( $lifecycle_failure['message'] ); ?></div><?php endif; ?>
<?php if ( $can_create_event ) : ?>
<section class="ee-card" style="margin-bottom:24px">
    <h2>Create an event</h2>
    <p class="ee-muted">Start with a draft. You can add details before submitting it to EventExpo for approval.</p>
    <form method="post" class="ee-form">
        <?php wp_nonce_field( 'b2b_create_event', 'b2b_lifecycle_nonce' ); ?>
        <div class="ee-field"><label for="new_event_title">Event title</label><input id="new_event_title" name="event_title" value="<?php echo esc_attr( $lifecycle_failure['title'] ?? '' ); ?>" required></div>
        <button class="ee-button" type="submit" name="b2b_create_event" value="1"><?php echo b2b_icon( 'plus' ); ?>Create draft</button>
    </form>
</section>
<?php endif; ?>
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
