<?php
/*
Plugin Name: List Eventbrite events on your site
Plugin URI: https://wordpress.org/plugins/health-check/
Description: A shortcode that grabs all future events from a profile. Make sure to cache your site, so you're not overusuing the Eventbrite API 
Version: 0.1.0
Author: Digital IDeas
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
    wp_enqueue_script( 'fa', plugins_url( '/js/fontawesome.min.js', __FILE__ ));
    wp_enqueue_script( 'fa-light', plugins_url( '/js/fa-light.min.js', __FILE__ ));
    wp_enqueue_style( 'dievents-style', plugins_url('/css/style.css',__FILE__ ));
}

function eventbrite_list($atts = [], $content = null)
{
    if(empty($content)) {
        $content = "";
    }
    
    if (!defined('DIEVENTS_EVENTBRITE_APP_TOKEN')) {
        return 'Please define DIEVENTS_EVENTBRITE_APP_TOKEN in wp-config.php with your app token';
    }
    
    $client = new HttpClient(DIEVENTS_EVENTBRITE_APP_TOKEN);
    
    $profiles = array();
    
    if(empty($atts['profiles'])) {
        return 'Please select one or more Eventbrite profiles to get the events from';
    }
    
    // should I use the regex preg_split approach instead this more readable one? https://stackoverflow.com/questions/19347005/how-can-i-explode-and-trim-whitespace
    $profiles = array_map('trim', explode(',', $atts['profiles']));
    
    $timeframe = 'future';
    
    if(!empty($atts['timeframe'])) {
        $timeframe = $atts['timeframe'];
    }
    
    $events = [];
    
    foreach($profiles as $profile) {
        $events = array_merge($client->get("/organizers/$profile/events/", array('start_date.range_start='.date( "Y-m-d\TH:i:s")))['events'], $events);
    }
    
    $content .= '<div id="dievents">'."\n";
    
    $eventStrings = [];
    foreach($events as $event) {
        $date = new DateTime($event['start']['local']);
        
        $i = 0;
        while(isset($eventStrings[$date->getTimestamp() + $i])) {
            $i++;
        }
        $eventOrder = $date->getTimestamp() + $i;
        $venue = $client->get("/venues/" . $event['venue_id'] . "/");
        $orgainizer = $client->get("/organizers/" . $event['organizer_id'] . "/");
        $ticketClasses = $client->get("/events/" . $event['id'] . "/ticket_classes/");
        // /events/:id/ticket_classes/:ticket_class_id/
        
        $ticketsAvailable = 0;
        foreach($ticketClasses['ticket_classes'] as $ticketClass) {
            $ticketsAvailable = $ticketClass['quantity_total'] - $ticketClass['quantity_sold'];
        }
        
        
        $ticketsAvailableString = '';
        if($ticketsAvailable <= 0) {
            $ticketsAvailableString = '<span style="color: red"><i class="fal fa-times-circle"></i> <strong>sold out</strong></span>';
        }
        else if($ticketsAvailable <= 3) {
            if($event['is_free']) {
                $ticketsAvailableString = '<span style="color: orange"> <i class="fal fa-external-link"></i> <strong> only ' . $ticketsAvailable . '</strong> free tickets left</span>';
            }
            else {
                $ticketsAvailableString = '<span style="color: orange"> <i class="fal fa-external-link"></i> <strong> only ' . $ticketsAvailable . '</strong> tickets left</span>';
            }
        }
        else {
            if($event['is_free']) {
                $ticketsAvailableString = '<span style="color: green"><i class="fal fa-external-link"></i> <strong>' . $ticketsAvailable . '</strong> free tickets left</span>';
            }
            else {
                $ticketsAvailableString = '<span style="color: green"><i class="fal fa-external-link"></i> <strong>' . $ticketsAvailable . '</strong> tickets left</span>';
            }
        }
        
        $eventStrings[$eventOrder]  = '<div class="event">';
        $eventStrings[$eventOrder] .= '<div class="image"><img src="' . $event['logo']['url'] . '"></div>';
        $eventStrings[$eventOrder] .= '<div class="title"><a href="'.$event['url'].'">' . $event['name']['html'] . '</a> by <a href="' . (isset($orgainizer['website'])?$orgainizer['website']:$orgainizer['url']) . '">' . $orgainizer['name'] .  '</a></div>';
        $eventStrings[$eventOrder] .= '<div class="time"><i class="fal fa-calendar"></i> ' . $date->format('l, j. F Y H:i') . '</div>';
        $eventStrings[$eventOrder] .= '<div class="location"><i class="fal fa-thumbtack"></i> <a href="http://www.google.com/maps/place/' . $venue['latitude'] . ',' . $venue['longitude'] . '" target="_blank">' . $venue['name'] . ', ' . $venue['address']['city'] . ', ' . $venue['address']['country'] . '</a></div>';
        $eventStrings[$eventOrder] .= '<div class="description">' . mb_strimwidth($event['description']['text'], 0, 160, "...") . '<br /><a href="'.$event['url'].'">' . $ticketsAvailableString . '</a></div>';
        $eventStrings[$eventOrder] .= '</div>';
    }
    ksort($eventStrings);
    $content .= implode("\n", $eventStrings);
    
    $content .= "\n".'</div>';
    
    return $content;
}
add_shortcode('eventbrite_list', 'eventbrite_list');