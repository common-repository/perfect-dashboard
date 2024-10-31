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

class PerfectDashboardTest {

    public function __construct() {

    }

    /*
     * Getting list of files and their md5_file checksum
     */
    public function getFilesChecksum($dir, &$results = array()) {

        $files = scandir($dir);

        foreach($files as $key => $value) {

            $path = realpath($dir . '/' . $value);
            $rel_path = str_replace(ABSPATH, '', $path);
     
            if(!is_dir($path)) {

                $results[] = $rel_path . ' ' . md5_file($path);

            } else if(is_dir($path) && $value != "." && $value != "..") {

                $this->getFilesChecksum($path, $results);

            }
        }

        return $results;

    }

}