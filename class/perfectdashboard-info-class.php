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

class PerfectDashboardInfo {

    public function __construct() {

    }

    /*
     * Getting information about current WordPress instance (name, type, version, update state and update version)
     */
    public function getCmsInfo($skip_updates = 0) {

        $cms = array(
            'name' => 'Wordpress CMS',
            'type' => 'wordpress',
            'slug' => '',
            'version' => get_bloginfo('version'),
            'state' => 1
        );

        if((int)$skip_updates == 1) {
            return $cms;
        }

        // check if Wordpress detects some update
        $upgrade = get_site_transient( 'update_core' );

        if($upgrade && $upgrade->updates[0]->response != 'lastest') {
            $cms['update_state'] = 2;
            $cms['update_version'] = $upgrade->updates[0]->current;
        } elseif($upgrade && $upgrade->updates[0]->response == 'lastest') {
            $cms['update_state'] = 1;
            $cms['update_version'] = '';
        } else {
            $cms['update_state'] = 0;
            $cms['update_version'] = '';
        }

        return $cms;
    }

    /*
     * Getting information about plugins in this Wordpress (name, type, slug, version, state, update state and update version)
     */
    public function getPluginsInfo($slug_plugin, $array_plugin, $skip_updates = 0) {

        $item = array(
            'name' => $array_plugin['Name'],
            'type' => 'plugin',
            'slug' => $slug_plugin,
            'version' => $array_plugin['Version']
        );

        if((int)$skip_updates == 1) {
            return $item;
        }

        // getting informations about plugins updates from Wordpress repository
        $plugins_outdate = get_site_transient( 'update_plugins' );

        // assign updates to plugins array
        if(isset($plugins_outdate->response[$slug_plugin])){
            $array_plugin['update'] = $plugins_outdate->response[$slug_plugin];
        }

        // get author url
        if(isset($array_plugin['AuthorURI'])) {
            $item['author_url'] = $array_plugin['AuthorURI'];
        } else {
            $item['author_url'] = null;
        }

        // check if plugin is activated
        if(is_plugin_active($slug_plugin)) {
            $item['state'] = 1;
        } else {
            $item['state'] = 0;
        }

        // get info about plugin from repository (ex. requires and tested version of Wordpress)
        $repo_version = $this->checkPluginUpdate(dirname($item['slug']));

        // set the update state
        if (isset($array_plugin['update']) && is_object($array_plugin['update'])) {
            if ($repo_version !== false && $this->isAvailableForWordpressVersion($repo_version->requires, $repo_version->tested)) {
                $item['update_state'] = 2;
                $item['update_version'] = $array_plugin['update']->new_version;
            } else {
                $item['update_state'] = 1;
                $item['update_version'] = '';
            }
        } else {

            if ($repo_version !== false && isset($array_plugin['Version']) && $repo_version->version == $array_plugin['Version']) {
                $item['update_state'] = 1;
                $item['update_version'] = '';
            } else {
                $item['update_state'] = 0;
                $item['update_version'] = '';
            }

        }

        return $item;

    }

    /*
     * Getting information about themes in this Wordpress (name, type, slug, version, state, update state and update version)
     */
    public function getThemesInfo($slug_theme, $object_theme, $skip_updates = 0) {

        // build array with themes data to Dashboard
        $item = array(
            'name' => $object_theme->get('Name'),
            'type' => 'theme',
            'slug' => pathinfo($slug_theme, PATHINFO_FILENAME),
            'version' => $object_theme->get('Version')
        );

        if((int)$skip_updates == 1) {
            return $item;
        }

        // getting informations about themes updates from Wordpress repository
        $themes_outdate = get_site_transient( 'update_themes' );
        if (!$themes_outdate) {
            $themes_outdate = array();
        }

        // assign updates to themes array
        foreach($themes_outdate->response as $slug => $version) {
            if($slug == $slug_theme) {
                $object_theme->update = $version;
                break;
            }
        }

        // check if theme is activated
        $current_theme = wp_get_theme();
        if($current_theme->get('Name') == $item['name']) {
            $item['state'] = 1;
        } else {
            $item['state'] = 0;
        }

        if(isset($object_theme->update)) {
            $item['update_state'] = 2;
            $item['update_version'] = $object_theme->update['new_version'];
        } else {
            // check if theme is in repository and update is possible
            $repo_version = $this->checkThemeUpdate($item['slug']);

            if($repo_version) {
                $item['update_state'] = 1;
                $item['update_version'] = '';
            } else {
                $item['update_state'] = 0;
                $item['update_version'] = '';
            }

        }

        return $item;
    }

    /*
     * Getting informations about plugin from Wordpress repository
     */
    public function checkPluginUpdate($slug) {

        $url = 'http://api.wordpress.org/plugins/info/1.0/' . $slug . '.json';

        $response = wp_remote_get( $url );
        if (is_wp_error($response) || empty($response['body'])) {
            return false;
        }

        $body = json_decode($response['body']);
        if ($body) {
            return $body;
        } else {
            return false;
        }

    }

    /*
     * Getting version of theme from Wordpress repository
     */
    public function checkThemeUpdate($slug) {

        $url = 'http://api.wordpress.org/themes/info/1.0/';

        $args = array(
            'slug' => $slug,
            'fields' => array( 'screenshot_url'=> true )
        );

        $response = wp_remote_post( $url, array('body' => array('action' => 'theme_information', 'request' => serialize((object)$args))) );
        if (is_wp_error($response) || empty($response['body'])) {
            return false;
        }
        
        $body = unserialize($response['body']);
        if ($body) {
            return $body->version;
        } else {
            return false;
        }

    }

    /**
     * Checking if Wordpress is compatible with given versions
     *
     * @param String $required - the minimum required version of Wordpress
     * @param String $tested - the maximum tested version of Wordpress
     * @param String $wp_version - version of Wordpress
     * @return boolean
     */
    public function isAvailableForWordpressVersion($required, $tested, $wp_version = null) {

        if($wp_version === null)
            $wp_version = get_bloginfo('version');

        // compare given versions to current WordPress version
        $is_not_lower = version_compare($required, $wp_version, '<=');
        $is_not_higher = version_compare($tested, $wp_version, '>=');

        if($is_not_lower && $is_not_higher)
            return true;
        else
            return false;

    }

}