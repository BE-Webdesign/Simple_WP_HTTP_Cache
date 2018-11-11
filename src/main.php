<?php
/**
 * Root file for project.
 *
 * @package         Simple_WP_HTTP_Cache
 */

namespace EC\SWPHTTPC;

use \WP_HTTP_Response;
use \WP_Error;

function track_request_times( $request ) {
	if ( isset( $request['simple_wp_http_cache']['log_request_times'] ) && true === $request['simple_wp_http_cache']['log_request_times'] ) {
		$request['simple_wp_http_cache']['log_request_times'] = microtime( true );
	}

	return $request;
}
/** This action is documented in wp-includes/class-http.php */
add_filter( 'http_request_args', __NAMESPACE__ . '\track_request_times', 10, 1 );

function log_request_times( $response, $context, $class, $request, $url ) {
	if ( isset( $request['simple_wp_http_cache']['log_request_times'] ) ) {
		log( $response, $request, $url );
	}
}
/** This action is documented in wp-includes/class-http.php */
add_action( 'http_api_debug', __NAMESPACE__ . '\log_request_times', 10, 5 );

function check_http_cache( $response, $request, $url ) {
	$hash = request_hash( $request, $url );
	$cache = cache_get( $hash );

	if ( false !== $cache ) {
		$response = $cache;
	}

	return $response;
}
/** This action is documented in wp-includes/class-http.php */
add_filter( 'pre_http_request', __NAMESPACE__ . '\check_http_cache', 10, 3 );

function log_http_errors( $response, $context, $class, $request, $url ) {
	if ( isset( $request['simple_wp_http_cache']['log_errors'] ) && true === $request['simple_wp_http_cache']['log_errors'] ) {
		log_http_error( $response, $request, $url );
	}
}
/** This action is documented in wp-includes/class-http.php */
add_action( 'http_api_debug', __NAMESPACE__ . '\log_http_errors', 10, 5 );

function set_http_cache( $response, $request, $url ) {
	// Check if request args are set to use simple cache.
	if ( isset( $request['simple_wp_http_cache']['active'] ) && true === $request['simple_wp_http_cache']['active'] ) {
		if ( isset( $response['http_response'] ) && $response['http_response'] instanceof WP_HTTP_Response ) {
			$hash = request_hash( $request, $url );

			$expire = apply_filters( 'simple_wp_http_cache_expiration', $request['simple_wp_http_cache']['expiration'] ?? 300, $response, $request, $url );

			$cache = cache_set( $hash, wp_json_encode( $response['http_response']->to_array() ), 'simple_http_cache_group', $expire );
		}
	}

	return $response;
}
/** This action is documented in wp-includes/class-http.php */
return add_filter( 'http_response', __NAMESPACE__ . '\set_http_cache', 10, 3 );

function cache_get( $key ) {
	$data = false;

	// If object caching enabled prefer that and use transient API as backup.
	if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
		$cache = wp_cache_get( $key, 'simple_http_cache_group', false, true );

		$data = is_string( $cache ) ? json_decode( $cache, true ) : false;
	} else {
		$cache = get_transient( $key );

		$data = is_string( $cache ) ? json_decode( $cache, true ) : false;
	}

	return $data;
}

function cache_set( $key, $data, $group, $expire ) {
	// If object caching enabled prefer that and use transient API as backup.
	if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
		$cache = wp_cache_set( $key, $data, $group, $expire );
	} else {
		$cache = set_transient( $key, $data, $expire );
	}

	return $cache;
}

function request_hash( $request, $url ) {
	// Do not hash cache params, allowing for different cache params to not affect cache key look ups.
	unset( $request['simple_wp_http_cache'] );

	return sha1( serialize( $request ) . $url );
}

function log_http_error( $response, $request, $url ) {
	$is_error = false;

	if ( is_array( $response ) && isset( $response['http_response'] ) && $response['http_response'] instanceof WP_HTTP_Response ) {
		$status = $response['http_response']->get_status();

		$is_error = $status >= 400;

		$is_error = apply_filters( 'simple_wp_http_cache_is_error', $is_error, $response, $request, $url );
	}

	if ( is_wp_error( $response ) ) {
		$is_error = true;

		$is_error = apply_filters( 'simple_wp_http_cache_is_error', $is_error, $response, $request, $url );
	}

	if ( $is_error ) {
		log( $response, $request, $url );
	}
}

function log( $response, $request, $url ) {
	if ( is_array( $response ) && isset( $response['http_response'] ) && $response['http_response'] instanceof WP_HTTP_Response ) {
		$headers = $response['http_response']->get_headers();
		$response_object = $response['http_response']->get_response_object();

		$date   = sanitize_text_field( $headers['date'] ?? date_i18n( get_option('date_format'), current_time( 'timestamp' ) ) .' @ '. date_i18n( get_option('time_format'), current_time( 'timestamp' ) ) );
		$method = sanitize_text_field( $request['method'] ?? 'GET' );
		$status = sanitize_text_field( $response['http_response']->get_status() ?? 'No Status' );
		$user_agent = sanitize_text_field( $request['user-agent'] ?? 'No User Agent' );
		$protocol = sanitize_text_field( 'HTTP/' . $response_object->protocol_version ?? '1.1' );
		$message = "[$date] \"$method $url $protocol\" $status \"$user_agent\"";

		if ( isset( $request['simple_wp_http_cache']['log_request_times'] ) ) {
			// Get time diff in milliseconds.
			$diff = round( ( microtime( true ) - $request['simple_wp_http_cache']['log_request_times'] ) * 1000 );
			$time_diff = ' Request Latency: ' . $diff . 'ms';

			$message .= $time_diff;
		}

		$log_level = apply_filters( 'simple_wp_http_cache_log_level', 'debug', $response, $request, $url );
		$class = apply_filters( 'simple_wp_http_cache_log_class', __NAMESPACE__ . '\Logger', $response, $request, $url, $log_level );

		$logger = new $class();
		$logger->log( $log_level, $message );
	}

	if ( is_wp_error( $response ) ) {
		$date   = sanitize_text_field( date_i18n( get_option('date_format'), current_time( 'timestamp' ) ) .' @ '. date_i18n( get_option('time_format'), current_time( 'timestamp' ) ) );
		$method = sanitize_text_field( $request['method'] ?? 'GET' );
		$status = esc_html__( 'WordPress Error:', 'simple-wp-http-cache' );
		$messages = sanitize_text_field( implode( $response->get_error_messages(), ', ' ) );

		$message = "[$date] \"$method $url\" $status \"$messages\"";

		$log_level = apply_filters( 'simple_wp_http_cache_log_level', 'debug', $response, $request, $url );
		$class = apply_filters( 'simple_wp_http_cache_log_class', __NAMESPACE__ . '\Logger', $response, $request, $url, $log_level );

		$logger = new $class();
		$logger->log( $log_level, $message );
	}
}
