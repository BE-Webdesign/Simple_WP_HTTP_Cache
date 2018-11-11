<?php
/**
 * Logger interface.
 *
 * @package Simple_WP_HTTP_Cache
 */

namespace EC\SWPHTTPC;

/**
 * Basic logger interface derived from PSR-3, compatability can be improved.
 *
 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
 */
interface Logger_Interface {
	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level   Log level for the request.
	 * @param string $message Message to be logged.
	 * @param array  $context Contextual data for log.
	 * @return void
	 */
	public function log( $level, $message, array $context = array() );
}
