<?php

if ( class_exists('CompatCheckWP') ) {
    return;
}

/**
  * Compatibility Check helper for WordPress plugins
  * 
  * Helps check PHP and WordPress compatibility
  * before running the plugin.
  *
  * @author Samuel Elh <samelh.com/contact>
  * @version 0.1
  * @link http://github.com/elhardoum/wp-php-compat-check
  * @link https://samelh.com
  * @license GPL-3.0
  * @see https://github.com/elhardoum/wp-php-compat-check/blob/master/readme.md
  */

class CompatCheckWP
{
    const ARG_PHPVER = 'php_version';
    const ARG_WPVER = 'wp_version';
    const ARG_DEACTIVATE = 'deactivate_incompatible';
    const ARG_ERR_MSG = 'error_message';
    const ARG_PLUGIN_FILE = 'plugin_file';
    const ARG_PHPVER_OPERATOR = 'php_version_operator';
    const ARG_WPVER_OPERATOR = 'wp_version_operator';
    const CLASSNAME = 'CompatCheckWP'; // self::class alt for PHP < 5.5

    protected static $phpVersion;
    protected static $wpVersion;
    protected static $errorMessage;
    protected static $deactivateIncompatible;
    protected static $pluginFile;
    protected static $phpVersionOperator = '>=';
    protected static $wpVersionOperator = '>=';

    private static $compatible;
    private static $wp_version;
    private static $phpVersion_check = true;
    private static $wpVersion_check = true;
    private static $isNetworkActive;

    public static function getInstance()
    {
        static $instance = null;
        
        if ( null === $instance ) {
            $instance = new CompatCheckWP;
        }

        return $instance;
    }

    public static function check($args)
    {
        return self::parseArgs((array) $args)->verify();
    }

    private static function parseArgs($args)
    {
        if ( isset($args[self::ARG_PHPVER]) && (float) $args[self::ARG_PHPVER] ) {
            self::$phpVersion = (float) $args[self::ARG_PHPVER];
        }

        if ( isset($args[self::ARG_WPVER]) && (float) $args[self::ARG_WPVER] ) {
            self::$wpVersion = (float) $args[self::ARG_WPVER];
        }

        if ( isset($args[self::ARG_DEACTIVATE]) && $args[self::ARG_DEACTIVATE] ) {
            self::$deactivateIncompatible = true;
        }

        if ( isset($args[self::ARG_PLUGIN_FILE]) && $args[self::ARG_PLUGIN_FILE] ) {
            self::$pluginFile = esc_attr($args[self::ARG_PLUGIN_FILE]);
        } else {
            $backtrace = debug_backtrace();
            self::$pluginFile = basename(dirname($backtrace[1]['file'])) . DIRECTORY_SEPARATOR . basename($backtrace[1]['file']);
        }

        if ( isset($args[self::ARG_ERR_MSG]) && $args[self::ARG_ERR_MSG] ) {
            self::$errorMessage = esc_attr($args[self::ARG_ERR_MSG]);
        }

        if ( isset($args[self::ARG_PHPVER_OPERATOR]) && $args[self::ARG_PHPVER_OPERATOR] ) {
            self::$phpVersionOperator = $args[self::ARG_PHPVER_OPERATOR];
        }

        if ( isset($args[self::ARG_WPVER_OPERATOR]) && $args[self::ARG_WPVER_OPERATOR] ) {
            self::$WPVersionOperator = $args[self::ARG_WPVER_OPERATOR];
        }

        return self::getInstance();
    }

    private static function verify()
    {
        if ( self::$phpVersion ) {
            self::$phpVersion_check = (bool) version_compare(
                self::getUserphpVersion(),
                self::$phpVersion,
                self::$phpVersionOperator
            );
        }

        if ( self::$wpVersion ) {
            self::$wpVersion_check = (bool) version_compare(
                self::getUserWpVersion(),
                self::$wpVersion,
                self::$wpVersionOperator
            );
        }

        self::$compatible = self::$wpVersion_check && self::$phpVersion_check;

        return self::getInstance();
    }

    private static function getUserphpVersion()
    {
        return PHP_VERSION;
    }

    private static function getUserWpVersion()
    {
        if ( !isset(self::$wp_version) ) {
            self::$wp_version = get_bloginfo('version');
        }

        return self::$wp_version;
    }

    public static function then($callback, $args=null)
    {
        if ( !self::isCompatible() ) {
            return self::incompatible();
        } else if ( is_callable($callback) ) {
            if ( $args ) {
                return call_user_func_array($callback, (array) $args);
            } else {
                return call_user_func($callback);
            }
        }
    }

    public static function isCompatible()
    {
        return (bool) self::$compatible;
    }

    private static function incompatible()
    {
        if ( self::isNetworkActive() ) {
            add_action('network_admin_notices', array(self::CLASSNAME, 'errorNotice'), 999);
        } else {
            add_action('admin_notices', array(self::CLASSNAME, 'errorNotice'), 999);
        }

        if ( self::$deactivateIncompatible ) {
            if ( !function_exists('deactivate_plugins') ) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }

            deactivate_plugins( self::$pluginFile, true, self::isNetworkActive() );
        }

        return self::getInstance();
    }

    public static function isNetworkActive()
    {
        if ( !isset(self::$isNetworkActive) ) {
            if ( !is_multisite() ) {
                self::$isNetworkActive = false;
            } else {
                $plugins = get_site_option( 'active_sitewide_plugins', array() );
                self::$isNetworkActive = is_array($plugins) && isset($plugins[self::$pluginFile]);
            }
        }

        return self::$isNetworkActive;
    }

    private static function setDynamicErrorMessage()
    {
        self::$errorMessage = sprintf(
            'The following plugin could not be activated due to a compatibility error: <strong>%s</strong>.',
            self::$pluginFile
        );

        $list = array();

        if ( self::$phpVersion ) {
            $list []= sprintf(
                'PHP %s %s: %s',
                self::$phpVersionOperator,
                self::$phpVersion,
                (self::$phpVersion_check ? '<span style="color:green">&check;</span>' : '<span style="color:red">&times;</span>')
            );
        }

        if ( self::$wpVersion ) {
            $list []= sprintf(
                'WP %s %s: %s',
                self::$wpVersionOperator,
                self::$wpVersion,
                (self::$wpVersion_check ? '<span style="color:green">&check;</span>' : '<span style="color:red">&times;</span>')
            );
        }

        if ( $list ) {
            self::$errorMessage .= sprintf( ' [%s]', join($list, ', ') );
        }

        return self::getInstance();
    }

    public static function errorNotice()
    {
        if ( self::isNetworkActive() && !is_super_admin() )
            return;

        if ( !self::$errorMessage ) {
            self::setDynamicErrorMessage();
        }

        printf(
            '<div class="error notice is-dismissible"><p>%s</p></div>',
            self::$errorMessage
        );
    }
}
