# Compatibility Check helper for WordPress plugins

Helps check PHP and WordPress compatibility before running the plugin.

You can wrap your main WordPress plugin code in this helper, and specify which minimum PHP version your plugin should run on, and also optionally which WordPress version to require as a minimum, and then the plugin should work with no fatal errors if all checks are met and the environment is compatible.

This helper requires PHP 5.0 at least, and should be working for multisite environments as well.
## Usage:

In your main plugin file, e.g `wp-content/plugins/my-plugin/my-plugin.php`, require this compat class:

```php
require_once 'wp-php-compat-check/wp-php-compat-check.php';
```

*For composer users:*
```bash
composer require "elhardoum/wp-php-compat-check:*@dev"
```

Then, make sure to copy all of your main file code (except the plugin header comments of course) into the `then` method:

```php
CompatCheckWP::check(
    // ... arguments
)->then(function(){
    // [ ... ] Your plugin code goes here.
});
```

Here's an example:

```php
<?php
/*
Plugin Name: Tweak MailChimp Feeds RSS
Plugin URI: https://samelh.com/blog
Description: Tweak MailChimp RSS Feeds to add the featured image, excerpt and a read more button.
Author: Samuel Elh
Version: 0.1
Author URI: https://go.samelh.com/buy-me-a-coffee
*/

defined ( 'ABSPATH' ) || exit ( 'Direct access not allowed.' . PHP_EOL );

require_once 'wp-php-compat-check/wp-php-compat-check.php';

CompatCheckWP::check(array(
    'php_version' => 7.0,
    'deactivate_incompatible' => true,
    'wp_version' => 4.2,
))->then(function(){
    $GLOBALS['feed_ignore_categories'] = array( 
        // [...]
    );

    function filter_the_content_feed( $content ) {
        // [...]
    }


    function pre_get_posts_mailchimp_rss($query) {
        // [...]
    }

    // [...]
});
```

Here's another example, this time without using [closures](http://php.net/manual/en/functions.anonymous.php) since they're supported as of PHP 5.3:

```php
<?php
/*
Plugin Name: My Plugin
Plugin URI: https://samelh.com/blog
Description: My Plugin.
Author: Samuel Elh
Version: 0.1
Author URI: https://go.samelh.com/buy-me-a-coffee
*/

function my_plugin_runs_here() {
    // [ ... ] my plugin code..
}

require_once 'wp-php-compat-check/wp-php-compat-check.php';

CompatCheckWP::check(array(
    'php_version' => 7.0,
    'deactivate_incompatible' => true,
    'wp_version' => 4.2,
))->then('my_plugin_runs_here');
```

One last example, if you don't want to use `then` method, there's `isCompatible` to get you covered:
```php
$is_compatible = CompatCheckWP::check(array(
    'php_version' => 7.0,
    'deactivate_incompatible' => true,
    'wp_version' => 4.2,
))->isCompatible();

if ( $is_compatible ) { /* ... */ }
```

## Options:

The following are called options, which you can pass to the `check` method of the `CompatCheckWP` class (`CompatCheckWP::check( array( ...options ) )`):

**`php_version`**: The minimum PHP version your plugin should work with, if needed.

**`wp_version`**: The minimum WordPress version your plugin should work with, if needed.

**`deactivate_incompatible`**: Deactivate the plugin right away if not compatible. Otherwise keep it active but the plugin code will not be running.

**`error_message`**: Customize the incompatibility error message. It defaults to: *The following plugin could not be activated due to a compatibility error: plugin-name/plugin-name.php. [PHP >= XX: ✓, WP >= XX: ×]*

**`plugin_file`**: Optional, pass a string of the plugin file in the format of this function return: [`plugin_basename`](https://codex.wordpress.org/Function_Reference/plugin_basename) (e.g `'plugin_file' => plugin_basename(MY_PLUGIN_FILE)
`). If you are calling the class from the main plugin file and not passing this argument, then the plugin file will be programatically extracted from the debug backtrace, otherwise if calling from another file then it would certainly fail and you'll want to pass in this argument.

**`php_version_operator`**: The operator by default is `>=` for checking the PHP version requirement.

**`wp_version_operator`**: The operator by default is `>=` for checking WP version requirement.