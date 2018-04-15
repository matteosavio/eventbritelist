jQuery(document).ready(function($) {
    $("div.eventbritelist-loading").each(function( index ) {
        var data = {
        	'action': 'eventbrite_list_ajax',
        	'profiles': $(this).data('profiles'),
        	'timeframe':  $(this).data('timeframe'),
        	'show_description': $(this).data('show-description'),
        	'show_hidden_tickets': $(this).data('show-hidden-tickets')
        };
        var $t = $(this);
        jQuery.post(ajax_object.ajax_url, data, function(response) {
            $t.replaceWith(response);
        });
    });
});