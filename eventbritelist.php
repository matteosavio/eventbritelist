<?php
/*
Plugin Name: List Eventbrite events
Plugin URI: 
Description: A shortcode that grabs all future events from a profile. Make sure to cache your site, so you're not overusuing the Eventbrite API 
Version: 0.1.0
Author: Digital Ideas
Author URI: http://www.digitalideas.io
*/

// Mae it a cron job: https://tommcfarlin.com/wordpress-cron-jobs/
// create a table for the events: https://codex.wordpress.org/Creating_Tables_with_Plugins

/*add_action( 'wp_loaded', 'dievents_update_events' );

function dievents_update_events {
    echo '<!--aj test-->';
}*/

require_once('eventbrite-sdk-php/HttpClient.php');

add_action('wp_enqueue_scripts', 'dievents_scripts');
function dievents_scripts() {
    wp_enqueue_script('fa', plugins_url( '/js/fontawesome-all.min.js',  __FILE__ ));
    wp_enqueue_style('dievents-style', plugins_url('/css/style.css', __FILE__ ));
	wp_enqueue_script('eventbritelist-script', plugins_url( '/js/app.js', __FILE__ ), array('jquery'));
	
	wp_localize_script('eventbritelist-script', 'ajax_object',
	    array(
    	    'ajax_url' => admin_url( 'admin-ajax.php' )
	    )
    ); 
}

function eventbrite_list($atts = [], $content = null)
{
    if(!isset($atts['profiles']) || empty($atts['profiles'])) {
        return 'Please select one or more Eventbrite profiles to get the events from';
    }
    
    $dataParamaters = ' data-profiles="' . esc_html($atts['profiles']) . '"';
    
    if(isset($atts['timeframe'])) {
        $dataParamaters .= ' data-timeframe="' . esc_html($atts['timeframe']) . '"';
    }
    
    if(isset($atts['show_description'])) {
        $dataParamaters .= ' data-show-description="' . esc_html($atts['show_description']) . '"';
    }
    
    if(isset($atts['show_hidden_tickets'])) {
        $dataParamaters .= ' data-show-hidden-tickets="' . esc_html($atts['show_hidden_tickets']) . '"';
    }
    
    return '<div class="eventbritelist-loading"' . $dataParamaters . '><i class="fas fa-spinner fa-spin"></i></div>';
}
add_shortcode('eventbrite_list', 'eventbrite_list');


add_action( 'wp_ajax_eventbrite_list_ajax', 'eventbrite_list_ajax' );

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
    
    $profiles = array_map('trim', explode(',', $profileCsv));
    
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
    
    // only future events: default
    $timeframe = 'start_date.range_start='.date( "Y-m-d\TH:i:s");
    if(isset($atts['timeframe'])) {
        switch ($atts['timeframe']) {
            case 'past':
                $timeframe = 'start_date.range_end='.date( "Y-m-d\TH:i:s");
                break;
            case 'all':
                $timeframe = '';
                break;
        }
    }
    
    $events = [];
    foreach($profiles as $profile) {
        $events = array_merge(
            $asanaClient->get("/organizers/$profile/events/",
                array(
                    $timeframe,
                    'status=live'
                )
            )['events'],
            $events);
    }
    
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
        
        $ticketsAvailable = 0;
        foreach($ticketClasses['ticket_classes'] as $ticketClass) {
            if(!$ticketClass['hidden'] || $showHiddenTickets) {
                $ticketsAvailable += $ticketClass['quantity_total'] - $ticketClass['quantity_sold'];
            }
        }
        
        $ticketsAvailableString = '';
        if($ticketsAvailable <= 0) {
            $ticketsAvailableString = '&otimes; no tickets left';
            $ticketsAvailableString = '<a href="'.$event['url'].'" class="button soldout">' . $ticketsAvailableString . '</a>';
        }
        else if($ticketsAvailable <= 3) {
            if($event['is_free']) {
                $ticketsAvailableString = 'only ' . $ticketsAvailable . ' free tickets avaiable &#8811;';
            }
            else {
                $ticketsAvailableString = 'only ' . $ticketsAvailable . ' tickets avaiable &#8811;';
            }
            $ticketsAvailableString = '<a href="'.$event['url'].'" class="button limited">' . $ticketsAvailableString . '</a>';
        }
        else {
            if($event['is_free']) {
                $ticketsAvailableString = $ticketsAvailable . ' free tickets avaiable &#8811;';
            }
            else {
                $ticketsAvailableString = $ticketsAvailable . ' tickets avaiable &#8811;';
            }
            $ticketsAvailableString = '<a href="'.$event['url'].'" class="button available">' . $ticketsAvailableString . '</a>';
        }
        
        $eventStrings[$eventOrder]  = '<div class="event">';
        $eventStrings[$eventOrder] .= '<div class="image"><img src="' . $event['logo']['url'] . '"></div>';
        $eventStrings[$eventOrder] .= '<div class="time"><i class="fal fa-calendar"></i> ' . $date->format('l, j. F Y H:i') . '</div>';
        $eventStrings[$eventOrder] .= '<div class="title"><a href="'.$event['url'].'">' . $event['name']['html'] . '</a> by <a href="' . (isset($orgainizer['website'])?$orgainizer['website']:$orgainizer['url']) . '">' . $orgainizer['name'] .  '</a></div>';
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
    
    $content .= "\n".'</div>';
    
    return $content;
}