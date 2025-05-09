<?php
/**
 * ServerSentEventsLogger class.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Logger;

/**
 * ServerSentEventsLogger class.
 */
class ServerSentEventsLogger extends Logger {
	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed        $level   Log level.
	 * @param string       $message Message to log.
	 * @param array<mixed> $context  Contextual data.
	 * @return null
	 */
	public function log( $level, $message, array $context = [] ) {
		$data = compact( 'level', 'message' );

		switch ( $level ) {
			case 'emergency':
			case 'alert':
			case 'critical':
			case 'error':
			case 'warning':
			case 'notice':
			case 'info':
				echo "event: log\n";
				echo 'data: ' . wp_json_encode( $data ) . "\n\n";
				flush();
				break;

			case 'debug':
				if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
					echo "event: log\n";
					echo 'data: ' . wp_json_encode( $data ) . "\n\n";
					flush();
					break;
				}
				break;
		}

		return null;
	}
}
