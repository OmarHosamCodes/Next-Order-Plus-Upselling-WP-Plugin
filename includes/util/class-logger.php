<?php
/**
 * Logger utility class
 *
 * Handles logging of debug messages, warnings, and errors
 *
 * @package NextOrderPlus
 * @since 1.0.0
 */

namespace NOP\Util;

/**
 * Logger class for debugging and error tracking
 */
class NOP_Logger
{
    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Whether debugging is enabled
     *
     * @var bool
     */
    private $debug_enabled;

    /**
     * Constructor
     *
     * Sets up logging properties
     */
    public function __construct()
    {
        // Check for runtime debug mode (set by admin settings)
        $this->debug_enabled = (defined('NOP_DEBUG') && NOP_DEBUG) ||
            (defined('NOP_DEBUG_RUNTIME') && NOP_DEBUG_RUNTIME);

        $this->log_file = WP_CONTENT_DIR . '/debug-logs/nop-debug.log';
    }

    /**
     * Initialize the logger
     *
     * Sets up hooks and actions for logging
     *
     * @return void
     */
    public function init(): void
    {
        if ($this->debug_enabled) {
            // Register the log_event hook for other classes to use
            add_action('nop_log_event', [$this, 'log_event'], 10, 2);
        }
    }

    /**
     * Log an event with timestamp and level
     *
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     * @return void
     */
    public function log_event(string $message, string $level = 'info'): void
    {
        // Check runtime debug setting which can be changed in admin
        $this->debug_enabled = (defined('NOP_DEBUG') && NOP_DEBUG) ||
            (defined('NOP_DEBUG_RUNTIME') && NOP_DEBUG_RUNTIME);

        if (!$this->debug_enabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        // Log to file if writable
        if ($this->ensure_log_file()) {
            error_log($log_message, 3, $this->log_file);
        }

        // For critical errors, also use WordPress error log
        if ($level === 'error') {
            error_log("NOP Error: {$message}");
        }
    }

    /**
     * Ensure log file exists and is writable
     *
     * Creates the log directory if it doesn't exist
     *
     * @return bool Whether log file is writable
     */
    private function ensure_log_file(): bool
    {
        $log_dir = dirname($this->log_file);

        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // If file doesn't exist, try to create it
        if (!file_exists($this->log_file)) {
            @touch($this->log_file);
        }

        return file_exists($this->log_file) && is_writable($this->log_file);
    }

    /**
     * Format variable for logging
     *
     * @param mixed $var Variable to format
     * @return string Formatted string representation
     */
    public function format_var($var): string
    {
        if (is_array($var) || is_object($var)) {
            return print_r($var, true);
        }

        return (string) $var;
    }
}