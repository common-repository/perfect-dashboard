<?php
/**
 * Plugin Name: Perfect Dashboard
 * Plugin URI: https://perfectdashboard.co
 * Description:
 * Version: 1.1
 * Text Domain: perfectdashboard
 * Author: Perfect-Web
 * Author URI: https://perfectdashboard.co
 * License: GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
function_exists('add_action') or die;

if (version_compare($GLOBALS['wp_version'], '3.5', '>=') AND version_compare(PHP_VERSION, '5.3', '>=')) {

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $data = get_plugin_data(__FILE__, false, false);
    define('PERFECTDASHBORD_PATH', dirname(__FILE__));
    define('PERFECTDASHBOARD_VERSION', $data['Version']);

    require_once PERFECTDASHBORD_PATH . '/class/perfectdashboard-class.php';

    if(is_admin()) {
        require_once PERFECTDASHBORD_PATH . '/class/perfectdashboard-admin-class.php';
    }

}
else {

    function perfectRequirementsNotice() {
        ?>
        <div class="error">
            <p><?php printf(__( 'Perfect Dashboard plugin requires WordPress %s and PHP %s', 'pwebcore' ), '3.5+', '5.3+'); ?></p>
         </div>
        <?php
    }
    add_action( 'admin_notices', 'perfectRequirementsNotice' );
}
