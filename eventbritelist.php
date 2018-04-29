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
    
    if($_GET['create'] == 'yes') {
        eventbritelist_read_events();
        wp_die('yoooow!');
    }
    
    $args = [
    	'orderby'          => 'date',
    	'order'            => 'ASC',
    	'post_type'        => 'eventbritelist_event',
        'post_status'      => $status,
    	'suppress_filters' => true,
        'posts_per_page'   => 12,
    ];
    $events = get_posts($args);
    
    $content .= '<div class="eventbritelist">'."\n";
    foreach($events as $event) {
        
        $freeTicketsAvailable = get_post_meta($event->ID, 'eventbritelist_eventbrite_free_tickets_available', true);
        $paidTicketsAvailable = get_post_meta($event->ID, 'eventbritelist_eventbrite_paid_tickets_available', true);
        $eventUrl = get_post_meta($event->ID, 'eventbritelist_eventbrite_link', true);
        $ticketsAvailableString = '';
        
        if($freeTicketsAvailable != 'NONE') {
            $freeTicketsAvailable = intval($freeTicketsAvailable);
            if($freeTicketsAvailable <= 0) {
                $ticketsAvailableString = '&otimes; no tickets left';
                $ticketsAvailableString = '<a href="' . $eventUrl . '" class="button soldout">' . $ticketsAvailableString . '</a>';
            }
            else if($freeTicketsAvailable <= 3) {
                //if($event['is_free']) {
                    $ticketsAvailableString = 'only ' . $freeTicketsAvailable . ' free tickets avaiable &#8811;';
                /*}
                else {
                    $ticketsAvailableString = 'only ' . $freeTicketsAvailable . ' tickets avaiable &#8811;';
                }*/
                $ticketsAvailableString = '<a href="' . $eventUrl . '" class="button limited">' . $ticketsAvailableString . '</a>';
            }
            else {
                //if($event['is_free']) {
                    $ticketsAvailableString = $freeTicketsAvailable . ' free tickets avaiable &#8811;';
                /*}
                else {
                    $ticketsAvailableString = $freeTicketsAvailable . ' tickets avaiable &#8811;';
                }*/
                $ticketsAvailableString = '<a href="' . $$eventUrl . '" class="button available">' . $ticketsAvailableString . '</a>';
            }
        }
        if($paidTicketsAvailable != 'NONE') {
            $paidTicketsAvailable = intval($paidTicketsAvailable);
        }
        
        
        $content .= '<div class="event">';
        $content .= '<div class="image"><img src="' . get_post_meta($event->ID, 'eventbritelist_eventbrite_image', true) . '"></div>';
        $content .= '<div class="time"><i class="fal fa-calendar"></i> ' . get_the_time( "l, j. F Y H:i", $event->ID ) . '</div>';
        $content .= '<div class="title"><a href="' . $eventUrl  . '">' . $event->post_title . '</a>';
        
        // by <a href="' . (isset($orgainizer['website'])?$orgainizer['website']:$orgainizer['url']) . '">' . $orgainizer['name'] .  '</a>
        $content .= '</div>';
        $content .= '<div class="location"><i class="fal fa-thumbtack"></i> <a href="http://www.google.com/maps/place/' . get_post_meta($event->ID, 'eventbritelist_eventbrite_location_latitude', true) . ',' . get_post_meta($event->ID, 'eventbritelist_eventbrite_location_longitude', true) . '" target="_blank">' . get_post_meta($event->ID, 'eventbritelist_eventbrite_location', true) . '</a></div>';
        $content .= '<div class="description">';
        if($showDescription) {
        //    $content .= mb_strimwidth($event['description']['text'], 0, 160, "...") . '<br />';
        }
        $content .= $ticketsAvailableString . '</div>';
        $content .= '</div>';
    }
    
    $content .= '</div>'."\n";
    return $content;
}
add_shortcode('eventbrite_list', 'eventbrite_list');


/*add_action( 'wp_ajax_eventbrite_list_ajax', 'eventbrite_list_ajax' );

function eventbrite_list_ajax() {
    echo eventbritelist_get_event_list(
        $_POST['profiles'],
        (isset($_POST['timeframe']) ? $_POST['timeframe'] : 'future'),
        (isset($_POST['show_description']) ? $_POST['show_description'] : true ),
        (isset($_POST['show_hidden_tickets']) ? $_POST['show_hidden_tickets'] : false )
    );
	wp_die();
}

function eventbritelist_get_event_list($profileCsv, $timeframe = 'future', $showDescription = true, $showHiddenTickets = false) {
    if (!defined('EVENTBRITELIST_APP_TOKEN')) {
        return 'Please define EVENTBRITELIST_APP_TOKEN in wp-config.php with your app token';
    }
    
    $asanaClient = new HttpClient(EVENTBRITELIST_APP_TOKEN);
    
    $profiles = array();
    
    
    
    $content .= '<div class="eventbritelist">'."\n";
    
    $eventStrings = [];
    foreach($events as $event) {
        $date = new DateTime($event['start']['local']);
        
        $i = 0;
        while(isset($eventStrings[$date->getTimestamp() + $i])) {
            $i++;
        }
        $eventOrder = $date->getTimestamp() + $i;
        $venue = $asanaClient->get("/venues/" . $event['venue_id'] . "/");
        $orgainizer = $asanaClient->get("/organizers/" . $event['organizer_id'] . "/");
        $ticketClasses = $asanaClient->get("/events/" . $event['id'] . "/ticket_classes/");
        // /events/:id/ticket_classes/:ticket_class_id/
                
        $eventStrings[$eventOrder]  = '<div class="event">';
        $eventStrings[$eventOrder] .= '<div class="image"><img src="' . $event['logo']['url'] . '"></div>';
        $eventStrings[$eventOrder] .= '<div class="time"><i class="fal fa-calendar"></i> ' . $date->format('l, j. F Y H:i') . '</div>';
        $eventStrings[$eventOrder] .= '<div class="title"><a href="' . $event['url'] . '">' . $event['name']['html'] . '</a> by <a href="' . (isset($orgainizer['website'])?$orgainizer['website']:$orgainizer['url']) . '">' . $orgainizer['name'] .  '</a></div>';
        $eventStrings[$eventOrder] .= '<div class="location"><i class="fal fa-thumbtack"></i> <a href="http://www.google.com/maps/place/' . $venue['latitude'] . ',' . $venue['longitude'] . '" target="_blank">' . $venue['name'] . ', ' . $venue['address']['city'] . ', ' . $venue['address']['country'] . '</a></div>';
        $eventStrings[$eventOrder] .= '<div class="description">';
        if($showDescription) {
            $eventStrings[$eventOrder] .= mb_strimwidth($event['description']['text'], 0, 160, "...") . '<br />';
        }
        $eventStrings[$eventOrder] .= $ticketsAvailableString . '</div>';
        $eventStrings[$eventOrder] .= '</div>';
        
        
    }
    ksort($eventStrings);
    $content .= implode("\n", $eventStrings);
    
    $content .= "\n" . '</div>';
    
    return $content;
}*/

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
            $eventBeginDate = new DateTime($event['event']['start']['local']);
            $eventData = [
              'post_title'    => wp_strip_all_tags($event['event']['name']['text']),
              'post_content'  => '',
              'post_status'   => 'publish',
              'post_type' => 'eventbritelist_event',
              'post_author'   => 1,
              'post_excerpt'  => mb_strimwidth($event['description']['text'], 0, 160, "..."),
              'post_date' => $eventBeginDate->format('Y-m-d H:i:s'),
            ];
            
            $areFreeTicketsAvailable = false;
            $freeTicketsAvailable = 0;
            foreach($event['ticketClass']['ticket_classes'] as $ticketClass) {
                if(!$ticketClass['hidden']) {
                    $areFreeTicketsAvailable = true;
                    $freeTicketsAvailable += $ticketClass['quantity_total'] - $ticketClass['quantity_sold'];
                }
            }
            
            $arePaidTicketsAvailable = false;
            $paidTicketsAvailable = 0;
            
            $customFields = [
                'eventbritelist_eventbrite_link' => $event['event']['url'],
                'eventbritelist_eventbrite_location' => $event['venue']['name'] . ', ' . $event['venue']['address']['city'] . ', ' . $event['venue']['address']['country'],
                'eventbritelist_eventbrite_location_longitude' => $event['venue']['longitude'],
                'eventbritelist_eventbrite_location_latitude' => $event['venue']['latitude'],
                'eventbritelist_eventbrite_free_tickets_available' => $freeTicketsAvailable,
                'eventbritelist_eventbrite_image' => $event['event']['logo']['url'],
                'eventbritelist_eventbrite_organizer_name' => $event['organizer']['name'],
                'eventbritelist_eventbrite_free_tickets_available' => ($areFreeTicketsAvailable?$freeTicketsAvailable:'NONE'),
                'eventbritelist_eventbrite_paid_tickets_available' => ($arePaidTicketsAvailable?$paidTicketsAvailable:'NONE'),
                'eventbritelist_eventbrite_organizer_url' => isset($event['organizer']['website'])?$event['organizer']['website']:$event['organizer']['url']                
            ];
            
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