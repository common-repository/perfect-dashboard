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
global $user_email;
get_currentuserinfo();
?>

<div class="perfectdashboard-header">
    <h1 class="perfectdashboard-heading">
        <?php _e('Perfect Dashboard extension', 'perfectdashboard'); ?>
    </h1>

    <button class="button button-primary perfectdashboard-settings-button"
            data-close="<?php _e('Close Settings', 'perfectdashboard'); ?>"
            data-open="<?php _e('Settings', 'perfectdashboard'); ?>">
        <?php _e('Settings', 'perfectdashboard'); ?>
    </button>
</div>

<div class="perfectdashboard">

    <?php if (strlen(get_option('perfectdashboard-ping')) === 19) : ?>
        <div class="perfectdashboard-start perfectdashboard-view perfectdashboard-view-active">
            <div class="perfectdashboard-success-view">
                <h2 class="perfectdashboard-title">
                    <?php _e('This website has been successfully added to Perfect Dashboard.', 'perfectdashbord'); ?>
                </h2>

                <h3 class="perfectdashboard-subtitle">
                    Go to <a href="http://app.perfectdashboard.co" target="_blank">app.perfectdashboard.co</a> to:
                </h3>

                <ul class="perfectdashboard-list-features">
                    <li><span
                            class="dashicons dashicons-yes"></span> <?php _e('Manage WordPress, Themes and Plugins updates'); ?>
                    </li>
                    <li><span
                            class="dashicons dashicons-yes"></span> <?php _e('Do backups with automatic restoration tests',
                            'perfectdashboard'); ?></li>
                    <li><span
                            class="dashicons dashicons-yes"></span> <?php _e('Check automatically if nothing get broken after every update',
                            'perfectdashboard'); ?></li>
                </ul>

                <button type="button" onclick="document.getElementById('perfect-dashboard-install').submit()" class="button">
                    <?php _e('Click here to add your website again to Perfect&nbsp;Dashboard', 'perfectdashbord') ?>
                </button>
            </div>
        </div>
    <?php else : ?>
        <div class="perfectdashboard-start perfectdashboard-view perfectdashboard-view-active">

            <div class="perfectdashboard-col2">
                <h2>
                    <?php _e('Let Perfect Dashboard do all the backups & updates for you ', 'perfectdashbord'); ?>
                    <span><?php _e('[for&nbsp;FREE]', 'perfectdashbord'); ?></span>
                </h2>

                <ul class="perfectdashboard-list-features">
                    <li><span class="dashicons dashicons-yes"></span> <?php _e('One place to manage all websites',
                            'perfectdashboard'); ?></li>
                    <li><span
                            class="dashicons dashicons-yes"></span> <?php _e('Fully automated website test engine',
                            'perfectdashboard'); ?></li>
                    <li><span
                            class="dashicons dashicons-yes"></span> <?php _e('Automatic backup restoration test in cloud',
                            'perfectdashboard'); ?></li>
                </ul>

                <button type="button" onclick="document.getElementById('perfect-dashboard-install').submit()"
                        class="button button-primary button-hero perfectdashboard-big-btn">
                    <?php _e('Click here to add your website to Perfect&nbsp;Dashboard', 'perfectdashbord') ?>
                </button>

                <ul class="perfectdashboard-list-presale">
                    <li><?php _e('Functional Basic version. Free forever.', 'perfectdashboard'); ?></li>
                    <li><?php _e('Premium features for 6 weeks for free.', 'perfectdashboard'); ?></li>
                    <li><?php _e('No credit card required. Cancel anytime.', 'perfectdashboard'); ?></li>
                </ul>
            </div>

            <div class="perfectdashboard-col2">
                <div class="perfectdashboard-computer">
                    <img src="<?php echo plugins_url( 'media/images/laptop.svg', __DIR__ ); ?>" class="perfectdashboard-computer-img" alt="">
                    <video src="<?php echo plugins_url( 'media/images/laptop.mp4', __DIR__ ); ?>" class="perfectdashboard-computer-video" autoplay loop poster="<?php echo plugins_url( 'media/images/laptop_poster.png', __DIR__ ); ?>"></video>
                </div>
            </div>

        </div>
    <?php endif; ?>

    <form action="https://app.perfectdashboard.co/my-websites/site-addchild" method="post" enctype="multipart/form-data" id="perfect-dashboard-install">
        <input type="hidden" name="secure_key" value="<?php echo $key; ?>">
        <input type="hidden" name="user_email" value="<?php echo $user_email; ?>">
        <input type="hidden" name="site_frontend_url" value="<?php echo get_site_url(); ?>">
        <input type="hidden" name="site_backend_url" value="<?php echo get_admin_url(); ?>">
        <input type="hidden" name="cms_type" value="wordpress">
        <input type="hidden" name="version" value="<?php echo PERFECTDASHBOARD_VERSION; ?>">
    </form>

    <div class="perfectdashboard-settings perfectdashboard-view perfectdashboard-view-inactive">
        <form>
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row"><label for="perfectdashboard_key"><?php _e('Secure key',
                                'perfectdashboard'); ?></label></th>
                    <td><input id="perfectdashboard_key" placeholder="Key" type="text" class="regular-text"
                               value="<?php echo is_null($key) ? '' : $key; ?>"/></td>
                </tr>
                <tr>
                    <th scope="row"><label><?php _e('Site offline',
                                'perfectdashboard'); ?></label></th>
                    <td>
                        <label>
                            <input type="radio" name="site_offline" value="0" <?php if (empty($site_offline)) {echo 'checked="checked"';} ?>/><?php _e('No', 'perfectdashboard'); ?>
                        </label>
                        <label>
                            <input type="radio" name="site_offline" value="1" <?php if ($site_offline) {echo 'checked="checked"';} ?>/><?php _e('Yes', 'perfectdashboard'); ?>
                        </label>
                    </td>
                </tr>
                </tbody>
            </table>


            <p class="submit">
                <input type="submit" name="submit" id="perfectdashboard_save_config" class="button button-primary"
                       value="<?php _e('Save changes', 'perfectdashbord') ?>">
            </p>
        </form>
    </div>
