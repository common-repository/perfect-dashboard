/*!
 * @version 1.0.0
 * @package Perfect Dashboard
 * @copyright © 2015 Perfect Web sp. z o.o., All rights reserved. http://www.perfect-web.co
 * @license GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @author Perfect-Web
 */

jQuery(document).ready(function($) {
    var btn = $('.perfectdashboard-settings-button');

    if(btn.length) {
        btn.on('click', function(e) {
            e.preventDefault();
            var settingsScreen = $('.perfectdashboard-settings');
            var startScreen = $('.perfectdashboard-start');

            if(settingsScreen.hasClass('perfectdashboard-view-active')) {
                settingsScreen.removeClass('perfectdashboard-view-active');
                btn.html(btn.attr('data-open'));

                setTimeout(function() {
                    settingsScreen.addClass('perfectdashboard-view-inactive');
                    startScreen.removeClass('perfectdashboard-view-inactive');

                    setTimeout(function() {
                        startScreen.addClass('perfectdashboard-view-active');
                    }, 25);
                }, 200);
            } else {
                startScreen.removeClass('perfectdashboard-view-active');
                btn.html(btn.attr('data-close'));

                setTimeout(function() {
                    startScreen.addClass('perfectdashboard-view-inactive');
                    settingsScreen.removeClass('perfectdashboard-view-inactive');

                    setTimeout(function() {
                        settingsScreen.addClass('perfectdashboard-view-active');
                    }, 25);
                }, 200);
            }
        });

        $('#perfectdashboard_save_config').on('click', function(e) {
            e.preventDefault();
            var key = $('#perfectdashboard_key').val();
            var siteOffline = $('input[type="radio"][name="site_offline"]:checked').val();

            var data = {
                'action': 'perfectdashboard_save_config',
                'key_value': key,
                'site_offline': siteOffline
            };

            $.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    console.log(response);
                }
            });
        });
    }
});