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
  * @version 0.2
  * @link http://github.com/elhardoum/wp-php-compat-check
  * @link https://samelh.com
  * @license GPLv2 or later
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
    const ARG_TEXTDOMAIN = 'textdomain';

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
    private static $textdomain;

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
        // parse user arguments
        self::parseArgs((array) $args)->verify();

        // load default textdomain
        if ( ! isset( self::$textdomain ) && false === has_action('plugins_loaded', array(__CLASS__, 'i18n')) ) {
            add_action('plugins_loaded', array(__CLASS__, 'i18n'));
        }

        return self::getInstance();
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

        if ( isset($args[self::ARG_TEXTDOMAIN]) && $args[self::ARG_TEXTDOMAIN] ) {
            self::$textdomain = $args[self::ARG_TEXTDOMAIN];
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
        if ( ! isset(self::$wp_version) ) {
            self::$wp_version = get_bloginfo('version');
        }

        return self::$wp_version;
    }

    public static function then($callback, $args=null)
    {
        if ( ! self::isCompatible() ) {
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
            add_action('network_admin_notices', array(__CLASS__, 'errorNotice'), 999);
        } else {
            add_action('admin_notices', array(__CLASS__, 'errorNotice'), 999);
        }

        if ( self::$deactivateIncompatible ) {
            if ( ! function_exists('deactivate_plugins') ) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }

            deactivate_plugins( self::$pluginFile, true, self::isNetworkActive() );
        }

        return self::getInstance();
    }

    public static function isNetworkActive()
    {
        if ( ! isset(self::$isNetworkActive) ) {
            if ( ! is_multisite() ) {
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
            __('The following plugin could not be activated due to a compatibility error: <strong>%s</strong>.', self::getTextDomain()),
            self::$pluginFile
        );

        $list = array();

        if ( self::$phpVersion ) {
            $list []= sprintf(
                __('PHP %s %s: %s', self::getTextDomain()),
                self::$phpVersionOperator,
                self::$phpVersion,
                (self::$phpVersion_check ? __('<span style="color:green">&check;</span>', self::getTextDomain()) : (
                    __('<span style="color:red">&times;</span>', self::getTextDomain())
                ))
            );
        }

        if ( self::$wpVersion ) {
            $list []= sprintf(
                __('WP %s %s: %s', self::getTextDomain()),
                self::$wpVersionOperator,
                self::$wpVersion,
                (self::$wpVersion_check ? __('<span style="color:green">&check;</span>', self::getTextDomain()) : (
                    __('<span style="color:red">&times;</span>', self::getTextDomain())
                ))
            );
        }

        if ( $list ) {
            self::$errorMessage .= sprintf( __(' [%s]', self::getTextDomain()), join($list, ', ') );
        }

        return self::getInstance();
    }

    public static function errorNotice()
    {
        if ( self::isNetworkActive() && ! is_super_admin() )
            return;

        if ( !self::$errorMessage ) {
            self::setDynamicErrorMessage();
        }

        printf(
            '<div class="error notice is-dismissible"><p>%s</p></div>',
            self::$errorMessage
        );
    }

    static function i18n()
    {
        return load_plugin_textdomain(
            self::getTextDomain(),
            false,
            str_replace(trailingslashit(WP_PLUGIN_DIR), '', __DIR__ ) . '/languages'
        );
    }

    private static function getTextDomain()
    {
        return isset( self::$textdomain ) && self::$textdomain ? self::$textdomain : 'compatcheckwp';
    }
}
