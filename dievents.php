<?php
/*
Plugin Name: Eventbrite event functions for wordpress
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

function dievents_eventbrite_events($atts = [], $content = null)
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
        
        $eventStrings[$eventOrder]  = '<div class="event">';
        $eventStrings[$eventOrder] .= '<div class="image"><img src="' . $event['logo']['url'] . '"></div>';
        $eventStrings[$eventOrder] .= '<div class="title"><a href="'.$event['url'].'">' . $event['name']['html'] . '</a></div>';
        $eventStrings[$eventOrder] .= '<div class="time"><i class="fal fa-calendar"></i> ' . $date->format('l, j. F Y H:i') . '</div>';
        $eventStrings[$eventOrder] .= '<div class="location"><i class="fal fa-thumbtack"></i> <a href="http://www.google.com/maps/place/' . $venue['latitude'] . ',' . $venue['longitude'] . '" target="_blank">' . $venue['name'] . ', ' . $venue['address']['city'] . ', ' . $venue['address']['country'] . '</a></div>';
        $eventStrings[$eventOrder] .= '<div class="description">' . mb_strimwidth($event['description']['text'], 0, 230, "...") . '<br /><a href="'.$event['url'].'"><i class="fal fa-external-link"></i> Register on Eventbrite</a></div>';
        $eventStrings[$eventOrder] .= '</div>';
    }
    ksort($eventStrings);
    
    $content .= implode("\n", $eventStrings);
    
    $content .= "\n".'</div>';
    
    return $content;
}
add_shortcode('dievents_eventbrite_events', 'dievents_eventbrite_events');