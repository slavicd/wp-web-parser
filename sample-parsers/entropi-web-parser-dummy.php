<?php
/*
Plugin Name: Entropi Web Parser: Dummy Source
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
    const NAME = 'Dummy Parser';

    private static $instance;

    private function __construct()
    {
        // this will be the hook for the main plugin to call
        add_action(self::PREFIX . '_' . self::SERVICE . '_synchronize', array(__CLASS__, 'synchronize'));
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
        if ($sources===false) {
            throw new Exception('This plugin should only be installed after Entropi Web Parser for Wordpress');
        }
        $sources[self::SERVICE] = array(
            'name' => self::NAME
        );
        update_option(self::PREFIX . '_sources', $sources);
    }

    public static function onDeactivation()
    {
        $sources = get_option(self::PREFIX . '_sources');
        unset($sources[self::SERVICE]);
        update_option(self::PREFIX . '_sources', $sources);

        wp_clear_scheduled_hook(self::PREFIX . '_parse', array(self::SERVICE));
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
                'title' => 'Dummy post #0',
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
            (object) [
                'service' => self::SERVICE,
                'foreign_key' => 2,
                'title' => 'Sample Post',
                'content' => "Sample post description",
                'image' => file_get_contents('/home/slavic/Downloads/MailChimp_Logo_NoBackground_Dark.png'),
                'source' => 'http://example.com',
            ],
        ];

        $posts = [];

        while (list($key, $postInput) = each($posts)) {
            call_user_func($processor, $postInput);
        }
        foreach (range(0,9) as $op) {
            wp_cache_delete('alloptions', 'options');
            $parsers = get_option(self::PREFIX . '_sources');
            if (isset($parsers[self::SERVICE]['stop_please'])) {
                break;
            }
            sleep(4);
        }
    }
}