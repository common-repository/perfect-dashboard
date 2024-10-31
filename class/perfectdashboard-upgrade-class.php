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

class PerfectDashboardUpgrade {

    private $type = 'plugin';

    private $destination = WP_PLUGIN_DIR;

    public function __construct($type = null) {

        if($type != null) {

            $this->type = $type;

            if($type == 'plugin')
                $this->destination = WP_PLUGIN_DIR;
            elseif($type == 'theme')
                $this->destination = get_theme_root();

        }

    }

    public function downloadPackage($slug, $package = null) {

        if($this->type == 'wordpress') {
            $current = get_site_transient( 'update_core' );

            if($current->updates['response'] == 'lastest')
                return (object)array('success' => 0, 'message' => 'extension_is_up_to_date');
        }

        if($package === null) {

            if($this->type == 'plugin') {
                $updates = get_site_transient('update_plugins');
                $plugin_update = $updates->response[$slug];
                $package = $plugin_update->package;
            } elseif($this->type == 'theme') {
                $updates = get_site_transient('update_themes');
                $theme_update = $updates->response[$slug];
                $package = $theme_update['package'];
            }

        } else {

            // There were problems with presigned URLs and urlIsFile method, so don't use it.
            /*if(!$this->urlIsFile($package)) {
                return (object)array('success' => 0, 'message' => 'wrong package url');
            }*/
        }

        //Connect to the Filesystem first.
        $res = $this->fs_connect( array(WP_CONTENT_DIR, $destination) );

        if (!$res || is_wp_error($res) ) //Mainly for non-connected filesystem.
            return (object)array('success' => 0, 'message' => 'can not connect to filesystem');

        if (empty($package))
            return (object)array('success' => 0, 'message' => 'invalid_package');

        //Download the package
        $download_file = download_url($package);

        if (is_wp_error($download_file))
            return (object)array('success' => 0, 'message' => 'download_error');

        $delete_package = ($download_file != $package); // Do not delete a "local" file

        return (object)array('success' => 1, 'download' => $download_file, 'delete_package' => (int)$delete_package);

    }

    public function unpackPackage($return) {

        //Connect to the Filesystem first.
        $res = $this->fs_connect( array(WP_CONTENT_DIR, $destination) );

        if (!$res || is_wp_error($res) ) //Mainly for non-connected filesystem.
            return (object)array('success' => 0, 'message' => 'can not connect to filesystem');

        global $wp_filesystem;

        $package = $return->download;
        $delete_package = (bool)$return->delete_package;

        $upgrade_folder = $wp_filesystem->wp_content_dir() . 'upgrade/';

        //Clean up contents of upgrade directory beforehand.
        $upgrade_files = $wp_filesystem->dirlist($upgrade_folder);
        if ( !empty($upgrade_files) ) {
            foreach ( $upgrade_files as $file )
                $wp_filesystem->delete($upgrade_folder . $file['name'], true);
        }

        //We need a working directory
        $working_dir = $upgrade_folder . basename($package, '.zip');

        // Clean up working directory
        if ( $wp_filesystem->is_dir($working_dir) )
            $wp_filesystem->delete($working_dir, true);

        // Unzip package to working directory
        $result = unzip_file($package, $working_dir);

        // Once extracted, delete the package if required.
        if ( $delete_package )
            unlink($package);

        if ( is_wp_error($result) ) {
            $wp_filesystem->delete($working_dir, true);
            return (object)array('success' => 0, 'message' => 'no_valid_plugin');
        }

        if ( is_wp_error($working_dir) ) {
            return (object)array('success' => 0, 'message' => 'unpack_error');
        }

        return (object)array('success' => 1, 'working_dir' => $working_dir);

    }

    public function installPackage($slug, $return) {

        //Connect to the Filesystem first.
        $res = $this->fs_connect( array(WP_CONTENT_DIR, $destination) );

        if (!$res || is_wp_error($res) ) //Mainly for non-connected filesystem.
            return (object)array('success' => 0, 'message' => 'can not connect to filesystem');

        global $wp_filesystem;

        $source = $return->working_dir;
        $destination = $this->destination;
        $clear_destination = false;
        $clear_working = true;
        $hook_extra = array();

        @set_time_limit( 300 );

        if ( empty($source) || empty($destination) )
            return (object)array('success' => 0, 'message' => 'install_error');

        add_filter('upgrader_pre_install', array(&$this, 'deactivatePluginBeforeUpgrade'), 10, 2);
        add_filter('upgrader_clear_destination', array(&$this, 'deleteOldPlugin'), 10, 4);

        //Retain the Original source and destinations
        $remote_source = $source;
        $local_destination = $destination;

        $source_files = array_keys( $wp_filesystem->dirlist($remote_source) );
        $remote_destination = $wp_filesystem->find_folder($local_destination);

        //Locate which directory to copy to the new folder, This is based on the actual folder holding the files.
        if ( 1 == count($source_files) && $wp_filesystem->is_dir( trailingslashit($source) . $source_files[0] . '/') ) //Only one folder? Then we want its contents.
            $source = trailingslashit($source) . trailingslashit($source_files[0]);
        elseif ( count($source_files) == 0 )
            return (object)array('success' => 0, 'message' => 'install_error');
        else //Its only a single file, The upgrader will use the foldername of this file as the destination folder. foldername is based on zip filename.
            $source = trailingslashit($source);

        //Has the source location changed? If so, we need a new source_files list.
        if ( $source !== $remote_source )
            $source_files = array_keys( $wp_filesystem->dirlist($source) );

        //Protection against deleting files in any important base directories.
        if ( in_array( $destination, array(ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR, WP_CONTENT_DIR . '/themes') ) ) {
            $remote_destination = trailingslashit($remote_destination) . trailingslashit(basename($source));
            $destination = trailingslashit($destination) . trailingslashit(basename($source));
        }

        if ( $clear_destination ) {
            //We're going to clear the destination if there's something there
            $removed = true;
            if ( $wp_filesystem->exists($remote_destination) )
                    $removed = $wp_filesystem->delete($remote_destination, true);
            $removed = apply_filters('upgrader_clear_destination', $removed, $local_destination, $remote_destination, $hook_extra);

            if ( is_wp_error($removed) || !$removed )
                return (object)array('success' => 0, 'message' => 'install_error');

        }

        //Create destination if needed
        if ( !$wp_filesystem->exists($remote_destination) )
            if ( !$wp_filesystem->mkdir($remote_destination, FS_CHMOD_DIR) )
                return (object)array('success' => 0, 'message' => 'could_not_create_dir');

        // Copy new version of item into place.
        $result = copy_dir($source, $remote_destination);
        if ( is_wp_error($result) ) {
            if ( $clear_working )
                $wp_filesystem->delete($remote_source, true);

            if($this->type == 'plugin')
                $this->refreshPluginsInfo($slug);
            elseif($this->type == 'theme')
                $this->refreshThemesInfo($slug);
            return (object)array('success' => 1);
        }

        //Clear the Working folder?
        if ( $clear_working )
            $wp_filesystem->delete($remote_source, true);

        $destination_name = basename( str_replace($local_destination, '', $destination) );
        if ( '.' == $destination_name )
            $destination_name = '';

        $result = compact('local_source', 'source', 'source_name', 'source_files', 'destination', 'destination_name', 'local_destination', 'remote_destination', 'clear_destination', 'delete_source_dir');

        if ( is_wp_error($result) ) {
            return (object)array('success' => 0, 'message' => 'proces failed');
        }

        if($this->type == 'plugin')
            $this->refreshPluginsInfo($slug);
        elseif($this->type == 'theme')
            $this->refreshThemesInfo($slug);

        return (object)array('success' => 1);

    }

    private function refreshPluginsInfo($slug) {
        $current = get_site_transient( 'update_plugins' );
        $plugins = get_plugins();
        $changed = false;

        foreach($plugins as $pslug => $plugin) {
            if($pslug == $slug) {

                if(isset($current->checked[$slug])) {
                    if(strval($current->checked[$slug]) != strval($plugin['Version'])) {
                        $current->last_checked = time();
                        $current->checked[$slug] = $plugin['Version'];
                        unset($current->response[$slug]);
                        $changed = true;
                    }
                }
            }
        }

        if($changed)
            set_site_transient( 'update_plugins', $current );
    }

    private function refreshWordpressInfo($version) {

        global $wp_object_cache;

        $core_updates = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/' );
        $core_updates = json_decode($core_updates['body']);

        $current = new stdClass();
        $current->updates = array();

        foreach($core_updates->offers as $offer) {
            if(version_compare($offer->version, $version, '>=')) {
                $current->updates[] = $offer;
            }
        }

        if(strval($current->updates[0]->version) == strval($version)) {
            $current->updates[0]->response = 'lastest';
        }

        $current->last_checked = time();
        $current->version_checked = $version;
        $current->translations = $core_updates->translations;

        wp_cache_flush();
        set_site_transient( 'update_core', $current );
    }

    private function refreshThemesInfo($slug) {
        $current = get_site_transient( 'update_themes' );
        $themes = get_themes();
        $changed = false;

        foreach($themes as $theme) {
            if($theme->get('TextDomain') == $slug) {

                if(isset($current->checked[$slug])) {
                    if(strval($current->checked[$slug]) != strval($theme->get('Version'))) {
                        $current->last_checked = time();
                        $current->checked[$slug] = $theme->get('Version');
                        unset($current->response[$slug]);
                        $changed = true;
                    }
                }
            }
        }

        if($changed)
            set_site_transient( 'update_themes', $current );
    }

    private function deactivatePluginBeforeUpgrade($return, $plugin) {

        if ( is_wp_error($return) ) //Bypass.
                return $return;

        $plugin = isset($plugin['plugin']) ? $plugin['plugin'] : '';
        if ( empty($plugin) )
                return new WP_Error('bad_request', $this->strings['bad_request']);

        if ( is_plugin_active($plugin) ) {
                //Deactivate the plugin silently, Prevent deactivation hooks from running.
                deactivate_plugins($plugin, true);
        }
    }

    //Hooked to upgrade_clear_destination
    function deleteOldPlugin($removed, $local_destination, $remote_destination, $plugin) {
        global $wp_filesystem;

        if ( is_wp_error($removed) )
                return $removed; //Pass errors through.

        $plugin = isset($plugin['plugin']) ? $plugin['plugin'] : '';
        if ( empty($plugin) )
                return new WP_Error('bad_request', $this->strings['bad_request']);

        $plugins_dir = $wp_filesystem->wp_plugins_dir();
        $this_plugin_dir = trailingslashit( dirname($plugins_dir . $plugin) );

        if ( ! $wp_filesystem->exists($this_plugin_dir) ) //If its already vanished.
                return $removed;

        // If plugin is in its own directory, recursively delete the directory.
        if ( strpos($plugin, '/') && $this_plugin_dir != $plugins_dir ) //base check on if plugin includes directory separator AND that its not the root plugin folder
                $deleted = $wp_filesystem->delete($this_plugin_dir, true);
        else
                $deleted = $wp_filesystem->delete($plugins_dir . $plugin);

        if ( ! $deleted )
                return new WP_Error('remove_old_failed', $this->strings['remove_old_failed']);

        return true;
    }

    public function fs_connect( $directories = array() ) {
        global $wp_filesystem;

        if ( false === ($credentials = $this->request_filesystem_credentials()) )
            return false;

        if ( ! WP_Filesystem($credentials) ) {
            $error = true;
            if ( is_object($wp_filesystem) && $wp_filesystem->errors->get_error_code() )
                    $error = $wp_filesystem->errors;
            $this->skin->request_filesystem_credentials($error); //Failed to connect, Error and request again
            return false;
        }

        if ( ! is_object($wp_filesystem) )
            return new WP_Error('fs_unavailable', $this->strings['fs_unavailable'] );

        if ( is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code() )
            return new WP_Error('fs_error', $this->strings['fs_error'], $wp_filesystem->errors);

        foreach ( (array)$directories as $dir ) {
            switch ( $dir ) {
                case ABSPATH:
                    if ( ! $wp_filesystem->abspath() )
                            return new WP_Error('fs_no_root_dir', $this->strings['fs_no_root_dir']);
                    break;
                case WP_CONTENT_DIR:
                    if ( ! $wp_filesystem->wp_content_dir() )
                            return new WP_Error('fs_no_content_dir', $this->strings['fs_no_content_dir']);
                    break;
                case WP_PLUGIN_DIR:
                    if ( ! $wp_filesystem->wp_plugins_dir() )
                            return new WP_Error('fs_no_plugins_dir', $this->strings['fs_no_plugins_dir']);
                    break;
                case WP_CONTENT_DIR . '/themes':
                    if ( ! $wp_filesystem->find_folder(WP_CONTENT_DIR . '/themes') )
                            return new WP_Error('fs_no_themes_dir', $this->strings['fs_no_themes_dir']);
                    break;
                default:
                    if ( ! $wp_filesystem->find_folder($dir) )
                            return new WP_Error( 'fs_no_folder', sprintf( $this->strings['fs_no_folder'], esc_html( basename( $dir ) ) ) );
                    break;
            }
        }
        return true;
    }


    public function request_filesystem_credentials($error = false) {
        $url = $this->options['url'];
        $context = $this->options['context'];
        if ( !empty($this->options['nonce']) )
                $url = wp_nonce_url($url, $this->options['nonce']);
        return request_filesystem_credentials($url, '', $error, $context); //Possible to bring inline, Leaving as is for now.
    }

    public function urlIsFile($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if($code == 200) {
            $status = true;
        } else {
            $status = false;
        }
        curl_close($ch);
        return $status;
    }

    function updateWordpress($return) {

        global $wp_filesystem;

        //Connect to the Filesystem first.
        $res = $this->fs_connect( array(WP_CONTENT_DIR, $destination) );

        if (!$res || is_wp_error($res) ) //Mainly for non-connected filesystem.
            return (object)array('success' => 0, 'message' => 'can not connect to filesystem');

        $working_dir = $return->working_dir;
        $wp_dir = trailingslashit($wp_filesystem->abspath());

        // Copy update-core.php from the new version into place.
        if ( !$wp_filesystem->copy($working_dir . '/wordpress/wp-admin/includes/update-core.php', $wp_dir . 'wp-admin/includes/update-core.php', true) ) {
            $wp_filesystem->delete($working_dir, true);
            return (object)array('success' => 0, 'message' => 'could not copy files');
        }

        $wp_filesystem->chmod($wp_dir . 'wp-admin/includes/update-core.php', FS_CHMOD_FILE);

        require(ABSPATH . 'wp-admin/includes/update-core.php');

        if ( ! function_exists( 'update_core' ) )
            return (object)array('success' => 0, 'message' => 'Could not copy files. You may have run out of disk space');

        $version = update_core($working_dir, $wp_dir);

        if($version && !is_wp_error($version)) {
            $this->refreshWordpressInfo($version);
            return (object)array('success' => 1);
        } else {
            return (object)array('success' => 0, 'message' => 'something went wrong');
        }

    }
}