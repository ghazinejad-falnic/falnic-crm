/* Falnic CRM Admin Shared Script */
jQuery(function($){
    'use strict';

    // Common helper: show toast-like notice.
    window.falnicCrmNotice = function(message, type) {
        type = type || 'success';
        var cls = type === 'error' ? 'notice notice-error' : 'notice notice-success';
        var $notice = $('<div class="'+cls+' is-dismissible"><p>'+message+'</p></div>');
        $('.wrap').first().prepend($notice);
        setTimeout(function(){ $notice.fadeOut(300, function(){ $notice.remove(); }); }, 4000);
    };
});
