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

    private static $instance;

    private function __construct()
    {
        // HOLY SHIT, if we don't do this, then any time the cron references the hook it just silently ignores us..
        add_action(self::PREFIX . '_parse', array('Entropi_WebParser', 'run'));
    }

    public static function init()
    {
        is_null( self::$instance ) && self::$instance = new self;
        return self::$instance;
    }

    /**
     * Ran upon plugin activation.
     * - schedule crons for parsing
     */
    public static function onActivation()
    {
        // schedule crons
        wp_schedule_event( time(), 'hourly', self::PREFIX . '_parse' );

        $fh = fopen(realpath(ABSPATH . '../log') . '/parse.log', 'a');
        fwrite($fh, date('r') . ": Activating web parser\n");
        fclose($fh);

        // add sources option
        add_option(self::PREFIX . '_sources', array());
    }

    public static function onDeactivation()
    {
        // unschedule schedule crons
        wp_clear_scheduled_hook(self::PREFIX . '_parse');
        delete_option(self::PREFIX . '_sources');
    }

    public static function onUninstall()
    {
        // remove post meta
    }

    /**
     * Performs synchronization for all registered parsers.
     */
    public static function run()
    {
        $logHandle = fopen(realpath(ABSPATH . '../log') . '/parse.log', 'a');
        fwrite($logHandle, date('r') . ": Parsing\n");

        // load all active sources
        $sources = get_option(self::PREFIX . '_sources');

        // for each source synchronize posts
        foreach ($sources as $key=>$val) {
            // source is the name of a web-parser source plugin. Each such plugin is supposed to register an
            // action hook of the form desartlab_parser_<plugin-name>_synchronize
            do_action(self::PREFIX . '_' . $key . '_synchronize', function($postData) {
                self::processParsedPost($postData);
            });
        }

        fclose($logHandle);
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
     *
     * @param $parsedPost
     */
    private static function processParsedPost($parsedPost)
    {
        $logHandle = fopen(realpath(ABSPATH . '../log') . '/parse.log', 'a');
        fwrite($logHandle, date('r') . ": Synchronizing \n");

        // if this post is already in the database, update it, otherwise insert a new one
        $query = new WP_Query(array(
            'meta_query' => array(
                array('key' => self::PREFIX . '_service', 'value' => $parsedPost->service),
                array('key' => self::PREFIX . '_foreign_key', 'value' => $parsedPost->foreign_key),
            )
        ));
        if ($query->have_posts()) {
            // this post is already in the db
            $query->the_post();
            $postId = $query->post->ID;
            fwrite($logHandle, date('r') . ":\t updating post: {$postId}\n");
            $postData = [
                'ID' => $query->post->ID,
                'post_title' => $parsedPost->title,
                'post_content' => $parsedPost->content,
            ];
            wp_update_post($postData);
            update_post_meta($postId, self::PREFIX . '_last_sync', time());
            update_post_meta($postId, 'source', $parsedPost->source);

            // deal with attachments
            if (isset($parsedPost->image)) {
                self::processImage($postId, $parsedPost);
            }
        } else {
            // this post looks to be new
            fwrite($logHandle, date('r') . ":\t inserting new post\n");
            $postData = [
                'post_content' => $parsedPost->content,
                'post_title' => $parsedPost->title,
                'post_author' => 1,     // admin; TODO: make configurable
                'post_status' => 'publish',     // TODO: make configurable
            ];
            if ($postId = wp_insert_post($postData)) {
                add_post_meta($postId, self::PREFIX . '_service', $parsedPost->service);
                add_post_meta($postId, self::PREFIX . '_foreign_key', $parsedPost->foreign_key);
                add_post_meta($postId, self::PREFIX . '_last_sync', time());
                add_post_meta($postId, 'source', $parsedPost->source);
            }
        }

        fclose($logHandle);
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

        $logHandle = fopen(realpath(ABSPATH . '../log') . '/parse.log', 'a');
        fwrite($logHandle, date('r') . ":\t detected mime type: {$mime} \n");
        fclose($logHandle);

        $types = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
        ];

        if (!isset($types[$mime])) {
            throw new Exception('Could not detect extension from mime type.');
        }

        return $types[$mime];
    }
}