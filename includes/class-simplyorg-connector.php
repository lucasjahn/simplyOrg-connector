<?php
/**
 * Main SimplyOrg Connector class.
 *
 * Coordinates all plugin functionality and manages component initialization.
 *
 * @package SimplyOrg_Connector
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin coordinator class.
 *
 * Manages the initialization and coordination of all plugin components.
 *
 * @since 1.0.0
 */
class SimplyOrg_Connector {

	/**
	 * The single instance of the class.
	 *
	 * @var SimplyOrg_Connector|null
	 */
	private static $instance = null;

	/**
	 * API client instance.
	 *
	 * @var SimplyOrg_API_Client
	 */
	private $api_client;

	/**
	 * Hash manager instance.
	 *
	 * @var SimplyOrg_Hash_Manager
	 */
	private $hash_manager;

	/**
	 * Trainer syncer instance.
	 *
	 * @var SimplyOrg_Trainer_Syncer
	 */
	private $trainer_syncer;

	/**
	 * Event syncer instance.
	 *
	 * @var SimplyOrg_Event_Syncer
	 */
	private $event_syncer;

	/**
	 * Admin interface instance.
	 *
	 * @var SimplyOrg_Admin
	 */
	private $admin;

	/**
	 * Cron manager instance.
	 *
	 * @var SimplyOrg_Cron
	 */
	private $cron;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 * @return SimplyOrg_Connector
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Initializes all plugin components.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Initialize plugin components.
	 *
	 * Creates instances of all required classes.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		$this->api_client     = new SimplyOrg_API_Client();
		$this->hash_manager   = new SimplyOrg_Hash_Manager();
		$this->trainer_syncer = new SimplyOrg_Trainer_Syncer( $this->api_client, $this->hash_manager );
		$this->event_syncer   = new SimplyOrg_Event_Syncer( $this->api_client, $this->hash_manager, $this->trainer_syncer );
		$this->admin          = new SimplyOrg_Admin( $this->event_syncer, $this->trainer_syncer );
		$this->cron           = new SimplyOrg_Cron( $this->event_syncer, $this->trainer_syncer );
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Add any global hooks here if needed.
	}

	/**
	 * Get API client instance.
	 *
	 * @since 1.0.0
	 * @return SimplyOrg_API_Client
	 */
	public function get_api_client() {
		return $this->api_client;
	}

	/**
	 * Get hash manager instance.
	 *
	 * @since 1.0.0
	 * @return SimplyOrg_Hash_Manager
	 */
	public function get_hash_manager() {
		return $this->hash_manager;
	}

	/**
	 * Get trainer syncer instance.
	 *
	 * @since 1.0.0
	 * @return SimplyOrg_Trainer_Syncer
	 */
	public function get_trainer_syncer() {
		return $this->trainer_syncer;
	}

	/**
	 * Get event syncer instance.
	 *
	 * @since 1.0.0
	 * @return SimplyOrg_Event_Syncer
	 */
	public function get_event_syncer() {
		return $this->event_syncer;
	}

	/**
	 * Get admin interface instance.
	 *
	 * @since 1.0.0
	 * @return SimplyOrg_Admin
	 */
	public function get_admin() {
		return $this->admin;
	}

	/**
	 * Get cron manager instance.
	 *
	 * @since 1.0.0
	 * @return SimplyOrg_Cron
	 */
	public function get_cron() {
		return $this->cron;
	}
}

