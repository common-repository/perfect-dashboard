<?php
/**
 * @version 1.1.0
 * @package Perfect Dashboard
 * @copyright © 2015 Perfect Web sp. z o.o., All rights reserved. http://www.perfect-web.co
 * @license GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @author Perfect-Web
 */

// No direct access
function_exists('add_action') or die;

add_action('admin_menu', array('PerfectDashboardAdmin', 'adminMenu'));
add_action('admin_init', array('PerfectDashboardAdmin', 'init'));

class PerfectDashboardAdmin
{

    public function __construct()
    {

    }

    /**
     * Add menu entry with plug-in settings page
     */
    public static function adminMenu()
    {

        add_menu_page(
            __('Perfect Dashboard', 'perfectdashboard'),
            __('Perfect Dashboard', 'perfectdashboard'),
            'manage_options',
            'perfectdashboard-config',
            array(__CLASS__, 'displayConfiguration')
        );
    }

    /**
     * Add media and ajax actions
     */
    public static function init()
    {

        wp_register_style('perfect-dashboard',
            plugins_url('media/css/style.css', PERFECTDASHBORD_PATH . '/perefectdashboard.php'),
            array(), '0.1');
        wp_enqueue_style('perfect-dashboard');
        wp_register_script('script',
            plugins_url('media/js/script.js', PERFECTDASHBORD_PATH . '/perefectdashboard.php'),
            array('jquery'), '1.11.3');
        wp_localize_script('script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
        wp_enqueue_script('script');

        if (defined('DOING_AJAX')) {
            add_action('wp_ajax_perfectdashboard_save_config', array(__CLASS__, 'saveConfig'));
        }

        add_action( 'admin_notices', array(__CLASS__, 'configNotice'));

    }

    /**
     * Display template of settings page
     */
    public function displayConfiguration()
    {

        $key = get_option('perfectdashboard-key', null);

        $site_offline = get_option('perfectdashboard-site-offline', null);

        require_once PERFECTDASHBORD_PATH . '/tmpl/tmpl-admin.php';

    }

    /**
     * Save key into db
     */
    public static function saveConfig()
    {
        require_once __DIR__ . '/perfectdashboard-filterinput-class.php';

        $filter = PerfectDashboardFilterInput::getInstance();

        if (isset($_POST['key_value']) && $_POST['key_value']) {
            $key = $filter->clean($_POST['key_value'], 'cmd');
            update_option('perfectdashboard-key', $key);
        }

        if (isset($_POST['site_offline'])) {
            $site_offline = $filter->clean($_POST['site_offline'], 'int');
            update_option('perfectdashboard-site-offline', $site_offline);
        }
    }

    public function configNotice()
    {
        global $hook_suffix;

        $ping = get_option('perfectdashboard-ping');

        if ($hook_suffix == 'plugins.php' AND empty($ping)) {
            $plugins = get_option( 'active_plugins' );
            $active  = false;
            foreach ( $plugins as $i => $plugin ) {
                if ( strpos($plugin, '/perfectdashboard.php') !== false ) {
                    $active = true;
                    break;
                }
            }
            if ($active) {
            ?>
                <div class="updated notice">
                    <p><?php printf(__( '<strong>Well done!</strong> You are just a step away from automating updates & backups on this website <a href="admin.php?page=perfectdashboard-config" class="uk-button uk-button-primary">Click here to configure</a>', 'pwebcore' )); ?></p>
                </div>
            <?php
            }
        }
    }
}