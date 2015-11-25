<?php
/**
* @package Entropi_WebParser
* @version 0.1
*/
/*
Plugin Name: Entropi Web Parser for Wordpress
Description: This plugin is for parsing web content and storing it in database. It works in tandem with child plugins, written with a specific web resource in mind.
Author: Slavic Dragovtev <slavic@entropi.me>
Version: 0.1
*/

defined('ABSPATH') || die ('Hello :)');

register_activation_hook(   __FILE__, array( 'Entropi_WebParser', 'onActivation' ) );
register_deactivation_hook( __FILE__, array( 'Entropi_WebParser', 'onDeactivation' ) );
register_uninstall_hook(    __FILE__, array( 'Entropi_WebParser', 'onUninstall' ) );

add_action( 'plugins_loaded', array( 'Entropi_WebParser', 'init' ) );

class Entropi_WebParser
{
    const PREFIX = 'entropi_wparser';
    /**
     * The human readable name
     */
    const NAME = 'Post Parser';
    const FILENAME = 'entropi-web-parser';

    private static $instance;

    private function __construct()
    {
        require_once ABSPATH . 'wp-admin/includes/taxonomy.php';

        // HOLY SHIT, if we don't do this, then any time the cron references the hook it just silently ignores us..
        add_action(self::PREFIX . '_parse', array(__CLASS__, 'run'));

        // add admin menus
        add_action( 'admin_menu', array(__CLASS__, 'createAdminMenu'));

        add_action( 'admin_enqueue_scripts', array(__CLASS__, 'addCustomStatic') );

        // load configs

        // add custom cron intervals
        add_filter( 'cron_schedules', array(__CLASS__, 'addCustomIntervals'));
    }

    public static function init()
    {
        is_null( self::$instance ) && self::$instance = new self;
        return self::$instance;
    }

    public static function createAdminMenu()
    {
        add_submenu_page( 'edit.php', self::NAME . ' Options', 'Sources', 'manage_options', self::PREFIX, array(__CLASS__, 'getPluginOptionsPage'));
    }

    public static function addCustomStatic($hook)
    {
        wp_register_style( 'custom_wp_admin_css', plugins_url(self::FILENAME. '/style.css'), false, '1.0.0' );
        wp_enqueue_style('custom_wp_admin_css');

        wp_register_script( 'custom_wp_admin_js', plugins_url(self::FILENAME. '/main.js'), false, '1.0.0' );
        wp_enqueue_script('custom_wp_admin_js');
    }

    /**
     * Outputs the options page markup and handles saving of plugin settings.
     * Also allows running of individual parsers.
     *
     * @return void
     */
    public static function getPluginOptionsPage()
    {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        $parsers = get_option(self::PREFIX . '_sources');

        $selectedParser = false;

        if (isset($_GET['sp'])) {
            $parserId = $_GET['sp'];

            if (!empty($_POST)) {
                switch ($_POST['operation']) {
                    case 'save':
                        $data = $_POST;
                        unset($data['operation']);
                        // got data. a post request
                        foreach ($data as $key=>$val) {
                            $parsers[$parserId][$key] = $val;
                        }
                        update_option(self::PREFIX . '_sources', $parsers);

                        // update schedules
                        wp_clear_scheduled_hook(self::PREFIX . '_parse', array($parserId));
                        if ($parsers[$parserId]['schedule']!='disabled') {
                            wp_schedule_event( time(), $parsers[$parserId]['schedule'], self::PREFIX . '_parse', array($parserId));
                        }
                        break;
                    case 'run':
                        self::run($parserId);
                        echo json_encode(array(
                            'status' => 'ok'
                        ));
                        die;
                    case 'stop':
                        $parsers[$parserId]['stop_please'] = true;
                        update_option(self::PREFIX . '_sources', $parsers);
                        die;
                }
            }

            $selectedParser = $parsers[$parserId];

            $schedules = array(
                'daily' => 'Daily',
                'biweekly' => 'Twice a week',
                'weekly' => 'Weekly',
                'bimonthly' => 'Twice a month',
                'monthly' => 'Monthly',
            );

            global $wpdb;
            $tableName = $wpdb->prefix . str_replace('-','_', self::PREFIX) . '_log';
            $logEntries = $wpdb->get_results("SELECT * FROM $tableName WHERE parser='$parserId' ORDER BY `time` DESC, `id` DESC LIMIT 150");
        }

        // build a few links to the parser details page
        foreach ($parsers as $key=>$value) {
            $parsers[$key]['link_to'] = $_SERVER['SCRIPT_NAME'] . '?' . http_build_query(array_merge($_GET, array('sp' => $key)));
        }

        // used by the view:
        $parsers; $selectedParser; $schedules; $logEntries;
        include (ABSPATH .'wp-content/plugins/' . self::FILENAME . '/options.php');
    }

    /**
     * A test page which triggers running all parsers
     * @return void
     */
    public static function getPluginRunPage()
    {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        self::run();
    }

    /**
     * Ran upon plugin activation.
     */
    public static function onActivation()
    {
        // create custom tables
        global $wpdb;
        $tableName = $wpdb->prefix . str_replace('-','_', self::PREFIX) . '_log';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $tableName (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
          parser VARCHAR (16) NOT NULL,
          text text NOT NULL,
          UNIQUE KEY id (id)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);

        self::log('Activating web parser');

        // add sources option
        add_option(self::PREFIX . '_sources', array());
    }

    public static function onDeactivation()
    {
        delete_option(self::PREFIX . '_sources');
    }

    public static function onUninstall()
    {
        // remove post meta

        // delete the custom tables
        global $wpdb;
        $tableName = $wpdb->prefix . str_replace('-','_', self::PREFIX) . '_log';
        $wpdb->query("DROP TABLE IF EXISTS $tableName");
    }

    public static function addCustomIntervals($schedules)
    {
        self::log('creating custom schedules');

        $schedules['biweekly'] = array(
            'interval' => (int) 604800/2,
            'display' => __('Twice a week')
        );

        $schedules['weekly'] = array(
            'interval' => 604800,
            'display' => __('Weekly')
        );

        $schedules['bimonthly'] = array(
            'interval' => 3600 * 24 * 15,
            'display' => __('Twice a month')
        );

        $schedules['monthly'] = array(
            'interval' => 3600 * 24 * 30,
            'display' => __('Monthly')
        );

        return $schedules;
    }

    /**
     * Performs synchronization for all registered parsers.
     * @param string $parser
     */
    public static function run($parser=null)
    {
        // load all active sources
        $sources = get_option(self::PREFIX . '_sources');

        set_time_limit(0); // parsing is a slow process

        if (!is_null($parser)) {
            $toRun = array($parser => $sources[$parser]);
        } else {
            $toRun = $sources;
        }

        // for each source synchronize posts
        foreach ($toRun as $key=>$val) {
            // if any of the plugins fails, this should not stop other plugins from running
            try {
                if ($sources[$key]['started']) {
                    self::log('Parser already working.. Not running this time.', $key);
                    continue;
                }

                self::log('Start', $key);
                $sources[$key]['started'] = time();
                update_option(self::PREFIX . '_sources', $sources);

                // source is the name of a web-parser source plugin. Each such plugin is supposed to register an
                // action hook of the form desartlab_parser_<plugin-name>_synchronize
                do_action(self::PREFIX . '_' . $key . '_synchronize', function($postData) {
                    self::processParsedPost($postData);
                });

                self::log('End', $key);
                $sources[$key]['started'] = false;
                update_option(self::PREFIX . '_sources', $sources);
            } catch (Exception $e) {
                self::log($e->getMessage(), $key);

                $sources[$key]['started'] = false;
                update_option(self::PREFIX . '_sources', $sources);
            }
        }

        echo 'Script finished execution.';
    }

    /**
     * This is called by child parsers when a new post is parsed
     *
     * $parsedPost should be an object with following properties (plus indicating mandatory):
     * + id: the identifier within the outside service that offers the post (i.e. the id on the source site)
     * + title: wp title
     * + content: wp content
     * + service: a string identifying the parser (e.g. dummy, or 3d-prints.com)
     * + source: the URL of the
     * - image: a string holding a binary file representing the main post image
     * - category
     * - tags: array of tag strings
     *
     * @param stdClass|bool $parsedPost if this is false see the $error parameter
     * @param string $error
     */
    private static function processParsedPost($parsedPost, $error=null)
    {
        if (!$parsedPost) {
            self::log($error);
            return ;
        }
        // if this post is already in the database, update it, otherwise insert a new one
        $query = new WP_Query(array(
            'meta_query' => array(
                array('key' => self::PREFIX . '_service', 'value' => $parsedPost->service),
                array('key' => self::PREFIX . '_foreign_key', 'value' => $parsedPost->foreign_key),
            )
        ));

        $postData = [
            'post_title' => $parsedPost->title,
            'post_content' => $parsedPost->content,
        ];

        if (
            isset($parsedPost->category) && $parsedPost->category &&
            (!$query->have_posts() || $parsedPost->shouldUpdate)
        ) {
            // almost always we prepare the categories
            $termInfo = term_exists($parsedPost->category, 'category');
            if (!$termInfo) {
                $categoryId = wp_create_category($parsedPost->category);
            } else {
                $categoryId = $termInfo['term_id'];
            }

            $postData['post_category'] = array($categoryId);
        }

        if ($query->have_posts()) {
            // this post is already in the db. Only update if forced.
            if ($parsedPost->shouldUpdate) {
                $query->the_post();
                $postData['ID'] = $postId = $query->post->ID;
                self::log("updating post: {$postId}", $parsedPost->service);

                wp_update_post($postData);
                update_post_meta($postId, self::PREFIX . '_last_sync', time());
                update_post_meta($postId, 'source', $parsedPost->source);
                update_post_meta($postId, self::PREFIX . '_last_sync', time());

                // deal with attachments
                if (isset($parsedPost->image)) {
                    self::processImage($postId, $parsedPost);
                }
            } else {
                self::log("Post already in db: {$parsedPost->source}", $parsedPost->service);
            }
        } else {
            // this post looks to be new
            self::log('Inserting new post: ' . $parsedPost->title);
            $postData['post_author'] = 1;               // admin; TODO: make configurable
            $postData['post_status'] = 'publish';       // TODO: make configurable

            if ($postId = wp_insert_post($postData)) {
                add_post_meta($postId, self::PREFIX . '_service', $parsedPost->service);
                add_post_meta($postId, self::PREFIX . '_foreign_key', $parsedPost->foreign_key);
                add_post_meta($postId, self::PREFIX . '_last_sync', time());
                add_post_meta($postId, 'source', $parsedPost->source);

                // deal with attachments
                if ($parsedPost->image) {
                    self::processImage($postId, $parsedPost);
                }
            }
        }
    }

    /**
     * @param int $id post id
     * @param stdClass $post parsed post data
     */
    private static function processImage($id, $post)
    {
        $wpUploadDir = wp_upload_dir();
        $upDir = $wpUploadDir['path'];

        $extension = self::detectExtension($post->image);
        if (!$extension) {
            return ;
        }
        $fileName = $post->service . '-' . $id . '.' . $extension;
        $fullFileName = $upDir . DIRECTORY_SEPARATOR . $fileName;
        $fileHandle = fopen($fullFileName, 'w');
        fwrite($fileHandle, $post->image);
        fclose($fileHandle);

        $fileType = wp_check_filetype( $fileName , null );  // some duplicate work, i know...

        // Prepare an array of post data for the attachment.
        $attachment = array(
            'guid'           => $wpUploadDir['url'] . '/' .  $fileName ,
            'post_mime_type' => $fileType['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', $fileName),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attachId = wp_insert_attachment($attachment, $fullFileName, $id);

        // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        // Generate the metadata for the attachment, and update the database record.
        $attachData = wp_generate_attachment_metadata($attachId, $fullFileName);
        wp_update_attachment_metadata($attachId, $attachData);

        set_post_thumbnail($id, $attachId);
    }

    /**
     * Detects extension based on the mime type of a string
     * @param string $file
     * @return string
     * @throws Exception
     */
    private static function detectExtension($file)
    {
        $finfo = new finfo(FILEINFO_MIME);
        $mime = strtok($finfo->buffer($file),';');

        $types = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
        ];

        if (!isset($types[$mime])) {
            return false;
        }

        return $types[$mime];
    }

    /**
     * Logs errors
     * @param string $message
     * @param string $parser
     */
    public static function log($message, $parser='')
    {
        global $wpdb;

        $tableName = $wpdb->prefix . str_replace('-','_', self::PREFIX) . '_log';

        $wpdb->insert(
            $tableName,
            array(
                'parser' => $parser,
                'time' => current_time( 'mysql' ),
                'text' => $message,
            )
        );
    }
}