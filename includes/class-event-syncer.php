<?php
/**
 * Event Syncer.
 *
 * Handles synchronization of events from SimplyOrg to WordPress.
 *
 * @package SimplyOrg_Connector
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs event data from SimplyOrg to WordPress seminar posts.
 *
 * Manages creation and updating of seminar custom post types with proper
 * grouping of multi-day events.
 *
 * @since 1.0.0
 */
class SimplyOrg_Event_Syncer {

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param SimplyOrg_API_Client     $api_client     API client instance.
	 * @param SimplyOrg_Hash_Manager   $hash_manager   Hash manager instance.
	 * @param SimplyOrg_Trainer_Syncer $trainer_syncer Trainer syncer instance.
	 */
	public function __construct( SimplyOrg_API_Client $api_client, SimplyOrg_Hash_Manager $hash_manager, SimplyOrg_Trainer_Syncer $trainer_syncer ) {
		$this->api_client     = $api_client;
		$this->hash_manager   = $hash_manager;
		$this->trainer_syncer = $trainer_syncer;
	}

	/**
	 * Sync all events from SimplyOrg.
	 *
	 * Fetches events from the API and syncs them to WordPress.
	 *
	 * @since 1.0.0
	 * @param string   $start_date Optional start date (Y-m-d format).
	 * @param string   $end_date   Optional end date (Y-m-d format).
	 * @param int|null $limit      Optional limit on number of events to sync.
	 * @return array|WP_Error Sync results on success, WP_Error on failure.
	 */
	public function sync_events( $start_date = null, $end_date = null, $limit = null ) {
		$this->log( sprintf( 'Starting sync: start_date=%s, end_date=%s, limit=%s', $start_date, $end_date, $limit ? $limit : 'none' ) );

		// Fetch events from API.
		$this->log( 'Fetching events from API...' );
		$events = $this->api_client->fetch_calendar_events( $start_date, $end_date );

		if ( is_wp_error( $events ) ) {
			$this->log( 'API fetch failed: ' . $events->get_error_message() );
			return $events;
		}

		$this->log( sprintf( 'Fetched %d raw events from API', count( $events ) ) );

		// Filter and normalize events.
		$this->log( 'Normalizing and filtering events...' );
		$normalized_events = $this->normalize_events( $events );
		$this->log( sprintf( 'After normalization: %d events', count( $normalized_events ) ) );

		// Apply limit if specified (for manual sync to avoid timeouts).
		if ( null !== $limit && $limit > 0 ) {
			$this->log( sprintf( 'Applying limit of %d events for manual sync', $limit ) );
			$normalized_events = array_slice( $normalized_events, 0, $limit );
			$this->log( sprintf( 'Processing %d events after limit', count( $normalized_events ) ) );
		}

		// Sync results.
		$results = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		// Process each normalized event.
		$this->log( sprintf( 'Processing %d events...', count( $normalized_events ) ) );
		foreach ( $normalized_events as $index => $event_data ) {
			$this->log( sprintf( 'Event %d/%d: %s (ID: %s)', $index + 1, count( $normalized_events ), $event_data['title'], $event_data['simplyorg_id'] ) );
			$result = $this->sync_single_event( $event_data );

			if ( is_wp_error( $result ) ) {
				$error_msg = sprintf(
					/* translators: 1: Event title, 2: Error message */
					__( 'Failed to sync event "%1$s": %2$s', 'simplyorg-connector' ),
					$event_data['title'],
					$result->get_error_message()
				);
				$results['errors'][] = $error_msg;
				$this->log( 'ERROR: ' . $error_msg );
			} else {
				if ( 'created' === $result ) {
					$results['created']++;
					$this->log( 'Result: CREATED' );
				} elseif ( 'updated' === $result ) {
					$results['updated']++;
					$this->log( 'Result: UPDATED' );
				} else {
					$results['skipped']++;
					$this->log( 'Result: SKIPPED (no changes)' );
				}
			}
		}

		$this->log( sprintf( 'Sync complete: Created=%d, Updated=%d, Skipped=%d, Errors=%d', $results['created'], $results['updated'], $results['skipped'], count( $results['errors'] ) ) );
		return $results;
	}

	/**
	 * Normalize and group events.
	 *
	 * Groups multi-day events by event_id and cleans up data.
	 *
	 * @since 1.0.0
	 * @param array $events Raw events from API.
	 * @return array Normalized events grouped by event_id.
	 */
	private function normalize_events( $events ) {
		$event_groups = array();
		$skipped      = array(
			'no_data'      => 0,
			'einmietung'   => 0,
			'no_trainer'   => 0,
		);

		foreach ( $events as $event ) {
			// Skip events without necessary data.
			if ( empty( $event['event_id'] ) || empty( $event['title'] ) ) {
				$skipped['no_data']++;
				continue;
			}

			// Skip "Einmietung" (room rental) events.
			if ( isset( $event['event_category_name'] ) && 'Einmietung' === $event['event_category_name'] ) {
				$skipped['einmietung']++;
				continue;
			}

			// Skip events without trainer.
			if ( empty( $event['trainer_name'] ) ) {
				$skipped['no_trainer']++;
				continue;
			}

			$event_id = $event['event_id'];

			// Initialize event group if not exists.
			if ( ! isset( $event_groups[ $event_id ] ) ) {
				// Clean title by removing "Tag - X" suffix.
				$clean_title = preg_replace( '/ Tag - \d+$/', '', $event['title'] );

				// Handle multiple trainers (comma-separated).
				$trainer_names = array();
				$trainer_ids   = array();

				if ( ! empty( $event['trainer_name'] ) ) {
					// Split by comma and trim whitespace.
					$trainer_names = array_map( 'trim', explode( ',', $event['trainer_name'] ) );
				}

				// Extract trainer IDs from schedule_slot.
				if ( isset( $event['schedule_slot'][0]['trainer'] ) ) {
					$trainer_ids_str = $event['schedule_slot'][0]['trainer'];
					// Split by comma if multiple trainers.
					if ( strpos( $trainer_ids_str, ',' ) !== false ) {
						$trainer_ids = array_map( 'intval', explode( ',', $trainer_ids_str ) );
					} else {
						$trainer_ids = array( intval( $trainer_ids_str ) );
					}
				}

				$event_groups[ $event_id ] = array(
					'simplyorg_id'   => $event_id,
					'title'          => $clean_title,
					'event_name'     => isset( $event['event_name'] ) ? $event['event_name'] : '',
					'event_category' => isset( $event['event_category_name'] ) ? $event['event_category_name'] : '',
					'trainer_names'  => $trainer_names,
					'trainer_ids'    => $trainer_ids,
					'dates'          => array(),
				);
			}

			// Add date information.
			// Use schedule_date for the actual day (not event_startdate which is the overall range).
			$actual_date = isset( $event['schedule_date'] ) ? $event['schedule_date'] : $event['event_startdate'];

			// Detect if this is a module (part of "Ausbildungen" training course).
			$is_module = false;
			if ( isset( $event['event_category_name'] ) && 'Ausbildungen' === $event['event_category_name'] ) {
				$is_module = true;
			}
			// Also check if "Modul" is in the title.
			if ( isset( $event['event_name'] ) && stripos( $event['event_name'], 'Modul' ) !== false ) {
				$is_module = true;
			}

			$date_entry = array(
				'start_date' => $actual_date,
				'end_date'   => $actual_date, // Same day for individual schedule entries.
				'start_time' => '09:00:00',
				'end_time'   => '16:00:00',
				'day_number' => isset( $event['event_days'] ) ? intval( $event['event_days'] ) : 1,
				'is_module'  => $is_module,
			);

			// Extract time from schedule_slot.
			if ( isset( $event['schedule_slot'][0] ) ) {
				$slot = $event['schedule_slot'][0];
				if ( isset( $slot['start_time'] ) ) {
					// Remove microseconds from time.
					$date_entry['start_time'] = substr( $slot['start_time'], 0, 8 );
				}
				if ( isset( $slot['end_time'] ) ) {
					$date_entry['end_time'] = substr( $slot['end_time'], 0, 8 );
				}
			}

			$event_groups[ $event_id ]['dates'][] = $date_entry;
		}

		// Sort dates within each event group.
		foreach ( $event_groups as &$event_group ) {
			usort(
				$event_group['dates'],
				function ( $a, $b ) {
					return strcmp( $a['start_date'], $b['start_date'] );
				}
			);
		}

		$this->log( sprintf( 'Filtered events: Skipped %d (no data), %d (Einmietung), %d (no trainer)', $skipped['no_data'], $skipped['einmietung'], $skipped['no_trainer'] ) );
		$this->log( sprintf( 'Grouped into %d unique events', count( $event_groups ) ) );

		return array_values( $event_groups );
	}

	/**
	 * Sync a single event.
	 *
	 * Creates or updates a WordPress post for the event.
	 *
	 * @since 1.0.0
	 * @param array $event_data Normalized event data.
	 * @return string|WP_Error 'created', 'updated', or 'skipped' on success, WP_Error on failure.
	 */
	private function sync_single_event( $event_data ) {
		// Generate hash for change detection.
		$new_hash = $this->hash_manager->generate_event_hash( $event_data );

		// Check if event already exists.
		$existing_post_id = $this->hash_manager->find_post_by_simplyorg_id( $event_data['simplyorg_id'], 'seminar' );

		if ( $existing_post_id ) {
			// Check if content has changed.
			if ( ! $this->hash_manager->has_content_changed( $existing_post_id, $new_hash ) ) {
				return 'skipped';
			}

			// Update existing event.
			$result = $this->update_event( $existing_post_id, $event_data, $new_hash );
			return is_wp_error( $result ) ? $result : 'updated';
		}

		// Create new event.
		$result = $this->create_event( $event_data, $new_hash );
		return is_wp_error( $result ) ? $result : 'created';
	}

	/**
	 * Create new event post.
	 *
	 * @since 1.0.0
	 * @param array  $event_data Event data.
	 * @param string $hash       Content hash.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	private function create_event( $event_data, $hash ) {
		// Get configured post type.
		$settings        = get_option( 'simplyorg_connector_settings', array() );
		$event_post_type = isset( $settings['event_post_type'] ) ? $settings['event_post_type'] : 'seminar';

		// Create the post.
		$post_data = array(
			'post_title'  => sanitize_text_field( $event_data['title'] ),
			'post_type'   => $event_post_type,
			'post_status' => 'draft', // Create as draft for review.
			'post_author' => get_current_user_id(),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Update ACF fields.
		$this->update_event_fields( $post_id, $event_data );

		// Store hash.
		$this->hash_manager->update_post_hash( $post_id, $hash );

		return $post_id;
	}

	/**
	 * Update existing event post.
	 *
	 * @since 1.0.0
	 * @param int    $post_id    Post ID.
	 * @param array  $event_data Event data.
	 * @param string $hash       Content hash.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function update_event( $post_id, $event_data, $hash ) {
		// Update post title.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => sanitize_text_field( $event_data['title'] ),
			)
		);

		// Update ACF fields.
		$this->update_event_fields( $post_id, $event_data );

		// Update hash.
		$this->hash_manager->update_post_hash( $post_id, $hash );

		return true;
	}

	/**
	 * Update ACF fields for an event.
	 *
	 * @since 1.0.0
	 * @param int   $post_id    Post ID.
	 * @param array $event_data Event data.
	 */
	private function update_event_fields( $post_id, $event_data ) {
		// Update SimplyOrg ID.
		update_field( 'simplyorg_id', $event_data['simplyorg_id'], $post_id );

		// Update event category (seminar-typ).
		if ( ! empty( $event_data['event_category'] ) ) {
			update_field( 'seminar-typ', $event_data['event_category'], $post_id );
		}

		// Find or create trainers and link (supports multiple trainers).
		if ( ! empty( $event_data['trainer_ids'] ) && ! empty( $event_data['trainer_names'] ) ) {
			$trainer_post_ids = array();

			// Loop through each trainer.
			foreach ( $event_data['trainer_ids'] as $index => $trainer_id ) {
				$trainer_name = isset( $event_data['trainer_names'][ $index ] ) ? $event_data['trainer_names'][ $index ] : '';

				if ( ! empty( $trainer_name ) ) {
					$trainer_post_id = $this->trainer_syncer->find_or_create_trainer(
						$trainer_id,
						$trainer_name
					);

					if ( ! is_wp_error( $trainer_post_id ) ) {
						$trainer_post_ids[] = $trainer_post_id;
					}
				}
			}

			// Update trainer field with all trainer post IDs.
			if ( ! empty( $trainer_post_ids ) ) {
				update_field( 'trainer', $trainer_post_ids, $post_id );
			}
		}

		// Update dates (ACF repeater).
		if ( ! empty( $event_data['dates'] ) ) {
			$dates_data = array();

			foreach ( $event_data['dates'] as $date ) {
				// Combine date and time for ACF datetime field.
				$from_datetime = $date['start_date'] . ' ' . $date['start_time'];
				$bis_datetime  = '';

				// Set "bis" if end date exists (even for same-day events with different times).
				if ( ! empty( $date['end_date'] ) ) {
					$bis_datetime = $date['end_date'] . ' ' . $date['end_time'];
				}

				$dates_data[] = array(
					'from'       => $from_datetime,
					'bis'        => $bis_datetime,
					'hinweis'    => '',
					'modul'      => isset( $date['is_module'] ) ? $date['is_module'] : false,
					'modul_name' => '',
				);
			}

			update_field( 'dates', $dates_data, $post_id );
		}
	}

	/**
	 * Log message if debug mode is enabled.
	 *
	 * @since 1.0.7
	 * @param string $message Log message.
	 */
	private function log( $message ) {
		$settings = get_option( 'simplyorg_connector_settings', array() );
		$debug    = isset( $settings['debug_mode'] ) ? $settings['debug_mode'] : false;

		if ( $debug && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[SimplyOrg Event Syncer] ' . $message );
		}
	}
}

