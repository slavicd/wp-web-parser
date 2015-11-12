<?php
/*
Plugin Name: Entropi Web Parser for Wordpress: Dummy Source
Description: This plugin is for testing the Web Parser
Author: Slavic Dragovtev <slavic@entropi.me>
Version: 0.1
*/

defined('ABSPATH') || die ('Hello :)');

register_activation_hook(   __FILE__, array( 'Entropi_WebParser_Dummy', 'onActivation' ) );
register_deactivation_hook( __FILE__, array( 'Entropi_WebParser_Dummy', 'onDeactivation' ) );
register_uninstall_hook(    __FILE__, array( 'Entropi_WebParser_Dummy', 'onUninstall' ) );

add_action( 'plugins_loaded', array( 'Entropi_WebParser_Dummy', 'init' ) );

class Entropi_WebParser_Dummy
{
    const PREFIX = 'entropi_wparser';
    const SERVICE = 'dummy';

    private static $instance;

    private function __construct()
    {
        // this will be the hook for the main plugin to call
        add_action(self::PREFIX . '_dummy_synchronize', array('Entropi_WebParser_Dummy', 'synchronize'));
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
        // add sources option
        $sources = get_option(self::PREFIX . '_sources');
        $sources[self::SERVICE] = array();
        update_option(self::PREFIX . '_sources', $sources);
    }

    public static function onDeactivation()
    {
        $sources = get_option(self::PREFIX . '_sources');
        unset($sources[self::SERVICE]);
        update_option(self::PREFIX . '_sources', $sources);
    }

    public static function onUninstall()
    {
        // remove post meta
    }

    /**
     * @param callable $processor callback that will process a parsed post
     */
    public static function synchronize(callable $processor)
    {
        /**
         * This data would normally be parsed off a web resource or anything else.
         */
        $posts = [
            (object) [
                'service' => self::SERVICE,
                'foreign_key' => 0,
                'title' => 'Dummy post #0 RHCP',
                'content' => 'No it wont be long',
                'source' => 'http://example.com',
            ],
            (object) [
                'service' => self::SERVICE,
                'foreign_key' => 1,
                'title' => 'Dummy post #1, Blood Sugar Sex Magik',
                'content' => "Please don't turn away. Please don't turn me in, to them.",
                'source' => 'http://example.com',
            ],
        ];

        while (list($key, $postInput) = each($posts)) {
            call_user_func($processor, $postInput);
        }
    }
}