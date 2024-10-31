<?php
/**
 * @version 1.1.1
 * @package Perfect Dashboard
 * @copyright © 2015 Perfect Web sp. z o.o., All rights reserved. http://www.perfect-web.co
 * @license GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @author Perfect-Web
 */

// No direct access
function_exists('add_action') or die;

require_once __DIR__ . '/perfectdashboard-filterinput-class.php';

$perfect_dashboard = new PerfectDashboard();

class PerfectDashboard {

    public function __construct() {

        register_activation_hook( dirname(__DIR__) . '/perfectdashboard.php', array('PerfectDashboard','onInstall') );
        register_uninstall_hook( dirname(__DIR__) . '/perfectdashboard.php', array('PerfectDashboard', 'onUninstall') );
        add_action( 'init', array($this, 'processPost') );
        add_action( 'init', array($this, 'siteOffline'));
    }

    public static function processPost() {

        if (isset($_GET['perfect']) && $_GET['perfect'] == 'dashboard') {

            require_once PERFECTDASHBORD_PATH . '/class/perfectdashboard-api-class.php';

            // Check parameters and create an object if task name and secure_key are set
            $filter = PerfectDashboardFilterInput::getInstance();
            if (isset($_POST['secure_key']) && $_POST['secure_key']) {
                $secure_key = $filter->clean($_POST['secure_key'], 'cmd');
            } else {
                $secure_key = null;
            }

            if (isset($_POST['task']) && $_POST['task']) {
                $task = $filter->clean($_POST['task'], 'cmd');
            } else {
                $task = null;
            }

            $perfectdashboard_api = new PerfectDashboardAPI($secure_key, $task);
        }
    }

    public static function onInstall() {
        self::setSecureKey();

        //check and fix the automaticupdates
        self::checkAndRepairAutomaticUpdates();

        self::deleteExternalFiles();
    }

    public static function onUninstall() {

        self::uninstallAkeebaSolo();

        self::deleteExternalFiles();

        delete_option('perfectdashboard-key');
        delete_option('perfectdashboard-ping');
        delete_option('perfectdashboard-site-offline');
        delete_option('perfectdashboard-backup_dir');

        // TODO remove in 1.2
        delete_option('perfectdashboard_akeeba_access');

    }

    public static function checkAndRepairAutomaticUpdates()
    {
        // setup file path
        $file = ABSPATH . '/wp-config.php';

        //check if file exists
        if (file_exists($file)) {
            // grab content of that file
            $content = file_get_contents($file);

            // search for automatic updater
            preg_match('/(?:define\(\'AUTOMATIC_UPDATER_DISABLED\'\,.)(false|true)(?:\)\;)/i', $content, $match);

            // if $match empty we don't have this variable in file
            if (!empty($match)) {
                // check if constans is true
                if (filter_var($match[1], FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }

                // modify this constans : )
                $content = str_replace($match[0],
                    "define('AUTOMATIC_UPDATER_DISABLED', true); /* Perfectdashboard modification */", $content);
                // save it to file
                file_put_contents($file, $content);
            } else {
                // so lets create this constans : )
                $content = str_replace('/**#@-*/',
                    "define('AUTOMATIC_UPDATER_DISABLED', true); /* Perfectdashboard modification */" . PHP_EOL . "/**#@-*/",
                    $content);
                // save it to file
                file_put_contents($file, $content . PHP_EOL);
            }
        }
    }

    private static function setSecureKey()
    {
        $secure_key = md5(uniqid('perfectsecurekey'));

        update_option('perfectdashboard-key', $secure_key);
    }

    private static function uninstallAkeebaSolo() {

        global $wpdb;

        $ak_access = get_option('perfectdashboard_akeeba_access');

        // For childs installed before version 1.1
        if (!empty($ak_access)) {
            $ak_access     = unserialize(call_user_func('ba'.'se'.'64'.'_decode', $ak_access));
            $perfix_db     = $ak_access['ak_prefix_db'];
            $perfix_folder = $ak_access['ak_prefix_folder'];

            $akeeba_dirs = glob(ABSPATH . '*_perfectdashboard_akeeba');

            if(!empty($akeeba_dirs)) {
                foreach($akeeba_dirs as $directory) {
                    self::recursiveRemoveDirectory($directory);
                }
            }

            $sql = 'DROP TABLE '
                . '`'.$perfix_db.'perfectdashboard_akeeba_akeeba_common`, '
                . '`'.$perfix_db.'perfectdashboard_akeeba_ak_params`, '
                . '`'.$perfix_db.'perfectdashboard_akeeba_ak_profiles`, '
                . '`'.$perfix_db.'perfectdashboard_akeeba_ak_stats`, '
                . '`'.$perfix_db.'perfectdashboard_akeeba_ak_storage`, '
                . '`'.$perfix_db.'perfectdashboard_akeeba_ak_users`;';

            $drop = $wpdb->query($sql);

            delete_option('perfectdashboard_akeeba_access');

            if (get_option('perfectdashboard-backup_dir')) {
                delete_option('perfectdashboard-backup_dir');
            }
        } elseif (($backup_dir = get_option('perfectdashboard-backup_dir'))) {
            // For childs version >=1.1
            $backup_path   = ABSPATH.'/'.$backup_dir;

            // Get db prefix from akeeba config file.
            $config_file = $backup_path.'/Solo/assets/private/config.php';

            if (file_exists($config_file)) {
                $config = @file_get_contents($config_file);

                if ($config !== false) {
                    $config = explode("\n", $config, 2);

                    if (count($config) >= 2) {
                        $config = json_decode($config[1]);
                        $prefix_db = isset($config->prefix) ? $config->prefix : null;

                        if ($prefix_db) {
                            $sql = "DROP TABLE IF EXISTS `".$prefix_db."akeeba_common` ,
                                `".$prefix_db."ak_params` ,
                                `".$prefix_db."ak_profiles` ,
                                `".$prefix_db."ak_stats` ,
                                `".$prefix_db."ak_storage` ,
                                `".$prefix_db."ak_users` ;";

                            $drop = $wpdb->query($sql);
                        }
                    }

                }
            }

            self::recursiveRemoveDirectory($backup_path);

            delete_option('perfectdashboard-backup_dir');
        }
    }

    public static function recursiveRemoveDirectory($dir) {

        if(is_dir($dir)) {

            $objects = scandir($dir);
            foreach ($objects as $object) {

                if ($object != "." && $object != "..") {
                    if(filetype($dir . "/" . $object) == "dir") {
                        self::recursiveRemoveDirectory($dir . '/' . $object);
                    } else {
                        unlink($dir . '/' . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    public function siteOffline()
    {
        global $pagenow;

        if(is_admin()){
            return;
        }

        $site_is_offline = get_option('perfectdashboard-site-offline');
        if (empty($site_is_offline)){
            if (defined('SITE_OFFLINE') && SITE_OFFLINE) {
                $site_is_offline = true;
            } else {
                return;
            }
        }

        if (!current_user_can('edit_posts') && !in_array($pagenow, array( 'wp-login.php', 'wp-register.php'))) {
            $protocol = "HTTP/1.0";
            if ("HTTP/1.1" == $_SERVER["SERVER_PROTOCOL"]) {
                $protocol = "HTTP/1.1";
            }
            header("$protocol 503 Service Unavailable", true, 503);
            header("Retry-After: 3600");
            echo '<html>
<head>
    <title>Site Is Offline</title>
    <style type="text/css">
        body
        {
            background: #f1f1f1;
            color: #444;
            font-family: "Open Sans",sans-serif;
            font-size: 14px;
        }
        #content {
            width: 330px;
            padding: 8% 0 0;
            margin: auto;
        }
        #wrapper {
            padding: 20px 10px 25px;
            border-left: 4px solid #00a0d2;
            background-color: #fff;
            -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
        }
    </style>
</head>
<body>
        <div id="content">
            <div id="wrapper">
                <h2 style="text-align: center">Site is offline for maintenance</h2>
                <p style="text-align: center">Please try back soon.</p>
            </div>
        </div>
</body>
</html>';
            exit();
        }
    }

    public static function deleteExternalFiles()
    {
        require_once ABSPATH.'wp-admin/includes/file.php';

        // Remove external files.
        $dir_external_files = ABSPATH.'external_files';

        if (function_exists('WP_Filesystem') AND WP_Filesystem()) {
            global $wp_filesystem;

            if ($wp_filesystem->is_dir($dir_external_files)) {
                // Remove README.txt file.
                $file_readme = $dir_external_files.'/README.txt';
                if ($wp_filesystem->is_file($file_readme)) {
                    $wp_filesystem->delete($file_readme);
                }

                // Remove solo directory.
                $dir_solo = $dir_external_files.'/solo';
                if ($wp_filesystem->is_dir($dir_solo)) {
                    $wp_filesystem->delete($dir_solo, true);
                }

                // If there aren't any files left in external_files, then remove also the folder.
                $files_rest = $wp_filesystem->dirlist($dir_external_files);
                if (empty($files_rest)) {
                    $wp_filesystem->delete($dir_external_files, true);
                }
            }
        } else {
            if (is_dir($dir_external_files)) {
                // Remove README.txt file.
                $file_readme = $dir_external_files.'/README.txt';
                if (is_file($file_readme)) {
                    unlink($file_readme);
                }

                // Remove solo directory.
                $dir_solo = $dir_external_files.'/solo';
                if (is_dir($dir_solo)) {
                    self::recursiveRemoveDirectory($dir_solo);
                }

                // If there aren't any files left in external_files, then remove also the folder.
                $objects = scandir($dir_external_files);
                foreach ($objects as $object) {

                    if ($object != "." && $object != "..") {
                        $files_rest = true;
                        break;
                    }
                }
                
                if (empty($files_rest)) {
                    self::recursiveRemoveDirectory($dir_external_files);
                }
            }
        }
    }
}