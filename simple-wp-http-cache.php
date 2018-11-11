<?php
/**
 * Plugin Name:     Simple WP HTTP Cache
 * Plugin URI:      N/A
 * Description:     A simple HTTP Cache and log for WordPress.
 * Author:          Edwin Cromley
 * Author URI:      https://edwincromley.com
 * Text Domain:     simple-wp-http-cache
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Simple_WP_HTTP_Cache
 */

// Require main source file.
require_once 'src/class-logger-interface.php';
require_once 'src/class-logger.php';
require_once 'src/main.php';

$response = wp_safe_remote_get(
	'https://edwincromley.com/wp-json/wp/v2/posts?per_page=1&fields=post_title',
	[
		'simple_wp_http_cache' => [
			'active' => true,
			'log_request_times' => true,
		],
	]
);

$response = wp_safe_remote_get( 'https://edwincromley.com/wp-json/wp/v2/posts?per_page=2&fields=post_title' );
// echo '<pre>', var_dump( $response ), '</pre>';

$response = wp_safe_remote_get(
	'https://edwincromley.com/wp-json/wp/v2/ega',
	[
		'simple_wp_http_cache' => [
			'log_errors' => true,
			'log_request_times' => true,
		],
	]
);
echo '<pre>', var_dump( $response['http_response']->to_array() ), '</pre>';
echo '<pre>', var_dump( array_keys( $response ) ), '</pre>';
echo '<pre>', var_dump( $response ), '</pre>';
// var_dump( json_decode( wp_remote_retrieve_body( $response ) )[0]->id );
exit;
