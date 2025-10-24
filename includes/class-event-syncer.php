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
		// Fetch events from API.
		$events = $this->api_client->fetch_calendar_events( $start_date, $end_date );

		if ( is_wp_error( $events ) ) {
			return $events;
		}

		// Filter and normalize events.
		$normalized_events = $this->normalize_events( $events );

		// Apply limit if specified (for manual sync to avoid timeouts).
		if ( null !== $limit && $limit > 0 ) {
			$normalized_events = array_slice( $normalized_events, 0, $limit );
		}

		// Sync results.
		$results = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		// Process each normalized event.
		foreach ( $normalized_events as $event_data ) {
			$result = $this->sync_single_event( $event_data );

			if ( is_wp_error( $result ) ) {
				$results['errors'][] = sprintf(
					/* translators: 1: Event title, 2: Error message */
					__( 'Failed to sync event "%1$s": %2$s', 'simplyorg-connector' ),
					$event_data['title'],
					$result->get_error_message()
				);
			} else {
				if ( 'created' === $result ) {
					$results['created']++;
				} elseif ( 'updated' === $result ) {
					$results['updated']++;
				} else {
					$results['skipped']++;
				}
			}
		}

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

		foreach ( $events as $event ) {
			// Skip events without necessary data.
			if ( empty( $event['event_id'] ) || empty( $event['title'] ) ) {
				continue;
			}

			// Skip "Einmietung" (room rental) events.
			if ( isset( $event['event_category_name'] ) && 'Einmietung' === $event['event_category_name'] ) {
				continue;
			}

			// Skip events without trainer.
			if ( empty( $event['trainer_name'] ) ) {
				continue;
			}

			$event_id = $event['event_id'];

			// Initialize event group if not exists.
			if ( ! isset( $event_groups[ $event_id ] ) ) {
				// Clean title by removing "Tag - X" suffix.
				$clean_title = preg_replace( '/ Tag - \d+$/', '', $event['title'] );

				$event_groups[ $event_id ] = array(
					'simplyorg_id'   => $event_id,
					'title'          => $clean_title,
					'event_name'     => isset( $event['event_name'] ) ? $event['event_name'] : '',
					'event_category' => isset( $event['event_category_name'] ) ? $event['event_category_name'] : '',
					'trainer_name'   => $event['trainer_name'],
					'trainer_id'     => null,
					'dates'          => array(),
				);
			}

			// Extract trainer ID from schedule_slot.
			if ( isset( $event['schedule_slot'][0]['trainer'] ) ) {
				$event_groups[ $event_id ]['trainer_id'] = intval( $event['schedule_slot'][0]['trainer'] );
			}

			// Add date information.
			$date_entry = array(
				'start_date' => isset( $event['event_startdate'] ) ? $event['event_startdate'] : '',
				'end_date'   => isset( $event['event_enddate'] ) ? $event['event_enddate'] : '',
				'start_time' => '09:00:00',
				'end_time'   => '16:00:00',
				'day_number' => isset( $event['event_days'] ) ? intval( $event['event_days'] ) : 1,
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

		// Find or create trainer and link.
		if ( ! empty( $event_data['trainer_id'] ) && ! empty( $event_data['trainer_name'] ) ) {
			$trainer_post_id = $this->trainer_syncer->find_or_create_trainer(
				$event_data['trainer_id'],
				$event_data['trainer_name']
			);

			if ( ! is_wp_error( $trainer_post_id ) ) {
				// ACF expects an array of post IDs for post_object field with multiple enabled.
				update_field( 'trainer', array( $trainer_post_id ), $post_id );
			}
		}

		// Update dates (ACF repeater).
		if ( ! empty( $event_data['dates'] ) ) {
			$dates_data = array();

			foreach ( $event_data['dates'] as $date ) {
				// Combine date and time for ACF datetime field.
				$from_datetime = $date['start_date'] . ' ' . $date['start_time'];
				$bis_datetime  = '';

				// Only set "bis" if it's a different day or spans multiple days.
				if ( $date['end_date'] !== $date['start_date'] ) {
					$bis_datetime = $date['end_date'] . ' ' . $date['end_time'];
				}

				$dates_data[] = array(
					'from'       => $from_datetime,
					'bis'        => $bis_datetime,
					'hinweis'    => '',
					'modul'      => false,
					'modul_name' => '',
				);
			}

			update_field( 'dates', $dates_data, $post_id );
		}
	}
}

