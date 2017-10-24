# Compatibility Check helper for WordPress plugins

Helps check PHP and WordPress compatibility before running the plugin.

You can wrap your main WordPress plugin code in this helper, and specify which minimum PHP version your plugin should run on, and also optionally which WordPress version to require as a minimum, and then the plugin should work with no fatal errors if all checks are met and the environment is compatible.

## Usage:

In your main plugin file, e.g `wp-content/plugins/my-plugin/my-plugin.php`, require this compat class:

```php
require_once 'wp-php-compat-check/wp-php-compat-check.php';
```

Then, make sure to copy all of your main file code (except the plugin header comments of course, into the `then` method):

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

CompatCheckWP::check([
    'php_version' => 7.0,
    'deactivate_incompatible' => true,
    'wp_version' => 4.2,
])->then(function(){
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

CompatCheckWP::check([
    'php_version' => 7.0,
    'deactivate_incompatible' => true,
    'wp_version' => 4.2,
])->then('my_plugin_runs_here');
```

