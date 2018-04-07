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
    $showDescription = true;
    $showHiddenTickets = false;
    
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
    
    if(isset($atts['show_description']) && ($atts['show_description'] == "false")) {
        $showDescription = false;
    }
    
    if(isset($atts['show_hidden_tickets']) && ($atts['show_hidden_tickets'] == "true")) {
        $showHiddenTickets = true;
    }
    
    // should I use the regex preg_split approach instead this more readable one? https://stackoverflow.com/questions/19347005/how-can-i-explode-and-trim-whitespace
    $profiles = array_map('trim', explode(',', $atts['profiles']));
    
    $timeframe = 'future';
    
    if(!empty($atts['timeframe'])) {
        $timeframe = $atts['timeframe'];
    }
    
    $events = [];
    
    foreach($profiles as $profile) {
        $events = array_merge(
            $client->get("/organizers/$profile/events/",
                array(
                    'start_date.range_start='.date( "Y-m-d\TH:i:s"),
                    'status=live'
                )
            )['events'],
            $events);
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
    
    if ( ! is_plugin_active( 'wp-super-cache/wp-cache.php' ) ) {
        // Stop activation redirect and show error
        return '<div style="color: red"><i class="fal fa-exclamation-triangle"></i> No cache installed (currently detecting: <a href="https://wordpress.org/plugins/wp-super-cache/">WP Super Cache</a>). Please install and cache the content of your website. This plugin is heavily using the Eventbrite API, and is not suitable for heavy traffic. If you want us to support more caching plugins <a href="http://digitalideas.io/">drop us a line</a> - your Digital Ideas team!</div>' . $content;
    }
    return $content;
}
add_shortcode('eventbrite_list', 'eventbrite_list');