<?php
/**
 * Cron Manager.
 *
 * Handles scheduled synchronization tasks.
 *
 * @package SimplyOrg_Connector
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages cron jobs for automatic synchronization.
 *
 * Schedules and executes daily sync operations.
 *
 * @since 1.0.0
 */
class SimplyOrg_Cron {

	/**
	 * Event syncer instance.
	 *
	 * @var SimplyOrg_Event_Syncer
	 */
	private $event_syncer;

	/**
	 * Trainer syncer instance.
	 *
	 * @var SimplyOrg_Trainer_Syncer
	 */
	private $trainer_syncer;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param SimplyOrg_Event_Syncer   $event_syncer   Event syncer instance.
	 * @param SimplyOrg_Trainer_Syncer $trainer_syncer Trainer syncer instance.
	 */
	public function __construct( SimplyOrg_Event_Syncer $event_syncer, SimplyOrg_Trainer_Syncer $trainer_syncer ) {
		$this->event_syncer   = $event_syncer;
		$this->trainer_syncer = $trainer_syncer;

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action( 'simplyorg_daily_sync', array( $this, 'run_daily_sync' ) );
	}

	/**
	 * Run daily synchronization.
	 *
	 * Executes the full sync process for events and trainers.
	 *
	 * @since 1.0.0
	 */
	public function run_daily_sync() {
		// Check if sync is enabled.
		$settings = get_option( 'simplyorg_connector_settings', array() );
		if ( empty( $settings['sync_enabled'] ) ) {
			$this->log( 'Daily sync skipped: sync is disabled in settings.' );
			return;
		}

		$this->log( 'Starting daily sync...' );

		// Get date range from settings.
		$settings   = get_option( 'simplyorg_connector_settings', array() );
		$start_date = isset( $settings['sync_start_date'] ) ? $settings['sync_start_date'] : gmdate( 'Y-m-d' );
		$end_date   = isset( $settings['sync_end_date'] ) ? $settings['sync_end_date'] : gmdate( 'Y-12-31', strtotime( '+1 year' ) );

		// Sync events with configured date range.
		$results = $this->event_syncer->sync_events( $start_date, $end_date );

		if ( is_wp_error( $results ) ) {
			$this->log( 'Daily sync failed: ' . $results->get_error_message() );
			return;
		}

		// Log results.
		$this->log(
			sprintf(
				'Daily sync completed. Created: %d, Updated: %d, Skipped: %d, Errors: %d',
				$results['created'],
				$results['updated'],
				$results['skipped'],
				count( $results['errors'] )
			)
		);

		// Log errors if any.
		if ( ! empty( $results['errors'] ) ) {
			foreach ( $results['errors'] as $error ) {
				$this->log( 'Error: ' . $error );
			}
		}
	}

	/**
	 * Log sync activity.
	 *
	 * Writes log entries to the WordPress error log.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[SimplyOrg Connector] ' . $message );
		}

		// Also store in option for admin display.
		$logs = get_option( 'simplyorg_connector_logs', array() );
		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'message'   => $message,
		);

		// Keep only last 100 log entries.
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		update_option( 'simplyorg_connector_logs', $logs );
	}

	/**
	 * Get recent sync logs.
	 *
	 * Retrieves the most recent sync log entries.
	 *
	 * @since 1.0.0
	 * @param int $limit Number of log entries to retrieve.
	 * @return array Array of log entries.
	 */
	public function get_logs( $limit = 20 ) {
		$logs = get_option( 'simplyorg_connector_logs', array() );
		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	/**
	 * Clear all sync logs.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function clear_logs() {
		return delete_option( 'simplyorg_connector_logs' );
	}
}

