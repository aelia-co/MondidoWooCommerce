<?php namespace MondidoBase;

/**
 * A simple logger class. This class replaces the one included with the official
 * Mondido plugin, which was designed to log to someone else's PaperTrail account.
 *
 * @author Aelia <support@aelia.co>
 * @link https://aelia.co
 */
class Log {
	// TODO Handle "debug mode" condition
	// - When debug mode is activated, all log messages should be logged.
	// - When debug mode is deactivated, only critical messages should be logged

	/**
	 * The WC Logger used by this class.
	 *
	 * @var \WC_Logger
	 * @author Aelia <support@aelia.co>
	 */
	protected static $_logger;

	/**
	 * The handle against which the log messages will be stored. This will be used
	 * by WooCommerce to generate the log file name.
	 *
	 * @var string
	 * @author Aelia <support@aelia.co>
	 */
	protected static $log_handle = 'mondido';

	/**
	 * Returns the WC Logger instance.
	 *
	 * @return \WC_Logger
	 */
	protected static function logger() {
		if(empty(self::$_logger)) {
			self::$_logger = new \WC_Logger();
		}
		return self::$_logger;
	}

	/**
	 * Logs a message.
	 * This method replaces the original "send" method, using a WC Logger instead.
	 * The signature has been preserved, for compatibility with existing log calls.
	 *
	 * @param string $message The message to log.
	 * @param string $component
	 * @param string $program
	 * @author Aelia <support@aelia.co>
	 */
	public function send($message, $component = "web", $program = "next_big_thing") {
		$message = "$message - $component - $program";
		$this->logger()->add(self::$log_handle, $message);
	}
}
