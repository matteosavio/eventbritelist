jQuery(document).ready(function() {
    jQuery("button.moreevents").click(function() {
        jQuery(".eventbritelist .list .event.hidden").removeClass('hidden');
        jQuery(this).hide();
    });
});