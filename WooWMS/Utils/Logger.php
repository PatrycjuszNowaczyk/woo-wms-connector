<?php

namespace WooWMS\Utils;

class Logger {
	private static Logger|null $instance = null;
	
	public static string|null $logFilePath = null;
	
	
	private function __construct() {
		try {
			
			$upload_dir        = wp_upload_dir();
			$log_file          = $upload_dir['basedir'] . '/WooWMS.log';
			self::$logFilePath = $log_file;
			
			// Ensure the file exists and is writable
			if ( ! file_exists( self::$logFilePath ) ) {
				if ( ! touch( self::$logFilePath ) ) {
					throw new \Exception( "Unable to create log file: " . self::$logFilePath );
				}
			}
			
			if ( ! is_writable( self::$logFilePath ) ) {
				throw new \Exception( "Log file is not writable: " . self::$logFilePath );
			}
		} catch ( \Exception $e ) {
			$this->error( $e->getMessage() );
		}
	}
	
	/**
	 * Create singleton instance of Logger class if not already created and return it
	 * @return Logger
	 */
	public static function init(): Logger {
		if ( null === self::$logFilePath ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Log message to log file
	 *
	 * @param string $message
	 * @param string $level
	 *
	 * @return void
	 */
	private function log( string $message, string $level = 'info' ): void {
		try {
			$timestamp         = current_time( 'Y-m-d H:i:s' );
			$formatted_message = "[{$timestamp}] [{$level}] {$message}\n";
			
			if ( false === file_put_contents( self::$logFilePath, $formatted_message, FILE_APPEND ) ) {
				throw new \Exception( "Unable to write to log file: " . self::$logFilePath );
			}
			
		} catch ( \Exception $e ) {
			$this->error( $e->getMessage() );
		}
	}
	
	/**
	 * Log info to log file
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public function info( string $message ): void {
		$this->log( $message );
	}
	
	/**
	 * Log warning to log file
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public function warning( string $message ): void {
		$this->log( $message, 'warning' );
	}
	
	/**
	 * Log error to log file
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public function error( string $message ): void {
		$this->log( $message, 'error' );
	}
	
	/**
	 * Get log file full path
	 * @return string
	 */
	public function getLogFilePath(): string {
		return self::$logFilePath;
	}
}
