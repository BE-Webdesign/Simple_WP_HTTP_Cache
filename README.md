# Simple_WP_HTTP_Cache

Simple HTTP WordPress Cache for WordPress

## Requirements

PHP 7.0+
WordPress 4.9.8+

## Installation

This is a WordPress plugin, and can be [installed following these instructions](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation_by_Uploading_a_Zip_Archive).

## Usage

### Fundamentals

Simple WP HTTP Cache builds on top of WordPress's internal WP_HTTP API.

Any request using WordPress's WP HTTP, can be passed an additional argument to
control the functionality of the Simple HTTP caching and logging. Below is an
example of how to cache a network request:

```php
$response = wp_safe_remote_get(
	'https://edwincromley.com/wp-json/wp/v2/posts?per_page=1',
	[
		'simple_wp_http_cache' => [
			'active' => true,
		],
	]
);
```

This will store the response value by default for 5 minutes, preventing any
repeating calls in that time frame from reaching out over the network.

There are a number of arguments that can be added to simple_wp_http_cache.

#### `'active'`

Sets the cache to active, meaning it will cache any uncached or bypassed
requests. This will only cache successful requests, that reach the network.
Internal WP failures will not be cached by default. The `active` parameter is
only in charge of whether a fresh cache is set, not whether it is fetched. If
active is set to any value other than true, or not set at all, and there is a
previously cached result for the same request, the cached value will still be
returned.

```php
[
	'simple_wp_http_cache' => [
		'active' => true,
	],
]
```

#### `'expiration'`

The expiration of the cache set in seconds. Defaults to 300; 5 minutes.

To store the value indefinitely pass a value of 0 like so:

```php
[
	'simple_wp_http_cache' => [
		'active' => true,
		'expiration' => 0,
	],
]
```

Expiration can be changed from the `'simple_wp_http_cache_expiration'` filter.

```php
apply_filters( 'simple_wp_http_cache_expiration', $request['simple_wp_http_cache']['expiration'] ?? 300, $response, $request, $url );
```

#### `'bypass'`

By setting bypass to true you can avoid the cache hits. In the example below,
a new cache value will be set every time. The cache lookup is bypassed, and the
request goes through and the new response value is cached due to the active
flag. The following is really only useful for debugging and not recommended for
any other reason:

```php
[
	'simple_wp_http_cache' => [
		'active' => true,
		'bypass' => true,
	],
]
```

#### `'log_errors'`

`log_errors` is used to ensure that any internal WP Failures, or network request
failures are passed to the Logger. By default, if you have both WP_DEBUG and
WP_DEBUG_LOG enabled, the message will be output to WordPress's error log.

Alternative logging solutions can be implemented based on request, or response.

```php
[
	'simple_wp_http_cache' => [
		'log_errors' => true,
	],
]
```

#### `'log_request_times'`

`log_request_times` is used to log any request that reaches the network, and
displays the response time in milliseconds. Requests that are cached, will not
log by default on a cache hit, only on a cache miss.

```php
[
	'simple_wp_http_cache' => [
		'log_request_times' => true,
	],
]
```

### Caching

Simple WP HTTP Cache makes use of WordPress' internal caching APIs. If
`WP_CACHE` is set to `true`, the [Object Cache](https://codex.wordpress.org/Class_Reference/WP_Object_Cache) that is configured for WordPress
will be used. If `WP_CACHE` is not enabled, then the [Transients API](https://codex.wordpress.org/Transients_API) will be
used as a fallback.

Requests are cached by the request parameters being sent as well as the URL.
Any change in the request parameters or URL will result in a cache miss. The
cache key is generated using sha1, to create a hash, and the likelihood of any
collisions is extremely low.

You can pass the caching arguments directly to any HTTP function call like
`wp_safe_remote_get`, or you can leverage WordPress' internal hooks to set the
parameters for Simple WP HTTP Cache.

The following example will cache every request WordPress makes for 10 minutes:

```php
```

### Logging

Simple WP HTTP Cache also features a pluggable Logger class. You can override
the base Logger with a matching class of your own. The current Logger is based
around the [PSR-3 interface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md), and will likely be changed to match that in the
future.

If you have a custom Logger class, make sure it has the method `log` with the
following signature:

```php
public function log( $level, $message, array $context = array() ) {
	// Log my stuff here.
}
```

A basic custom logging class might look something like this:

```php
/**
 * My custom logger.
 */
class MyLogger {
	/**
	 * Constructs log object.
	 *
	 * @return void
	 */
	public function __construct() {
		return;
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level
	 * @param string $message
	 * @param array  $context
	 * @return void
	 */
	public function log( $level, $message, array $context = array() ) {
		// Log your message to wherever it should go.
	}
}

```

Then you can use your custom class like so:

```php
add_filter( 'simple_wp_http_cache_log_class', function( $class ) {
	$class = '\MyLogger';

	return $class;
} );
```

Now whenever any responses are sent to be logged, they will go through your
custom class. You can take advantage of the `'simple_wp_http_cache_log_class'`
hook to implement your logging strategy. Rather than writing complex `log`
methods for one class, it is recommended that you create multiple classes with
a `log` method that fits that particular use case.

## Wrap Up

Simple WP HTTP Cache is a basic enhancement to WordPress's existing HTTP API,
enabling the ability to quickly cache particular requests, or log out errors and
response times. The abilities are actually quite flexible and pluggable, as they
leverage various hooks in WordPress.
