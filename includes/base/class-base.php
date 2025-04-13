<?php
/**
 * Base class for all plugin components
 *
 * Provides common functionality and properties for service classes
 *
 * @package NextOrderPlus
 * @since 1.0.0
 */

namespace NOP\Base;

/**
 * Abstract base class that all service classes extend
 */
abstract class NOP_Base
{
    /**
     * Plugin prefix for hooks, options, etc.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Plugin directory path
     *
     * @var string
     */
    protected $plugin_path;

    /**
     * Plugin URL for assets
     *
     * @var string
     */
    protected $plugin_url;

    /**
     * Logger instance for debugging
     *
     * @var \NOP\Util\NOP_Logger
     */
    protected $logger;

    /**
     * Whether debugging is enabled
     *
     * @var bool
     */
    protected $debug;

    /**
     * Constructor
     *
     * Sets up common properties used by all service classes
     *
     * @param \NOP\Util\NOP_Logger|null $logger Optional logger instance
     */
    public function __construct($logger = null)
    {
        $this->prefix = NOP_PREFIX;
        $this->plugin_path = NOP_DIR;
        $this->plugin_url = NOP_URL;
        $this->debug = defined('NOP_DEBUG') && NOP_DEBUG;
        $this->logger = $logger;
    }

    /**
     * Initialize the service
     *
     * Abstract method that must be implemented by all service classes.
     * Used to set up hooks, filters, and other initialization tasks.
     *
     * @return void
     */
    abstract public function init(): void;

    /**
     * Log debug message if debugging is enabled
     *
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     * @return void
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if ($this->debug && $this->logger) {
            $this->logger->log_event($message, $level);
        }
    }

    /**
     * Get plugin text domain for translations
     *
     * @return string Text domain
     */
    protected function get_text_domain(): string
    {
        return 'next-order-plus';
    }

    /**
     * Sanitize numeric input
     *
     * @param mixed $input Input to sanitize
     * @return float Sanitized numeric value
     */
    protected function sanitize_number($input): float
    {
        return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Check if current request is AJAX
     *
     * @return bool Whether current request is AJAX
     */
    protected function is_ajax(): bool
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}