<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$date = b2b_date_parts( $data['start_date'] );
$approved = count( array_filter( $registration_rows, function ( $row ) { return 'approved' === $row['status']; } ) );
$pending = count( array_filter( $registration_rows, function ( $row ) { return '' === $row['status'] || 'pending' === $row['status']; } ) );
?>
<div class="ee-crumb"><a href="<?php echo esc_url( b2b_page_url( 'b2b_manage_event', 'manage-event' ) ); ?>">My events</a><?php echo b2b_icon( 'chevron-right' ); ?><span><?php echo esc_html( $event->post_title ); ?></span></div>
<div class="ee-page-heading"><div><h1><?php echo esc_html( $event->post_title ); ?></h1><div class="ee-meta"><span><?php echo b2b_icon( 'calendar-days' ); ?><?php echo esc_html( $date['label'] ); ?></span><span><?php echo b2b_icon( 'map-pin' ); ?><?php echo esc_html( implode( ', ', array_filter( array( $data['location'], $data['city'], $data['country'] ) ) ) ?: 'Location to be announced' ); ?></span><span class="ee-badge ee-badge-<?php echo esc_attr( $event->post_status ); ?>"><?php echo esc_html( b2b_status_label( $event->post_status ) ); ?></span></div></div><a class="ee-button ee-button-outline" href="<?php echo esc_url( get_permalink( $event_id ) ); ?>"><?php echo b2b_icon( 'external-link' ); ?>View event</a></div>
<?php if ( $message ) : ?><div class="ee-notice" role="status"><?php echo esc_html( $message ); ?></div><?php endif; ?>
<div class="ee-stats"><div class="ee-stat"><span>Registrations shown</span><strong><?php echo esc_html( count( $registration_rows ) ); ?></strong></div><div class="ee-stat"><span>Approved</span><strong><?php echo esc_html( $approved ); ?></strong></div><div class="ee-stat"><span>Awaiting approval</span><strong><?php echo esc_html( $pending ); ?></strong></div></div>
<div data-ee-tabs>
    <div class="ee-tabs" role="tablist" aria-label="Event management">
        <button type="button" id="ee-registrations-tab" role="tab" aria-controls="ee-registrations" aria-selected="true">Registrations <span class="ee-count"><?php echo esc_html( count( $registration_rows ) ); ?></span></button>
        <button type="button" id="ee-fields-tab" role="tab" aria-controls="ee-registration-fields" aria-selected="false">Registration form <span class="ee-count"><?php echo esc_html( count( $registration_fields ) ); ?></span></button>
        <button type="button" id="ee-details-tab" role="tab" aria-controls="ee-event-details" aria-selected="false">Event details</button>
    </div>
    <section id="ee-registrations" role="tabpanel" aria-labelledby="ee-registrations-tab">
        <?php echo b2b_render_template( 'event-registrations', compact( 'registration_rows' ) ); ?>
    </section>
    <section id="ee-registration-fields" role="tabpanel" aria-labelledby="ee-fields-tab">
        <?php echo b2b_render_template( 'event-registration-fields', compact( 'event_id', 'registration_fields' ) ); ?>
    </section>
    <section id="ee-event-details" role="tabpanel" aria-labelledby="ee-details-tab">
        <form method="post" action="#ee-event-details" class="ee-form">
            <?php wp_nonce_field( 'b2b_save_event_' . $event_id, 'b2b_event_nonce' ); ?>
            <input type="hidden" name="b2b_save_event" value="1">
            <div class="ee-fields">
                <div class="ee-field ee-field-full"><label for="event_title">Event title <span aria-hidden="true">*</span></label><input type="text" id="event_title" name="event_title" value="<?php echo esc_attr( $event->post_title ); ?>" required></div>
                <div class="ee-field ee-field-full"><label for="event_description">Description</label><textarea id="event_description" name="event_description" rows="7"><?php echo esc_textarea( $event->post_content ); ?></textarea></div>
                <div class="ee-field ee-field-full"><label for="event_type">Event type</label><select id="event_type" name="event_type"><option value="">Select event type</option><?php foreach ( $event_types as $event_type ) : ?><option value="<?php echo esc_attr( $event_type->term_id ); ?>" <?php selected( $selected_event_type, $event_type->term_id ); ?>><?php echo esc_html( $event_type->name ); ?></option><?php endforeach; ?></select></div>
                <?php foreach ( array( 'start_date' => 'Start date', 'end_date' => 'End date', 'location' => 'Venue', 'city' => 'City', 'country' => 'Country' ) as $field => $label ) : ?>
                    <div class="ee-field"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label><input type="<?php echo in_array( $field, array( 'start_date', 'end_date' ), true ) ? 'date' : 'text'; ?>" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $data[ $field ] ); ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="ee-form-actions"><button type="submit" class="ee-button"><?php echo b2b_icon( 'save' ); ?>Save changes</button></div>
        </form>
    </section>
</div>
