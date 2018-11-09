<?php
/**
 * Root file for project.
 *
 * @package         Simple_WP_HTTP_Cache
 */

namespace EC\SWPHTTPC;

use \WP_HTTP_Response;
use \WP_Error;

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
		$hash = request_hash( $request, $url );

		$expire = apply_filters( 'simple_wp_http_cache_expiration', $request['simple_wp_http_cache']['expiration'] ?? 300, $response, $request, $url );

		$cache = cache_set( $hash, wp_json_encode( $response ), 'simple_http_cache_group', $expire );
	}

	return $response;
}
/** This action is documented in wp-includes/class-http.php */
return add_filter( 'http_response', __NAMESPACE__ . '\set_http_cache', 10, 3 );

function track_request_times( $request ) {
	if ( isset( $request['simple_wp_http_cache']['log_request_times'] ) && true === $request['simple_wp_http_cache']['log_request_times'] ) {
		$request['simple_wp_http_cache']['log_request_times'] = current_time( 'timestamp' );
	}

	return $request;
}
/** This action is documented in wp-includes/class-http.php */
add_filter( 'http_request_args', _NAMESPACE__ . '\track_request_times', 10, 1 );

function log_request_times( $response, $context, $class, $request, $url ) {
	if ( isset( $request['simple_wp_http_cache']['log_request_times'] ) ) {
		log( $response, $request, $url );
	}
}
/** This action is documented in wp-includes/class-http.php */
add_action( 'http_api_debug', __NAMESPACE__ . '\log_request_times', 10, 5 );

function cache_get( $key ) {
	$data = false;

	// If object caching enabled prefer that and use transient API as backup.
	if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
		$cache = wp_cache_get( $key, 'simple_http_cache_group', false, true );

		$data = json_decode( $cache, true );

		if ( isset( $data['body'] ) && is_string( $data['body'] ) ) {
			$data['body'] = json_decode( $data['body'], true );
		}
	} else {
		$cache = get_transient( $key );

		$data = json_decode( $cache, true ) ?? false;

		if ( false !== $data ) {
			if ( isset( $data['body'] ) && is_string( $data['body'] ) ) {
				$data['body'] = json_decode( $data['body'], true );
			}
		}
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

function log_http_error( $error, $request, $url ) {
	$is_error = false;

	if ( is_array( $error ) && isset( $error['http_response'] ) && $error['http_response'] instanceof WP_HTTP_Response ) {
		$status = $error['http_response']->get_status();

		$is_error = $status >= 400;

		$is_error = apply_filters( 'simple_wp_http_cache_is_error', $is_error, $error, $request, $url );
	}

	if ( is_wp_error( $error ) ) {
		$is_error = true;

		$is_error = apply_filters( 'simple_wp_http_cache_is_error', $is_error, $error, $request, $url );
	}

	if ( $is_error ) {
		log( $error, $request, $url );
	}
}

function log( $error, $request, $url ) {
	if ( is_array( $error ) && isset( $error['http_response'] ) && $error['http_response'] instanceof WP_HTTP_Response ) {
		$headers = $error['http_response']->get_headers();
		$response = $error['http_response']->get_response_object();

		$date   = sanitize_text_field( $headers['date'] ?? date_i18n( get_option('date_format'), current_time( 'timestamp' ) ) .' @ '. date_i18n( get_option('time_format'), current_time( 'timestamp' ) ) );
		$method = sanitize_text_field( $request['method'] ?? 'GET' );
		$status = sanitize_text_field( $error['http_response']->get_status() ?? 'No Status' );
		$user_agent = sanitize_text_field( $request['user-agent'] ?? 'No User Agent' );
		$protocol = sanitize_text_field( 'HTTP/' . $response->protocol_version ?? '1.1' );
		$message = "[$date] \"$method $url $protocol\" $status \"$user_agent\"";

		if ( isset( $request['simple_wp_http_cache']['log_request_times'] ) ) {
			$diff = ( current_time( 'timestamp' ) - $request['simple_wp_http_cache']['log_request_times'] );
			$time_diff = ' Request Latency: ' . $diff . 'ms';

			$message .= $time_diff;
		}

		$class = apply_filters( 'simple_wp_http_cache_log_class', __NAMESPACE__ . '\Logger' );

		$logger = new $class();
		$logger->log( 'debug', $message );
	}

	if ( is_wp_error( $error ) ) {
		$date   = sanitize_text_field( date_i18n( get_option('date_format'), current_time( 'timestamp' ) ) .' @ '. date_i18n( get_option('time_format'), current_time( 'timestamp' ) ) );
		$method = sanitize_text_field( $request['method'] ?? 'GET' );
		$status = esc_html__( 'WordPress Error:', 'simple-wp-http-cache' );
		$messages = sanitize_text_field( implode( $error->get_error_messages(), ', ' ) );

		$message = "[$date] \"$method $url\" $status \"$messages\"";

		$class = apply_filters( 'simple_wp_http_cache_log_class', __NAMESPACE__ . '\Logger' );

		$logger = new $class();
		$logger->log( 'debug', $message );
	}
}
