<?php
/**
 * Basic Logger class.
 *
 * @package Simple_WP_HTTP_Cache
 */

namespace EC\SWPHTTPC;

/**
 * Super basic logger for WP Error logs.
 */
class Logger implements Logger_Interface {
	/**
	 * Constructs log object.
	 *
	 * @return void
	 */
	public function __construct() {}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level   Log level for the request.
	 * @param string $message Message to be logged.
	 * @param array  $context Contextual data for log.
	 * @return void
	 */
	public function log( $level, $message, array $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $message ); // @codingStandardsIgnoreLine.
		}
	}
}
