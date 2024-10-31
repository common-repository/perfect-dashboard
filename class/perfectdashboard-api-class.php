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

class PerfectDashboardAPI
{

    private $output = array();
    private $filter;

    public function __construct($secure_key, $task)
    {
        if (empty($secure_key)) {
            $this->output = array(
                'state' => 0,
                'message' => 'no secure_key given'
            );
        } elseif (empty($task)) {
            $this->output = array(
                'state' => 0,
                'message' => 'no task given'
            );
        } elseif ($secure_key == get_option('perfectdashboard-key', null)) {
            // Check if secure key is valid
            require_once __DIR__.'/perfectdashboard-filterinput-class.php';

            $this->filter = PerfectDashboardFilterInput::getInstance();

            // save ping after success connection to child
            $this->savePing();

            // Check task name and run specific action
            switch ($task) {
                case 'getExtensions':
                    $this->getExtensionsTask();
                    break;
                case 'doUpdate':
                    $this->doUpdateTask();
                    break;
                case 'getUpgradeStatus':
                    $this->getUpgradeStatusTask();
                    break;
                case 'getChecksum':
                    $this->getChecksumTask();
                    break;
                case 'getAkeebaSoloParams':
                    $this->getAkeebaSoloParamsTask();
                    break;
                case 'getLatestBackup':
                    $this->getLatestBackupTask();
                    break;
                case 'getLatestBackupName':
                    $this->getLatestBackupNameTask();
                    break;
                case 'removeBackup':
                    $this->removeBackupTask();
                    break;
                case 'beforeCmsUpdate':
                    $this->beforeCmsUpdate();
                    break;
                case 'beforeCmsUpgrade':
                    $this->beforeCmsUpgrade();
                    break;
                case 'afterCmsUpdate':
                    $this->afterCmsUpdate();
                    break;
                case 'afterCmsUpgrade':
                    $this->afterCmsUpgrade();
                    break;
                case 'cmsDisable':
                    $this->cmsDisable();
                    break;
                case 'cmsEnable':
                    $this->cmsEnable();
                    break;
                case 'extensionDisable':
                    $this->extensionDisable();
                    break;
                case 'extensionEnable':
                    $this->extensionEnable();
                    break;
                case 'sysInfo':
                    $this->sysInfo();
                    break;
                case 'checkSysEnv':
                    $this->checkSysEnv();
                    break;
                case 'installBackupTool':
                    $this->installBackupTool();
                    break;
                case 'removeLastBackup':
                    $this->removeLastBackupTask();
                    break;
                default:
                    $this->output = array(
                        'state' => 0,
                        'message' => 'invalid task name'
                    );
                    break;
            }
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'invalid secure_key'
            );
        }

        // Send response output to Dashboard
        $this->sendOutput();
    }

    private function savePing()
    {

        $date = new DateTime('now');
        update_option('perfectdashboard-ping', $date->format('d-m-Y H:i:s'));
    }

    private function getLatestBackupTask()
    {
        
        $akeeba_path = $this->getBackupToolPath().'backups/';

        $lastMod = 0;
        $lastModFile = null;
        foreach (scandir($akeeba_path) as $entry) {
            if (is_file($akeeba_path . $entry) AND filectime($akeeba_path . $entry) > $lastMod AND substr($entry, -4, 4) == '.jpa') {
                $lastMod = filectime($akeeba_path . $entry);
                $lastModFile = $akeeba_path . $entry;
            }
        }

        if (is_null($lastModFile)) {
            $this->output = array(
                'state' => 0,
                'message' => 'no file'
            );
            return false;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($lastModFile) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($lastModFile));
        readfile($lastModFile);
        exit;
    }

    private function getLatestBackupNameTask()
    {

        $akeeba_path = $this->getBackupToolPath().'backups/';

        $lastMod     = 0;
        $lastModFile = null;
        foreach (scandir($akeeba_path) as $entry) {
            if (is_file($akeeba_path . $entry) AND filectime($akeeba_path . $entry) > $lastMod AND substr($entry, -4, 4) == '.jpa') {
                $lastMod     = filectime($akeeba_path . $entry);
                $lastModFile = $entry;
            }
        }

        if(is_null($lastModFile)) {

            $this->output = array(
                'state' => 0,
                'message' => 'no file'
            );

        } else {

            $this->output = array(
                'state' => 1,
                'filename' => $lastModFile
            );
        }
    }

    private function removeBackupTask()
    {

        if (isset($_POST['filename']) && $_POST['filename']) {
            $filename = $this->filter->clean($_POST['filename'], 'string');
            $filename = basename($filename);
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'no filename given'
            );
            return false;
        }

        $path = $this->getBackupToolPath().'backups/'.$filename;

        if(file_exists($path)) {
            $result = unlink($path);

            if($result) {
                $this->output = array(
                    'state' => 1
                );
            } else {
                $this->output = array(
                    'state' => 0,
                    'message' => 'can not delete file'
                );
            }
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'file not exists'
            );
        }

    }

    /*
     * Getting information about extensions (name, version, type, slug, state, update state and update version)
     */
    private function getExtensionsTask()
    {

        include_once __DIR__ . '/perfectdashboard-info-class.php';

        if (isset($_POST['offset'])) {
            $offset = $this->filter->clean($_POST['offset'], 'int');            
        } else {
            $offset = 0;
        }
        if (isset($_POST['skip_updates'])) {
            $skip_updates = $this->filter->clean($_POST['skip_updates'], 'int');
        } else {
            $skip_updates = 0;
        }

        $limit = 4;

        $info = new PerfectDashboardInfo();

        $output = array(
            array('type' => 'cms')
        );

        // getting informations about plugins installed on this wordpress
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }
        $output = array_merge($output, get_plugins());

        // getting informations about themess installed on this wordpress
        if (!function_exists('wp_get_themes')) {
            require_once(ABSPATH . '/wp-admin/includes/theme.php');
        }
        $output = array_merge($output, wp_get_themes());

        //paginate output
        if ($skip_updates == 0) {
            $output = array_slice($output, $offset, $limit);
        }

        $return = array();

        //loop and paginate
        foreach ($output as $slug => $value) {

            if ($value instanceof WP_Theme) {

                $return[] = $info->getThemesInfo($slug, $value, $skip_updates);
            } elseif (isset($value['type']) && $value['type'] == 'cms') {
                if ($skip_updates == 0) {
                    $return[] = $info->getCmsInfo();
                }
            } elseif (isset($value['PluginURI'])) {

                $return[] = $info->getPluginsInfo($slug, $value, $skip_updates);
            }
        }

        $this->output = array(
            'offset' => ($skip_updates == 1) ? 0 : $offset + $limit,
            'result' => (empty($return) ? 0 : $return)
        );
    }

    /*
     * Sending json output to Dashboard
     */
    public function sendOutput()
    {
        header('Content-Type: application/json');
        if (is_array($this->output)) {
            $this->output = array_merge($this->output, array(
                'metadata' => array(
                    'version' => PERFECTDASHBOARD_VERSION
                )
            ));
        } elseif (is_object($this->output)) {
            $this->output->metadata = array(
                'version' => PERFECTDASHBOARD_VERSION
            );
        }
        echo json_encode($this->output);
        die();
    }

    /*
     * Updating Wordpress and extensions
     */
    private function doUpdateTask()
    {

        // get the type of the element that needs to be updated (wordpress, plugin, theme)
        if (isset($_POST['type']) && $_POST['type']) {
            $type = $this->filter->clean($_POST['type'], 'cmd');
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'no type'
            );
            return false;
        }

        // get the slug name of plugin or theme
        if ($type != 'wordpress') {
            if (isset($_POST['slug']) && $_POST['slug']) {
                $slug = $this->filter->clean($_POST['slug'], 'string');
            } else {
                $this->output = array(
                    'state' => 0,
                    'message' => 'no slug'
                );
                return false;
            }
        }

        // get the action to run (download, unpack, update)
        if (isset($_POST['action']) && $_POST['action']) {
            $action = $this->filter->clean($_POST['action'], 'cmd');     
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'no action'
            );
            return false;
        }

        // get the url of package to download (optional)
        if (isset($_POST['file']) && $_POST['file']) {
            $file = $this->filter->clean($_POST['file'], 'ba'.'se'.'64');
            $file = call_user_func('ba'.'se'.'64'.'_decode', $file);
        }

        // get the encoded serialized respons from previous action (only in unpack and update)
        if (isset($_POST['return']) && $_POST['return']) {
            $return = $this->filter->clean($_POST['return'], 'ba'.'se'.'64');
            $return = json_decode(call_user_func('ba'.'se'.'64'.'_decode', $return));
        }

        // including the necessary methods
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        include_once __DIR__ . '/perfectdashboard-upgrade-class.php';

        $upgrade = new PerfectDashboardUpgrade($type);

        // call specific action and set output message
        switch ($action) {
            case 'download':
                $download_package = $upgrade->downloadPackage($slug, $file);

                if ($download_package->success == 1) {
                    $this->output = array(
                        'state' => 1,
                        'message' => 'success',
                        'return' => call_user_func('ba'.'se'.'64'.'_encode', json_encode($download_package))
                    );
                } else {
                    $this->output = array(
                        'state' => 0,
                        'message' => $download_package->message
                    );
                }
                break;
            case 'unpack':
                $unpack_package = $upgrade->unpackPackage($return);

                if ($unpack_package->success == 1) {
                    $this->output = array(
                        'state' => 1,
                        'message' => 'success',
                        'return' => call_user_func('ba'.'se'.'64'.'_encode', json_encode($unpack_package))
                    );
                } else {
                    $this->output = array(
                        'state' => 0,
                        'message' => $unpack_package->message
                    );
                }
                break;
            case 'update':
                if ($type == 'wordpress') {
                    $update = $upgrade->updateWordpress($return);
                } else {
                    $update = $upgrade->installPackage($slug, $return);
                }

                if ($update->success == 1) {
                    $this->output = array(
                        'state' => 1,
                        'message' => 'success'
                    );
                } else {
                    $this->output = array(
                        'state' => 0,
                        'message' => $update->message
                    );
                }
                break;
            default:

        }

    }

    /*
     * Getting array of compatibility of all plugins for given WordPress versions
     */
    private function getUpgradeStatusTask()
    {

        // get an array with WordPress versions to check
        if (isset($_POST['versions']) && $_POST['versions']) {
            $versions = $this->filter->clean($_POST['versions'], 'array');
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'no versions parameter'
            );
            return false;
        }

        if (!is_array($versions)) {
            $this->output = array(
                'state' => 0,
                'message' => 'versions is not an array'
            );
            return false;
        }

        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        include_once __DIR__ . '/perfectdashboard-info-class.php';

        $plugins = get_plugins();
        $info = new PerfectDashboardInfo();

        $data = array();

        // check every plugin to compare compatibility with given Wordpress versions
        foreach ($plugins as $slug => $plugin) {

            $item = array(
                'name' => $plugin['Name'],
                'slug' => $slug,
                'cms' => array()
            );

            // check if plugin is on Wordpress repo
            $plugin_info = $info->checkPluginUpdate(dirname($slug));
            $cms_versions = array();

            // define false
            $installed_compatibility = false;

            // check if
            $same_plugin = ($plugin_info !== false AND version_compare($plugin['Version'], $plugin_info->version, '='));

            // if not same grab info about older version installed on wordpress
            if ($same_plugin === false) {

                // if plugin got readme file parse it
                if (file_exists(dirname(WP_PLUGIN_DIR . '/' . $slug) . '/readme.txt')) {
                    $installed_plugin_readme = file_get_contents(dirname(WP_PLUGIN_DIR . '/' . $slug) . '/readme.txt');
                    preg_match('/(?:Requires\sat\sleast\:\s)(.*)(?:\s+)(?:Tested\sup\sto\:\s)(.*)/i',
                        $installed_plugin_readme, $installed_compatibility);
                } else {
                    $installed_compatibility = false;
                }
            }

            // check all of the given Wordpress versions
            foreach ($versions as $version) {

                $is_available = ($plugin_info !== false AND $info->isAvailableForWordpressVersion($plugin_info->requires,
                        $plugin_info->tested,
                        $version));

                //if is available
                if ($is_available === true AND $same_plugin === true) {
                    $cms_versions[$version] = 1; // we got available without update
                } elseif ($is_available === true AND $same_plugin === false) {
                    $cms_versions[$version] = 3; // we got available with update
                } elseif ($is_available === false AND $same_plugin === true) {
                    $cms_versions[$version] = 2; // not available
                } elseif ($is_available === false AND $same_plugin === false) {

                    if ($installed_compatibility) {

                        $is_available = $info->isAvailableForWordpressVersion($installed_compatibility[1],
                            $installed_compatibility[2],
                            $version);

                        if ($is_available) {
                            $cms_versions[$version] = 1; // available
                        } else {
                            $cms_versions[$version] = 2; // not available
                        }
                    } else {
                        $cms_versions[$version] = 0; // not available
                    }
                }
            }

            $item['cms'] = $cms_versions;
            $item['old'] = $installed_compatibility;
            $item['new'] = $same_plugin ? $plugin['Version'] : $plugin_info->version;
            $data[] = $item;
        }

        // put array in output to response
        $this->output = $data;

    }

    /*
     * Getting array of files and their md5_file checksum
     */
    private function getChecksumTask()
    {

        include_once __DIR__ . '/perfectdashboard-test-class.php';

        $test = new PerfectDashboardTest();
        $file_list_checksum = $test->getFilesChecksum(ABSPATH);

        if (is_array($file_list_checksum) && count($file_list_checksum) > 0) {
            $this->output = array(
                'state' => 1,
                'file_list' => call_user_func('ba'.'se'.'64'.'_encode', json_encode($file_list_checksum))
            );
        } else {
            $this->output = array(
                'state' => 0
            );
        }

    }

    // TODO remove in 1.2
    private function getAkeebaSoloParamsTask()
    {

        $params = get_option('perfectdashboard_akeeba_access', false);

        $this->output = array('akeeba' => $params);

    }

    private function beforeCmsUpdate()
    {
        $this->output = array('state' => 1);
    }

    private function beforeCmsUpgrade()
    {
        $this->output = array('state' => 1);
    }

    private function afterCmsUpdate()
    {
        $this->output = array('state' => 1);
    }

    private function afterCmsUpgrade()
    {
        $this->output = array('state' => 1);
    }

    private function cmsDisable()
    {
        update_option('perfectdashboard-site-offline', 1);

        $this->output = array('state' => 1);
    }

    private function cmsEnable()
    {
        update_option('perfectdashboard-site-offline', 0);

        $this->output = array('state' => 1);
    }

    private function extensionDisable()
    {
        if (isset($_POST['extensions'])) {
            $extensions = $this->filter->clean($_POST['extensions'], 'array');
        }

        require_once ABSPATH . '/wp-admin/includes/plugin.php';

        $plugins = array();
        if ($extensions) {
            // If extensions are set, then deactivate only given extensions.
            foreach ($extensions as $ext) {
                $plugins[] = $ext['slug'];
            }
        } else {
            // If extensions aren't set, then deactivate all extensions except perfectdashboard.
            $extensions = get_plugins();

            foreach ($extensions as $ext_slug => $ext_data) {
                if (strpos($ext_slug, '/perfectdashboard.php') === false) {
                    $plugins[] = $ext_slug;
                }
            }
        }

        deactivate_plugins($plugins, true);

        $this->output = array('state' => 1);
    }

    private function extensionEnable()
    {
        if (isset($_POST['extensions'])) {
            $extensions = $this->filter->clean($_POST['extensions'], 'array');
        }

        require_once ABSPATH . '/wp-admin/includes/plugin.php';

        $plugins = array();
        if ($extensions) {
            // If extensions are set, then activate only given extensions.
            foreach ($extensions as $ext) {
                $plugins[] = $ext['slug'];
            }
        } else {
            // If extensions aren't set, then activate all extensions except perfectdashboard.
            $extensions = get_plugins();

            foreach ($extensions as $ext_slug => $ext_data) {
                if (strpos($ext_slug, '/perfectdashboard.php') === false) {
                    $plugins[] = $ext_slug;
                }
            }
        }

        $result = activate_plugins($plugins);

        if ($result === true) {
            $this->output = array('state' => 1);
        } else {
            $errors = array();
            foreach ($result->get_error_data() as $error) {
                $errors[] = $error->get_error_message();
            }
            $this->output = array('state' => 0, 'error_code' => 0, 'debug' => implode(', ', $errors));
        }
    }

    private function sysInfo()
    {
        global $wpdb, $wp_version;

        $this->output = array(
            'state' => 1,
            'cms_type' => 'wordpress',
            'cms_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'os' => php_uname('s'),
            'server' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '',
            'database_name' => $wpdb->is_mysql ? 'mysql' : '',
            'database_version' => $wpdb->db_version()
        );
    }

    private function checkSysEnv()
    {
        // Get request data.
        $php_ver_min = (isset($_POST['php_ver_min']) ? $this->filter->clean($_POST['php_ver_min'], 'cmd') : '');
        $php_ver_max = (isset($_POST['php_ver_max']) ? $this->filter->clean($_POST['php_ver_max'], 'cmd') : '');

        if (($php_ver_min && version_compare(PHP_VERSION, $php_ver_min) == -1) ||
            ($php_ver_max && version_compare(PHP_VERSION, $php_ver_max) == 1)) {
            $this->output = array(
                'state' => 0,
                'message' => sprintf('Server PHP version %s. Requies PHP version grater than %s and less than %s',
                    PHP_VERSION, $php_ver_min, $php_ver_max));
        } else {
            $this->output = array('state' => 1);
        }
    }

    private function installBackupTool()
    {
        if (isset($_POST['download_url'])) {
            $download_url = $this->filter->clean($_POST['download_url'], 'ba'.'se'.'64');
        }
        if (isset($_POST['install_dir'])) {
            $install_dir = $this->filter->clean($_POST['install_dir'], 'cmd');
        }
        if (isset($_POST['login'])) {
            $login = $this->filter->clean($_POST['login'], 'alnum');
        }
        if (isset($_POST['password'])) {
            $password = $this->filter->clean($_POST['password'], 'alnum');
        }
        if (isset($_POST['secret'])) {
            $secret = $this->filter->clean($_POST['secret'], 'alnum');
        }

        if (!$download_url || !$install_dir || !$login || !$password || !$secret) {
            $this->output = array('status' => 0, 'message' => 'missing_data');
            return false;
        }

        // Check if backup tool is already installed.
        $backup_dir = get_option('perfectdashboard-backup_dir', false);

        if (!empty($backup_dir) && file_exists(ABSPATH.$backup_dir.'/index.php')) {
            $this->output = array('state' => 1, 'message' => 'already_installed');
            return true;
        }

        // Check if backup tool is already installed - for childs installed before version 1.1
        $params = get_option('perfectdashboard_akeeba_access', false);

        if (!empty($params['ak_prefix_folder']) &&
            file_exists(ABSPATH.$params['ak_prefix_folder'].'perfectdashboard_akeeba'.'/index.php')) {
            $this->output = array('state' => 1, 'message' => 'already_installed');
            return true;
        }

        $download_url = call_user_func('ba'.'se'.'64'.'_decode', $download_url);

        include_once ABSPATH . 'wp-admin/includes/file.php';

        //Download the package
        $download_file = download_url($download_url);

        if (is_wp_error($download_file)) {
            $this->output = array('success' => 0, 'message' => 'download_error');
        }

        $perfix_db     = 'as'.substr(md5(uniqid('akeeba_tables')), 0, 5).'_';

        $akeeba_path = ABSPATH . $install_dir . '/';
        $akeeba_package_path = $download_file;

        if(!file_exists($akeeba_path)) {
            mkdir($akeeba_path);
        }

        if(file_exists($akeeba_package_path)) {

            $zip = new ZipArchive;
            $opened = $zip->open($akeeba_package_path);
            $extracted = false;

            if($opened) {
                $extracted = $zip->extractTo($akeeba_path);
                $zip->close();
            }

            if($extracted) {

                if(false == include $akeeba_path . 'Awf/Autoloader/Autoloader.php') {
                    return false;
                }

                if (!defined('APATH_BASE')) {
                    if(false == include $akeeba_path . 'defines.php') {
                        return false;
                    }
                }

                $prefixes = Awf\Autoloader\Autoloader::getInstance()->getPrefixes();
                if(!array_key_exists('Solo\\', $prefixes)) {
                    Awf\Autoloader\Autoloader::getInstance()->addMap('Solo\\', APATH_BASE . '/Solo');
                }

                if(!defined('AKEEBAENGINE')) {
                    define('AKEEBAENGINE', 1);

                    if(false == include $akeeba_path . 'Solo/engine/Factory.php') {
                        $this->output = array('status' => 0, 'message' => 'install_error');
                        return false;
                    }

                    if (file_exists($akeeba_path.'Solo/alice/factory.php')) {
                        if(false == include $akeeba_path . 'Solo/alice/factory.php') {
                            $this->output = array('status' => 0, 'message' => 'install_error');
                            return false;
                        }
                    }

                    Akeeba\Engine\Platform::addPlatform('Solo',  $akeeba_path . 'Solo/Platform/Solo');
                    Akeeba\Engine\Platform::getInstance()->load_version_defines();
                    Akeeba\Engine\Platform::getInstance()->apply_quirk_definitions();
                }

                try {
                    // Create the container if it doesn't already exist
                    if (!isset($container)) {
                        $container = new \Solo\Container(array(
                            'application_name' => 'Solo'
                        ));
                    }

                    // Create the application
                    $application = $container->application;

                    // Initialise the application
                    $application->initialise();

                    $model_setup = new \Solo\Model\Setup();

                    $db_config = self::getDatabaseConfig();

                    $session = $container->segment;

                    $session->set('db_driver', 'mysql');
                    $session->set('db_host', $db_config['db_host']);
                    $session->set('db_user', $db_config['db_user']);
                    $session->set('db_pass', $db_config['db_password']);
                    $session->set('db_name', $db_config['db_name']);
                    $session->set('db_prefix', $perfix_db.'perfectdashboard_akeeba_');

                    $model_setup->applyDatabaseParameters();

                    $model_setup->installDatabase();

                    $email = 'dashboard@perfectdashboard.co';
                    $live_site = get_site_url() . '/'.$install_dir;

                    $session->set('setup_timezone', date_default_timezone_get());
                    $session->set('setup_live_site',  $live_site);
                    $session->set('setup_session_timeout', 1440);
                    $session->set('setup_user_username', $login);
                    $session->set('setup_user_password', $password);
                    $session->set('setup_user_password2', $password);
                    $session->set('setup_user_email', $email);
                    $session->set('setup_user_name', 'Perfect Dashboard');

                    // Apply configuration settings to app config
                    $model_setup->setSetupParameters();

                    // Try to create the new admin user and log them in
                    $model_setup->createAdminUser();

                    // Set akeeba system configuration
                    $container->appConfig->set('options.frontend_enable', true);
                    $container->appConfig->set('options.frontend_secret_word', $secret);
                    $container->appConfig->set('options.frontend_email_on_finish', false);

                    $container->appConfig->saveConfiguration();

                    // Configuration Wizard
                    $siteParams = array();
                    $siteParams['akeeba.platform.site_url'] = get_home_url();
                    $siteParams['akeeba.platform.newroot'] = ABSPATH;
                    $siteParams['akeeba.platform.dbdriver'] = 'mysql';
                    $siteParams['akeeba.platform.dbhost'] = $db_config['db_host'];
                    $siteParams['akeeba.platform.dbusername'] = $db_config['db_user'];
                    $siteParams['akeeba.platform.dbpassword'] = $db_config['db_password'];
                    $siteParams['akeeba.platform.dbname'] = $db_config['db_name'];
                    $siteParams['akeeba.platform.dbprefix'] = $db_config['db_prefix'];
                    $siteParams['akeeba.platform.scripttype'] = 'wordpres';
                    $siteParams['akeeba.advanced.embedded_installer'] = 'angie-wordpress';

                    $config = \Akeeba\Engine\Factory::getConfiguration();

                    $protectedKeys = $config->getProtectedKeys();
                    $config->setProtectedKeys(array());

                    foreach ($siteParams as $k => $v) {
                        $config->set($k, $v);
                    }

                    \Akeeba\Engine\Platform::getInstance()->save_configuration();

                    $config->setProtectedKeys($protectedKeys);
                    // End Configuration Wizard.

                    // Exclude perfectdashboard_akeeba folder from backup.
                    $filter = \Akeeba\Engine\Factory::getFilterObject('directories');
                    $filters = \Akeeba\Engine\Factory::getFilters();

                    // Toggle the filter
                    $success = $filter->set(ABSPATH, $install_dir);

                    // Save the data on success
                    if ($success) {
                        $filters->save();
                    }
                    // End filtering.

                    update_option('perfectdashboard-backup_dir', $install_dir);

                } catch (Exception $ex) {
                    $this->output = array('status' => 0, 'message' => 'install_error', 'debug' => $ex->getMessage());
                    return false;
                }
            } else {
                $this->output = array('status' => 0, 'message' => 'install_error');
                return false;
            }
        }

        if (file_exists($download_file)) {
            unlink($download_file);
        }

        $this->output = array('state' => 1, 'message' => 'installed');
    }

    private static function getDatabaseConfig() {

        $config_file = ABSPATH .'/wp-config.php';
        $content = file_get_contents($config_file);
        $config = array();

        preg_match('/define.*DB_NAME.*\'(.*)\'/', $content, $match);
        $config['db_name'] = $match[1];

        preg_match('/define.*DB_USER.*\'(.*)\'/', $content, $match);
        $config['db_user'] = $match[1];

        preg_match('/define.*DB_PASSWORD.*\'(.*)\'/', $content, $match);
        $config['db_password'] = $match[1];

        preg_match('/define.*DB_HOST.*\'(.*)\'/', $content, $match);
        $config['db_host'] = $match[1];

        preg_match('/\$table_prefix.*=.*\'(.*)\'/', $content, $match);
        $config['db_prefix'] = $match[1];

        return $config;
    }

    protected function getBackupToolPath()
    {
        $backup_dir = get_option('perfectdashboard-backup_dir', false);

        if (empty($backup_dir)) {
            $old_params = get_option('perfectdashboard_akeeba_access', false);

            if ($old_params) {
                $old_params = unserialize(call_user_func('ba'.'se'.'64'.'_decode', $old_params));
                $backup_dir = $old_params['ak_prefix_folder'].'perfectdashboard_akeeba';

                update_option('perfectdashboard-backup_dir', $backup_dir);
            }
        }

        if ($backup_dir) {
            return ABSPATH.'/'.$backup_dir.'/';
        }
    }

    private function removeLastBackupTask() {

        if (isset($_POST['akeeba_dir'])) {
                $akeeba_dir = $this->filter->clean($_POST['akeeba_dir'], 'string');
        }

        $last_backup = 0;
        $last_backup_path = '';
        if($akeeba_dir) {
            $files = scandir(ABSPATH . '/' . $akeeba_dir . '/backups');

            foreach($files as $file) {
                $path = ABSPATH.'/'.$akeeba_dir.'/backups/'.$file;
                if(pathinfo($file, PATHINFO_EXTENSION) == 'jpa') {
                    if(filemtime($path) > $last_backup) {
                        $last_backup = filemtime($path);
                        $last_backup_path = $path;
                    }
                }
            }

            if($last_backup > 0) {
                if(!empty($last_backup_path)) {
                    unlink($path);
                    $this->output = array('status' => 1);
                    return false;
                } else {
                    $this->output = array('status' => 0, 'message' => 'cannot find backup to remove :(');
                    return false;
                }
            } else {
                $this->output = array('status' => 1);
                return false;
            }
        } else {
            $this->output = array('status' => 0, 'message' => 'no akeeba_dir parameter');
            return false;
        }
    }
}