<?php
/*
Plugin Name: WordPress SocialCrawler
Plugin URI: https://github.com/heavenconseil/wp-socialcrawler
Description: A WordPress plugin that crawls social networks looking for items containing a specific hashtag.
Version: 1.0.0
Author: Heaven
Author URI: http://heaven.fr
License: Proprietary
*/

define('WPSC_NAME', 'wp-socialcrawler');
define('WPSC_CONFIG', 'socialcrawler_config');

require __DIR__ . '/vendor/autoload.php';

use SocialCrawler\Crawler;
use SocialCrawler\Channel\Channel;

class WP_SocialCrawler {

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'wpsc_activation'));
        register_deactivation_hook(__FILE__, array($this, 'wpsc_deactivation'));

        if (is_admin()) {
            add_action('admin_menu', array($this, 'wpsc_admin_menu'));
            add_action('wp_ajax_wpsc_store', array($this, 'wp_ajax_wpsc_store'));
        }
    }

    public function wpsc_admin_menu() {
        $hook_suffix = add_menu_page(__('SocialCrawler', 'wpsc'), __('SocialCrawler', 'wpsc'), 'manage_options', WPSC_NAME, array($this, 'wpsc_admin_page'), 'dashicons-images-alt2');
        wp_enqueue_script('wp-socialcrawler-js', plugin_dir_url(__FILE__) . 'wp-socialcrawler.js', array('jquery'), false, true);
    }

    public function wpsc_admin_page() {
        $nonce = wp_create_nonce('wpsc_store');
        $tabs = array('configuration' => 'Configuration', 'logs' => 'Logs', 'help' => 'Help');
        $activeTab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs) ? $_GET['tab'] : 'configuration';
        wp_localize_script('wp-socialcrawler-js', 'wpsc_page', array('tab' => $activeTab));
?>
    <div class="wrap">
        <style scoped>
            .submit input { outline: none; }
            .spinner { margin-top: 4px; vertical-align: top; float: none; }
        </style>
        <h2 class="nav-tab-wrapper">
        <?php
            foreach ($tabs as $tab => $label) {
                $active = $tab === $activeTab ? 'nav-tab-active' : '';
        ?>
            <a href="<?php echo sprintf('?page=%s&tab=%s', WPSC_NAME, $tab); ?>" class="nav-tab <?php echo $active; ?>"><?php echo __($label, 'wpsc'); ?></a>
        <?php
            }
        ?>
        </h2>
        <form action="<?php echo plugin_dir_url(__FILE__) . '/wp-socialcrawler.php'; ?>" method="POST">
            <input type="hidden" name="action" value="wpsc_store">
            <input type="hidden" name="nonce" value="<?php echo $nonce; ?>">
        <?php
            switch ($activeTab) {
            case 'configuration':
        ?>
            <h2><?php echo __('SocialCrawler Configuration', 'wpsc'); ?></h2>
            <p><?php echo __('SocialCrawler will enable a Channel when at least the <strong>Application ID</strong> is set.', 'wpsc'); ?></p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="hashtag"><?php echo __('Hashtag'); ?></label></th>
                    <td>
                        <input type="text" name="hashtag" id="hashtag" value="<?php echo $this->option('hashtag'); ?>" placeholder="#">
                    </td>
                </tr>
            </table>
            <?php
                foreach ($this->option('channels') as $channel => $data) {
            ?>
            <h3><?php echo $channel; ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="channels_<?php echo $channel; ?>_id"><?php echo __('App ID', 'wpsc'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" name="channels_<?php echo $channel; ?>_id" id="channels_<?php echo $channel; ?>_id" value="<?php echo $this->option('channels.' . $channel . '.id'); ?>">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="channels_<?php echo $channel; ?>_secret"><?php echo __('App Secret (optional)', 'wpsc'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" name="channels_<?php echo $channel; ?>_secret" id="channels_<?php echo $channel; ?>_secret" value="<?php echo $this->option('channels.' . $channel . '.secret'); ?>">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="channels_<?php echo $channel; ?>_token"><?php echo __('Access Token (optional)', 'wpsc'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" name="channels_<?php echo $channel; ?>_token" id="channels_<?php echo $channel; ?>_token" value="<?php echo $this->option('channels.' . $channel . '.token'); ?>">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="channels_<?php echo $channel; ?>_media"><?php echo __('Media Types', 'wpsc'); ?></label></th>
                    <td>
                        <select name="channels_<?php echo $channel; ?>_media" id="channels_<?php echo $channel; ?>_media">
                        <?php foreach(array('Images + Videos' => Channel::MEDIA_IMAGES_VIDEOS, 'Images' => Channel::MEDIA_IMAGES, 'Videos' => Channel::MEDIA_VIDEOS) as $label => $value) { ?>
                            <option value="<?php echo $value; ?>" <?php echo $this->option('channels.' . $channel . '.media') == $value ? 'selected' : ''; ?>><?php echo __($label, 'wpsc'); ?></option>
                        <?php } ?>
                        </select>
                    </td>
                </tr>
            </table>
            <hr>
            <?php } ?>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Update">
                <span class="spinner left"></span>
            </p>
        <?php
                break;
            case 'logs':
                $logLevel = $this->option('log.level');
        ?>
            <h2><?php echo __('SocialCrawler Logs', 'wpsc'); ?></h2>
            <p><?php echo __('You can specify here whether SocialCrawler will log its operations, and with which granularity.', 'wpsc'); ?></p>
            <hr>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="log_level"><?php echo __('Log level', 'wpsc'); ?></label></th>
                    <td>
                        <select name="log_level" id="log_level">
                        <?php foreach(array('Disabled' => Crawler::LOG_DISABLED, 'Enabled' => Crawler::LOG_NORMAL, 'Verbose' => Crawler::LOG_VERBOSE) as $label => $value) { ?>
                            <option value="<?php echo $value; ?>" <?php echo $logLevel == $value ? 'selected' : ''; ?>><?php echo __($label, 'wpsc'); ?></option>
                        <?php } ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Update">
                <span class="spinner left"></span>
            </p>
            <hr>
            <?php
                if ($logLevel == Crawler::LOG_DISABLED || !is_writeable($this->option('log.path'))) {
                    echo sprintf(__('Logging is disabled or <code>%s/socialcrawler.log</code> cannot be created.', 'wpsc'), $this->option('log.path'));
                } else {
                    $log = @file_get_contents($this->option('log.path') . '/socialcrawler.log');
                    if (strlen($log) === 0) {
                        $log = __('The socialcrawler.log file is currently empty.', 'wpsc');
                    }
            ?>
                <h3><?php echo __('Log output'); ?></h3>
                <textarea style="width: 100%; height: 400px;" disabled><?php echo $log; ?></textarea>
            <?php } ?>
        <?php
                break;
            case 'help':
        ?>
            <h2><?php echo __('SocialCrawler Help', 'wpsc'); ?></h2>
            <h3><?php echo __('How can I use SocialCrawler?', 'wpsc'); ?></h3>
            <p><?php echo sprintf(__('When you\'re done with the configuration, add a call to <code>%1$s</code> in your crontab.<br>For example: <code>0 * * * * curl --silent --url %1$s &gt; /dev/null 2&gt;&amp;1</code> will look for new data every hour.'), plugins_url('wp-socialcrawler.php?cron', __FILE__)); ?></p>
            <h3><?php echo __('How can I use the data found by SocialCrawler?', 'wpsc'); ?></h3>
            <p><?php echo sprintf(__('As soon as SocialCrawler finishes its job, it will issue a <code>%s</code> action hook, so you will be able to make something of the data retrieved.'), 'socialcrawler_complete'); ?></p>
        <?php
                break;
            }
        ?>
        </form>
    </div>
<?php
    }

    public function wp_ajax_wpsc_store() {
        check_ajax_referer('wpsc_store', 'nonce');
        foreach ($_REQUEST as $option => $value) {
            $this->option(str_replace('_', '.', $option), $value);
        }
        die();
    }

    public function wpsc_activation() {
        $channels = array();
        foreach(glob(__DIR__ . '/vendor/heavenconseil/socialcrawler/SocialCrawler/Channel/*Channel.php') as $className) {
            $className = str_replace('.php', '', array_pop(explode(DIRECTORY_SEPARATOR, $className)));
            if ($className !== 'Channel') {
                $channels[$className] = array('id' => '', 'secret' => '', 'token' => '', 'since' => '', 'media' => Channel::MEDIA_IMAGES_VIDEOS);
            }
        }
        add_option(WPSC_CONFIG, array(
            'hashtag'   => '#socialcrawler',
            'channels'  => $channels,
            'log'       => array(
                'path'  => __DIR__,
                'level' => Crawler::LOG_NORMAL,
            )
        ));
    }

    public function wpsc_deactivation() {
        delete_option(WPSC_CONFIG);
    }

    public function option($optionPath = null, $newValue = null) {
        $option = get_option(WPSC_CONFIG);
        if (empty($optionPath)) {
            return $option;
        }

        $optionPath = explode('.', $optionPath);
        if (isset($newValue)) {
            $element = &$option;
            while(count($optionPath) > 0) {
                $part = array_shift($optionPath);
                if (isset($element[$part])) {
                    $element = &$element[$part];
                } else {
                    return false;
                }
            }
            $element = $newValue;
            update_option(WPSC_CONFIG, $option);
            return true;
        } else {
            while(count($optionPath) > 0) {
                $part = array_shift($optionPath);
                if (isset($option[$part])) {
                    $option = $option[$part];
                } else {
                    return false;
                }
            }
            return $option;
        }

        return false;
    }
}

if (isset($_GET['cron'])) {
    $wp_config_file = __DIR__ . '/wp-config.php';
    while(count(glob($wp_config_file)) === 0) {
        $wp_config_file = dirname(dirname($wp_config_file)) . '/wp-config.php';
    }
    require_once $wp_config_file;

    $wpsc = new WP_SocialCrawler();
    $crawler = new Crawler($wpsc->option());
    $result = $crawler->fetch($wpsc->option('hashtag'));
    foreach ($result as $channel => $data) {
        $wpsc->option('channels.' . $channel . '.since', $data->new_since);
    }
    do_action('socialcrawler_complete', $result);
} else {
    new WP_SocialCrawler();
}
