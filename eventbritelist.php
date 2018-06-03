<?php
/*
Plugin Name: List Eventbrite events
Plugin URI: 
Description: A shortcode that grabs all future events from a profile. Make sure to cache your site, so you're not overusuing the Eventbrite API 
Version: 0.1.0
Author: Digital Ideas
Author URI: http://www.digitalideas.io
*/

// TODO: CHECK IF STATUS LIVE, DELETE IF CANCELLED

require_once('eventbrite-sdk-php/HttpClient.php');

define('EVENTBRITELIST_EVENT_KEY', 'eventbritelist_eventbrite_id');

add_action('wp_enqueue_scripts', 'dievents_scripts');
function dievents_scripts() {
    wp_enqueue_script('fa', plugins_url( '/js/fontawesome-all.min.js',  __FILE__ ));
    wp_enqueue_style('dievents-style', plugins_url('/css/style.css', __FILE__ ));
	wp_enqueue_script('eventbritelist-script', plugins_url( '/js/app.min.js', __FILE__ ), array('jquery'));
	
	wp_localize_script('eventbritelist-script', 'ajax_object',
	    array(
    	    'ajax_url' => admin_url( 'admin-ajax.php' )
	    )
    ); 
}

function eventbrite_list($atts = [], $content = '')
{
                if(isset($_GET['debug']) && ($_GET['debug'] == 'yes')) {
                    eventbritelist_read_events();
                   // on_sale_status UNAVAILABLE, SOLD_OUT, AVAILABLE
                    echo 'eventbritelist_read_events executed'; exit;
                    }
    $status = 'future';
    if(isset($atts['status'])) {
        $status = $atts['status'];
    }
    
    $showExcerpt = false;
    if(isset($atts['show_excerpt'])) {
        $showDescription = true;
    }
        
    if($showDescription == "false") {
        $showDescription = false;
    }
    else {
        $showDescription = true;
    }
    
    if($showHiddenTickets == "true") {
        $showHiddenTickets = true;
    }
    else {
        $showHiddenTickets = false;
    }
    
    $showMoreLinkIfThereAreHiddenEvents = true;
    $hiddenEvents = 0;
    
    $limitEventsToShow = 3;
    if(isset($atts['limit_events_to_show'])) {
        $limitEventsWithAvailableTickets = intval($atts['limit_events_to_show']);
    }
    
    $args = [
    	'orderby'          => 'date',
    	'order'            => 'ASC',
    	'post_type'        => 'eventbritelist_event',
        'post_status'      => $status,
    	'suppress_filters' => true,
        'posts_per_page'   => 100,
    ];
    $events = get_posts($args);
    
    $content .= '<div class="eventbritelist">'."\n";
    $content .= '<div class="list">'."\n";
    
    foreach($events as $event) {
        $freeTicketAvailability =  get_post_meta($event->ID, 'eventbritelist_eventbrite_free_tickets_availability', true);
        $eventUrl = get_post_meta($event->ID, 'eventbritelist_eventbrite_link', true);
        
        if($freeTicketAvailability == 'AVAILABLE_NOW') {
            $freeTicketsAvailable = (int)get_post_meta($event->ID, 'eventbritelist_eventbrite_free_tickets_availability_count', true);
            if($freeTicketsAvailable < 1) {
                $ticketsAvailableString = 'ticket availability could not be determined';
            }
            if($freeTicketsAvailable == 1) {
                $ticketsAvailableString = '<a href="' . $eventUrl . '" class="button oneleft" title="Updated: ' .  get_post_modified_time("l, j. F Y H:i", false, $event->ID ) . '">only ' . $freeTicketsAvailable . ' free ticket available &#8811;</a>';
            }
            else if($freeTicketsAvailable <= 3) {
                $ticketsAvailableString = '<a href="' . $eventUrl . '" class="button limited" title="Updated: ' .  get_post_modified_time("l, j. F Y H:i", false, $event->ID ) . '">only ' . $freeTicketsAvailable . ' free tickets available &#8811;</a>';
            }
            else {
                $ticketsAvailableString = '<a href="' . $eventUrl . '" class="button available" title="Updated: ' .  get_post_modified_time("l, j. F Y H:i", false, $event->ID ) . '">' . $freeTicketsAvailable . ' free tickets available &#8811;</a>';
            }
        }
        else if($freeTicketAvailability == 'AVAILABLE_IN_THE_FUTURE') {
            $freeTicketsAvailabilityDate = new \DateTime(get_post_meta($event->ID, 'eventbritelist_eventbrite_free_tickets_availability_date', true));
            $ticketsAvailableString = '<a href="' . $eventUrl . '" class="button future" title="Updated: ' .  get_post_modified_time("l, j. F Y H:i", false, $event->ID ) . '">free tickets available ' . $freeTicketsAvailabilityDate->format('F j H:i') . '</a>';
        }
        else if($freeTicketAvailability == 'SOLD_OUT') {
            $ticketsAvailableString = '<a href="' . $eventUrl . '" class="button soldout" title="Updated: ' .  get_post_modified_time("l, j. F Y H:i", false, $event->ID ) . '">' . $ticketsAvailableString . '</a>';
        }
        else if($freeTicketAvailability == 'NOT_AVAILABLE') {
            $ticketsAvailableString = '<a href="' . $eventUrl . '" class="button available" title="Updated: ' .  get_post_modified_time("l, j. F Y H:i", false, $event->ID ) . '">tickets not available</a>';
        }
        else;
        
        if($limitEventsToShow > 0) {
            $content .= '<div class="event">';
        }
        else {
            $content .= '<div class="hidden event">';
            $hiddenEvents++;
        }
        
        $content .= '<div class="image"><img src="' . get_post_meta($event->ID, 'eventbritelist_eventbrite_image', true) . '"></div>';
        $content .= '<div class="time"><i class="fal fa-calendar"></i> ' . get_the_time( "l, j. F Y H:i", $event->ID ) . '</div>';
        $content .= '<div class="title">
            <a href="' . $eventUrl  . '">' . $event->post_title . '</a>
            by <a href="' . get_post_meta($event->ID, 'eventbritelist_eventbrite_organizer_url', true) . '">' . get_post_meta($event->ID, 'eventbritelist_eventbrite_organizer_name', true) . '</a>
            </div>';
        $content .= '<div class="location"><i class="fal fa-thumbtack"></i> <a href="http://www.google.com/maps/place/' . get_post_meta($event->ID, 'eventbritelist_eventbrite_location_latitude', true) . ',' . get_post_meta($event->ID, 'eventbritelist_eventbrite_location_longitude', true) . '" target="_blank">' . get_post_meta($event->ID, 'eventbritelist_eventbrite_location', true) . '</a></div>';
        $content .= '<div class="description">';
        if($showDescription) {
            $content .= get_the_excerpt($event->ID) . '<br />';
        }
        $content .= $ticketsAvailableString . '</div>';
        $content .= '</div>';
        $limitEventsToShow--;
    }
    $content .= '</div>'."\n";
    if($hiddenEvents > 0) {
        $content .= "<p><button class=\"moreevents\">Show more ($hiddenEvents) events</button></p>";
    }
    $content .= '</div>'."\n";
    return $content;
}
add_shortcode('eventbrite_list', 'eventbrite_list');

add_action('eventbritelist_hourly', 'eventbritelist_read_events');

function eventbritelist_read_events() {
    if (!defined('EVENTBRITELIST_CONFIG')) {
        wp_die('Please define EVENTBRITELIST_CONFIG in wp-config.php with your app token');
    }
    $events = eventbritelist_getEventsForProfiles(EVENTBRITELIST_CONFIG);
    
    foreach($events as $event) {
        if( ($event['event']['status'] == 'live') ||
            ($event['event']['status'] == 'started') ||
            ($event['event']['status'] == 'ended') ||
            ($event['event']['status'] == 'completed')
           ) {
            $eventBeginDate = new DateTime($event['event']['start']['local'], new \DateTimeZone('UTC'));
            $eventData = [
              'post_title'    => wp_strip_all_tags($event['event']['name']['text']),
              'post_content'  => '',
              'post_status'   => 'publish',
              'post_type' => 'eventbritelist_event',
              'post_author'   => 1,
              'post_date' => $eventBeginDate->format('Y-m-d H:i:s'),
              'post_content' => $event['event']['description']['html'],
              'post_excerpt'  => mb_strimwidth($event['event']['description']['text'], 0, 160, "..."),
            ];
            
            $customFields = [
                'eventbritelist_eventbrite_link' => $event['event']['url'],
                'eventbritelist_eventbrite_location' => $event['venue']['name'] . ', ' . $event['venue']['address']['city'] . ', ' . $event['venue']['address']['country'],
                'eventbritelist_eventbrite_location_longitude' => $event['venue']['longitude'],
                'eventbritelist_eventbrite_location_latitude' => $event['venue']['latitude'],
                'eventbritelist_eventbrite_image' => $event['event']['logo']['url'],
                'eventbritelist_eventbrite_organizer_name' => $event['organizer']['name'],
                'eventbritelist_eventbrite_organizer_url' => isset($event['organizer']['website'])?$event['organizer']['website']:$event['organizer']['url']                
            ];
            
            if($event['event']['status'] == 'live') {
                $areOrWereFreeNonHiddenTicketsAvailable = false;
                $freeNonHiddenTicketsAvailableNow = 0;
                $freeNonHiddenTicketsAvailableInTheFuture = 0;
                $freeNonHiddenTicketsTotal = 0;
                $freeNonHiddenTicketsAvailableInTheFutureDate = null;
                
                foreach($event['ticketClass']['ticket_classes'] as $ticketClass) {
                    if($ticketClass['free'] && !$ticketClass['hidden']) {
                        $freeNonHiddenTicketsTotal += $ticketClass['quantity_total'];
                        
                        if($ticketClass['on_sale_status'] == 'AVAILABLE') {
                            $areOrWereFreeNonHiddenTicketsAvailable = true;
                            $freeNonHiddenTicketsAvailableNow += $ticketClass['quantity_total'] - $ticketClass['quantity_sold'];
                        }
                        else if($ticketClass['on_sale_status'] == 'NOT_YET_ON_SALE') {
                            $freeNonHiddenTicketsAvailableInTheFuture += $ticketClass['quantity_total'] ;
                            if(is_null($freeNonHiddenTicketsAvailableInTheFutureDate)) {
                                $freeNonHiddenTicketsAvailableInTheFutureDate = new DateTime($ticketClass['sales_start'], new \DateTimeZone('UTC'));
                            }
                            else {
                                $anotherTicketClassSalesStart = new DateTime($ticketClass['sales_start'], new \DateTimeZone('UTC'));
                                if($anotherTicketClassSalesStart < $freeNonHiddenTicketsAvailableInTheFutureDate) {
                                    $freeNonHiddenTicketsAvailableInTheFutureDate = $anotherTicketClassSalesStart;
                                }
                            }
                        }
                        
                        /*
                            It's also possible that ticket sale of all tickets has stopped. That case is detected as "Sold out".
                        */
                    }
                    else {
                        // PAID TICKETS COUNT TO BE IMPLEMENTED YET!!!
                    }
                }
                
                
                if($freeNonHiddenTicketsTotal > 0) {
                    if($freeNonHiddenTicketsAvailableNow > 0) { // FREE TICKETS AVAILABLE_NOW
                        $customFields['eventbritelist_eventbrite_free_tickets_availability'] = 'AVAILABLE_NOW';
                        $customFields['eventbritelist_eventbrite_free_tickets_availability_count'] = $freeNonHiddenTicketsAvailableNow; 
                    }
                    else if($freeNonHiddenTicketsAvailableInTheFuture > 0) { // FREE TICKETS AVAILABLE_IN_THE_FUTURE
                        $customFields['eventbritelist_eventbrite_free_tickets_availability'] = 'AVAILABLE_IN_THE_FUTURE';
                        $customFields['eventbritelist_eventbrite_free_tickets_availability_date'] = $freeNonHiddenTicketsAvailableInTheFutureDate->format(DATE_ATOM);
                    }
                    else { // FREE TICKETS SOLD_OUT
                        $customFields['eventbritelist_eventbrite_free_tickets_availability'] = 'SOLD_OUT';
                    }
                }
                else { // FREE TICKETS NOT_AVAILABLE
                    $customFields['eventbritelist_eventbrite_free_tickets_availability'] = 'NOT_AVAILABLE';
                }
                
                // PAID TICKETS COUNT TO BE IMPLEMENTED YET!!!
                $customFields['eventbritelist_eventbrite_paid_tickets_available'] = 'NOT_AVAILABLE';
            }
            else { // EVENT IS IN THE PAST OR HAS STARTED/ENDED
                $customFields['eventbritelist_eventbrite_free_tickets_availability'] = 'NOT_AVAILABLE';
                $customFields['eventbritelist_eventbrite_paid_tickets_availability'] = 'NOT_AVAILABLE';
            }
                        
            insertOrUpdateEvent($event['event']['id'], $eventData, $customFields);
        }
        else {
            unpublishEventIfExists($event['event']['id']);
        }
    }
}

register_activation_hook(__FILE__, 'eventbritelist_activation');

function eventbritelist_activation() {
    if (! wp_next_scheduled ( 'eventbritelist_hourly' )) {
	    wp_schedule_event(time(), 'hourly', 'eventbritelist_hourly');
    }
}

register_deactivation_hook(__FILE__, 'eventbritelist_deactivation');

function eventbritelist_deactivation() {
	wp_clear_scheduled_hook('eventbritelist_hourly');
}

function eventbritelist_init() {
    $args = [
            'labels' => ['name' => __( 'Eventbrite Events' ), 'singular_name' => __( 'Eventbrite Event' )],
            'public' => false, 
            'rewrite' => array('slug' => 'event'),
            'supports' => array( 'title', 'author', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
            'public' => true,
            'has_archive' => true,
            'menu_position'      => null,
		    'capability_type'    => 'post',
        ];
    register_post_type( 'eventbritelist_event', $args); 
}

add_action( 'init', 'eventbritelist_init' );

function insertOrUpdateEvent($eventbriteEventId, $eventData, $customFields) {
    if(empty($eventbriteEventId)) {
        return false;
    }
    
    $args = [
    	'meta_key'         => EVENTBRITELIST_EVENT_KEY,
    	'meta_value'       => $eventbriteEventId,
    	'post_type'        => 'eventbritelist_event',
    	'post_status'      => 'publish,future',
    	'suppress_filters' => true 
    ];
    $existingEvents = get_posts($args);
    
    if($existingEvents) {
        if(count($existingEvents) == 1) {
            $existingEvent = array_shift($existingEvents);
            
            $eventData['ID'] = $existingEvent->ID;
            if($postId = wp_update_post($eventData)) {
                foreach($customFields as $customFieldName => $customFieldValue) {
                    if (!add_post_meta($postId, $customFieldName, $customFieldValue, true )) { 
                        update_post_meta($postId, $customFieldName, $customFieldValue);
                    }
                }
            }
        }
        else {
            wp_die("There were multiple instances of the same event with the ID $eventId found. Please delete the ones you don't want to keep, until then synching of this event is paused.");
        }
    }
    else {
        if($postId = wp_insert_post( $eventData )) {
            if (!add_post_meta($postId, EVENTBRITELIST_EVENT_KEY, $eventbriteEventId, true ) ) { 
                update_post_meta($postId, EVENTBRITELIST_EVENT_KEY, $eventbriteEventId);
            }
            foreach($customFields as $customFieldName => $customFieldValue) {
                if ( ! add_post_meta($postId, $customFieldName, $customFieldValue, true ) ) { 
                    update_post_meta($postId, $customFieldName, $customFieldValue);
                }
            }
        }
    }  
}

function unpublishEventIfExists($eventbriteEventId) {
    $args = [
    	'orderby'          => 'date',
    	'order'            => 'DESC',
    	'meta_key'         => EVENTBRITELIST_EVENT_KEY,
    	'meta_value'       => $eventbriteEventId,
    	'post_type'        => 'eventbritelist_event',
    	'post_status'      => 'publish',
    	'suppress_filters' => true 
    ];
    $existingEvents = get_posts($args);
    
    if($existingEvents) {
        foreach($existingEvents as $existingEvent) {
            wp_delete_post($existingEvent->ID);
        }
    }
}

function eventbritelist_getEventsForProfiles($appProfileKeys) {
    $eventbriteEvents = [];
    
    foreach($appProfileKeys as $appKey => $profiles) {
        $asanaClient = new HttpClient($appKey);
        foreach($profiles as $profile) {
            $returnedEvents = $asanaClient->get("/organizers/$profile/events/", array('status=all'))['events'];
            foreach($returnedEvents as $returnedEvent) {
                $eventbriteEvent = array();
                $eventbriteEvent['event'] = $returnedEvent;
                $eventbriteEvent['venue'] = $asanaClient->get("/venues/" . $returnedEvent['venue_id'] . "/");
                $eventbriteEvent['organizer'] = $asanaClient->get("/organizers/" . $returnedEvent['organizer_id'] . "/");
                $eventbriteEvent['ticketClass'] = $asanaClient->get("/events/" . $returnedEvent['id'] . "/ticket_classes/");
                $eventbriteEvents[] = $eventbriteEvent;
            }
        }
        unset($asanaClient);
    }
    
    return $eventbriteEvents;
}