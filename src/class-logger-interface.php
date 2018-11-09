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
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function log( $level, $message, array $context = array() );
}
