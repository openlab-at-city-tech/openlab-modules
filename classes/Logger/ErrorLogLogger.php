<?php
/**
 * ErrorLogLogger class.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Logger;

/**
 * ErrorLogLogger class.
 */
class ErrorLogLogger extends Logger {
	/**
	 * {@inheritDoc}
	 *
	 * @param string       $level   The log level.
	 * @param string       $message The log message.
	 * @param array<mixed> $context The log context.
	 * @return null
	 */
	public function log( $level, $message, array $context = [] ) {
		switch ( $level ) {
			case 'emergency':
			case 'alert':
			case 'critical':
			case 'error':
			case 'warning':
			case 'notice':
			case 'info':
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( $level . ' : ' . $message );
				break;

			case 'debug':
				if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( $level . ' : ' . $message );
					break;
				}
				break;
		}

		return null;
	}
}
