<?php
/**
 * Root file for project.
 *
 * @package         Simple_WP_HTTP_Cache
 */

namespace EC\SWPHTTPC;

use \WP_HTTP_Response;
use \WP_Error;

/**
 * Sets the time in microseconds to the current request data.
 *
 * If Simple WP HTTP Cache is set to log request times, then this function
 * stores the current time before the request in the request arguments, which
 * are then accessed later to calculate the response times and log them.
 *
 * The accuracy of this is probably not the best as there is still a lot of
 * logic in WordPress being executed between when the time diff is caculated. It
 * will at least give a rough estimate of the actual response time.
 *
 * @param array $request Request data.
 * @return array Request data.
 */
function track_request_times( $request ) {
	if ( isset( $request['simple_wp_http_cache']['log_request_times'] ) && true === $request['simple_wp_http_cache']['log_request_times'] ) {
		$request['simple_wp_http_cache']['log_request_times'] = microtime( true );
	}

	return $request;
}
/** This action is documented in wp-includes/class-http.php */
add_filter( 'http_request_args', __NAMESPACE__ . '\track_request_times', 10, 1 );

/**
 * Logs http error message if request is set to log errors.
 *
 * @param mixed  $response Response data, either WP_Error or array.
 * @param string $context  Context data.
 * @param string $class    Request object class.
 * @param array  $request  Request data.
 * @param string $url      URL of request.
 * @return void
 */
function log_request_times( $response, $context, $class, $request, $url ) {
	if ( isset( $request['simple_wp_http_cache']['log_request_times'] ) ) {
		log( $response, $request, $url );
	}
}
/** This action is documented in wp-includes/class-http.php */
add_action( 'http_api_debug', __NAMESPACE__ . '\log_request_times', 10, 5 );

/**
 * Tries to grab a previously cached response for the outgoing request.
 *
 * @param array  $response Response data.
 * @param array  $request  Request data.
 * @param string $url      URL of request.
 * @return array|false False to continue with request, data on success.
 */
function check_http_cache( $response, $request, $url ) {
	$hash  = request_hash( $request, $url );
	$cache = cache_get( $hash );

	if ( false !== $cache ) {
		$response = $cache;
	}

	return $response;
}
/** This action is documented in wp-includes/class-http.php */
add_filter( 'pre_http_request', __NAMESPACE__ . '\check_http_cache', 10, 3 );

/**
 * Logs http error message if request is set to log errors.
 *
 * @param mixed  $response Response data, either WP_Error or array.
 * @param string $context  Context data.
 * @param string $class    Request object class.
 * @param array  $request  Request data.
 * @param string $url      URL of request.
 * @return void
 */
function log_http_errors( $response, $context, $class, $request, $url ) {
	if ( isset( $request['simple_wp_http_cache']['log_errors'] ) && true === $request['simple_wp_http_cache']['log_errors'] ) {
		log_http_error( $response, $request, $url );
	}
}
/** This action is documented in wp-includes/class-http.php */
add_action( 'http_api_debug', __NAMESPACE__ . '\log_http_errors', 10, 5 );

/**
 * Sets cache of HTTP Response, if the Simple WP HTTP Cache is active.
 *
 * @param array  $response Response data.
 * @param array  $request  Request data.
 * @param string $url      URL of request.
 * @return array Return response data.
 */
function set_http_cache( $response, $request, $url ) {
	// Check if request args are set to use simple cache.
	if ( isset( $request['simple_wp_http_cache']['active'] ) && true === $request['simple_wp_http_cache']['active'] ) {
		// Only cache actual HTTP responses.
		if ( isset( $response['http_response'] ) && $response['http_response'] instanceof WP_HTTP_Response ) {
			$hash = request_hash( $request, $url );

			/**
			 * Filter the cache expiration time set in seconds.
			 *
			 * @param array  $expiration Response data.
			 * @param array  $response   Response data.
			 * @param array  $request    Request data.
			 * @param string $url        URL of request.
			 * @return int Return expiration of the cache in seconds.
			 */
			$expire = apply_filters( 'simple_wp_http_cache_expiration', $request['simple_wp_http_cache']['expiration'] ?? 300, $response, $request, $url );

			$cache = cache_set( $hash, wp_json_encode( $response['http_response']->to_array() ), 'simple_http_cache_group', $expire );
		}
	}

	return $response;
}
/** This action is documented in wp-includes/class-http.php */
return add_filter( 'http_response', __NAMESPACE__ . '\set_http_cache', 10, 3 );

/**
 * Grabs a cache item based on the provided key.
 *
 * Uses WP Transients API as a backup to WP_CACHE.
 *
 * @param string $key Cache entity key to lookup.
 * @return mixed|false Returns false on failure.
 */
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

/**
 * Creates a unique hash based on the parameters of the request.
 *
 * Uses WP Transients API as a backup to WP_CACHE.
 *
 * @param string $key    Cache entity key.
 * @param mixed  $data   Data to be saved.
 * @param string $group  Cache group name.
 * @param int    $expire Expiration of cache item in seconds. 0 for indefinite.
 * @return mixed|false Returns false on failure.
 */
function cache_set( $key, $data, $group, $expire ) {
	// If object caching enabled prefer that and use transient API as backup.
	if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
		$cache = wp_cache_set( $key, $data, $group, $expire );
	} else {
		$cache = set_transient( $key, $data, $expire );
	}

	return $cache;
}

/**
 * Creates a unique hash based on the parameters of the request.
 *
 * @param array  $request  Request data.
 * @param string $url      URL of request.
 * @return string
 */
function request_hash( $request, $url ) {
	// Do not hash cache params, allowing for different cache params to not affect cache key look ups.
	unset( $request['simple_wp_http_cache'] );

	return sha1( serialize( $request ) . $url ); // @codingStandardsIgnoreLine.
}

/**
 * Logs http error message based on response data.
 *
 * @param mixed  $response Response data, either WP_Error or array.
 * @param array  $request  Request data.
 * @param string $url      URL of request.
 * @return void
 */
function log_http_error( $response, $request, $url ) {
	$is_error = false;

	if ( is_array( $response ) && isset( $response['http_response'] ) && $response['http_response'] instanceof WP_HTTP_Response ) {
		$status = $response['http_response']->get_status();

		$is_error = $status >= 400;

		/**
		 * Filters whether the response is an error.
		 *
		 * @param boolean $is_error Whether the response is an error.
		 * @param mixed   $response Response data, either WP_Error or array.
		 * @param array   $request  Request data.
		 * @param string  $url      URL of request.
		 * @return boolean
		 */
		$is_error = apply_filters( 'simple_wp_http_cache_is_error', $is_error, $response, $request, $url );
	}

	if ( is_wp_error( $response ) ) {
		$is_error = true;

		/** This action is documented in simple-wp-http-cache/src/main.php */
		$is_error = apply_filters( 'simple_wp_http_cache_is_error', $is_error, $response, $request, $url );
	}

	if ( $is_error ) {
		log( $response, $request, $url );
	}
}

/**
 * Logs message based on response data.
 *
 * @param mixed  $response Response data, either WP_Error or array.
 * @param array  $request  Request data.
 * @param string $url      URL of request.
 * @return void
 */
function log( $response, $request, $url ) {
	if ( is_array( $response ) && isset( $response['http_response'] ) && $response['http_response'] instanceof WP_HTTP_Response ) {
		$headers         = $response['http_response']->get_headers();
		$response_object = $response['http_response']->get_response_object();

		$date       = sanitize_text_field( $headers['date'] ?? date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) ) . ' @ ' . date_i18n( get_option( 'time_format' ), current_time( 'timestamp' ) ) );
		$method     = sanitize_text_field( $request['method'] ?? 'GET' );
		$status     = sanitize_text_field( $response['http_response']->get_status() ?? 'No Status' );
		$user_agent = sanitize_text_field( $request['user-agent'] ?? 'No User Agent' );
		$protocol   = sanitize_text_field( 'HTTP/' . $response_object->protocol_version ?? '1.1' );
		$message    = "[$date] \"$method $url $protocol\" $status \"$user_agent\"";

		if ( isset( $request['simple_wp_http_cache']['log_request_times'] ) ) {
			// Get time diff in milliseconds.
			$diff      = round( ( microtime( true ) - $request['simple_wp_http_cache']['log_request_times'] ) * 1000 );
			$time_diff = ' Request Latency: ' . $diff . 'ms';

			$message .= $time_diff;
		}

		/**
		 * Filters the log level for the current log request.
		 *
		 * @param string $log_level Log level to set for log. Default: 'debug'
		 * @param mixed  $response  Response data, either WP_Error or array.
		 * @param array  $request   Request data.
		 * @param string $url       URL of request.
		 * @return boolean
		 */
		$log_level = apply_filters( 'simple_wp_http_cache_log_level', 'debug', $response, $request, $url );

		/**
		 * Filters the class name for the Logger.
		 *
		 * The Logger class being used should be compatible and match the
		 * interface of `simple-wp-http-cache/src/class-logger-interface.php`.
		 *
		 * Make sure to use a fully declared namespace like \MyLogger, when
		 * hooking into this filter.
		 *
		 * @param string $log_class Whether the response is an error.
		 * @param mixed  $response  Response data, either WP_Error or array.
		 * @param array  $request   Request data.
		 * @param string $url       URL of request.
		 * @param string $log_level The current log level for the log request.
		 * @return boolean
		 */
		$class = apply_filters( 'simple_wp_http_cache_log_class', __NAMESPACE__ . '\Logger', $response, $request, $url, $log_level );

		$logger = new $class();
		$logger->log( $log_level, $message );
	}

	if ( is_wp_error( $response ) ) {
		$date     = sanitize_text_field( date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) ) . ' @ ' . date_i18n( get_option( 'time_format' ), current_time( 'timestamp' ) ) );
		$method   = sanitize_text_field( $request['method'] ?? 'GET' );
		$status   = esc_html__( 'WordPress Error:', 'simple-wp-http-cache' );
		$messages = sanitize_text_field( implode( $response->get_error_messages(), ', ' ) );

		$message = "[$date] \"$method $url\" $status \"$messages\"";

		/** This action is documented in simple-wp-http-cache/src/main.php */
		$log_level = apply_filters( 'simple_wp_http_cache_log_level', 'debug', $response, $request, $url );

		/** This action is documented in simple-wp-http-cache/src/main.php */
		$class = apply_filters( 'simple_wp_http_cache_log_class', __NAMESPACE__ . '\Logger', $response, $request, $url, $log_level );

		$logger = new $class();
		$logger->log( $log_level, $message );
	}
}
