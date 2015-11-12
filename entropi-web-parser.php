<?php
/**
* @package Entropi_WebParser
* @version 1
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
     * - image: a string holding a binary image representing the main post image
     *
     * @param $parsedPost
     */
    public static function processParsedPost($parsedPost)
    {
        $logHandle = fopen(realpath(ABSPATH . '../log') . '/parse.log', 'a');
        fwrite($logHandle, date('r') . ": Synchronizing \n");

        // if this post is already in the database, update it, otherwise insert a new one
        $query = new WP_Query(array(
            'meta_query' => array(
                array('key' => self::PREFIX . '_service', 'value' => $parsedPost->service),
                array('key' => self::PREFIX . '_foreign_key', 'value' => $parsedPost->id),
            )
        ));
        if ($query->have_posts()) {
            $query->the_post();
            $postId = $query->post->ID;
            fwrite($logHandle, date('r') . ":\t updating post: {$postId}\n");
            $postData = [
                'ID' => $query->post->ID,
                'post_title' => $parsedPost->title,
                'post_content' => $parsedPost->content,
            ];
            wp_update_post($postData);
            //update_post_meta($postId, self::PREFIX . '_service', $parsedPost->service);
            update_post_meta($postId, self::PREFIX . '_last_sync', time());
        } else {
            fwrite($logHandle, date('r') . ":\t inserting new post\n");
            $postData = [
                'post_content' => $parsedPost->content,
                'post_title' => $parsedPost->title,
                'post_author' => 1,     // admin; TODO: make configurable
                'post_status' => 'publish',     // TODO: make configurable
            ];
            if ($postId = wp_insert_post($postData)) {
                add_post_meta($postId, self::PREFIX . '_service', $parsedPost->service);
                add_post_meta($postId, self::PREFIX . '_foreign_key', $parsedPost->id);
                add_post_meta($postId, self::PREFIX . '_last_sync', time());
            }
        }

        fclose($logHandle);
    }
}