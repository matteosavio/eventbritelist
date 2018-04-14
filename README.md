# eventbritelist
A wordpress plugin that shows a list of Evenbrite events

Set the DIEVENTS_EVENTBRITE_APP_TOKEN in your wp-config.php to your private event token.

The shortcode is [eventbrite_list], with the following parameters:
* profiles: comma seperated list of profiles which events should be included. Be sure to have access to those profiles through your app token
* show_description: should the description of events be shown as a teaser (160 characters)
* show_hidden_tickets: should hidden tickets be included in the amount of tickets left.
