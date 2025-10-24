<?php
/**
 * Trainer Syncer.
 *
 * Handles synchronization of trainers from SimplyOrg to WordPress.
 *
 * @package SimplyOrg_Connector
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs trainer data from SimplyOrg to WordPress trainer posts.
 *
 * Manages creation and updating of trainer custom post types.
 *
 * @since 1.0.0
 */
class SimplyOrg_Trainer_Syncer {

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param SimplyOrg_API_Client   $api_client   API client instance.
	 * @param SimplyOrg_Hash_Manager $hash_manager Hash manager instance.
	 */
	public function __construct( SimplyOrg_API_Client $api_client, SimplyOrg_Hash_Manager $hash_manager ) {
		$this->api_client   = $api_client;
		$this->hash_manager = $hash_manager;
	}

	/**
	 * Find or create trainer by name and ID.
	 *
	 * Searches for existing trainer by SimplyOrg ID or name, creates if not found.
	 *
	 * @since 1.0.0
	 * @param int    $simplyorg_trainer_id SimplyOrg trainer ID.
	 * @param string $trainer_name         Trainer full name.
	 * @return int|WP_Error WordPress post ID on success, WP_Error on failure.
	 */
	public function find_or_create_trainer( $simplyorg_trainer_id, $trainer_name ) {
		// First, try to find by SimplyOrg ID.
		$existing_post_id = $this->hash_manager->find_post_by_simplyorg_id( $simplyorg_trainer_id, 'trainer' );

		if ( $existing_post_id ) {
			return $existing_post_id;
		}

		// If not found by ID, try to find by name.
		$existing_post_id = $this->find_trainer_by_name( $trainer_name );

		if ( $existing_post_id ) {
			// Update the SimplyOrg ID for future lookups.
			update_field( 'simplyorg_id', $simplyorg_trainer_id, $existing_post_id );
			return $existing_post_id;
		}

		// Trainer doesn't exist, create new one.
		return $this->create_trainer( $simplyorg_trainer_id, $trainer_name );
	}

	/**
	 * Find trainer by name.
	 *
	 * Searches for a trainer post by exact name match.
	 *
	 * @since 1.0.0
	 * @param string $trainer_name Trainer full name.
	 * @return int|false Post ID if found, false otherwise.
	 */
	private function find_trainer_by_name( $trainer_name ) {
		// Get configured post type.
		$settings          = get_option( 'simplyorg_connector_settings', array() );
		$trainer_post_type = isset( $settings['trainer_post_type'] ) ? $settings['trainer_post_type'] : 'trainer';

		$args = array(
			'post_type'      => $trainer_post_type,
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'title'          => $trainer_name,
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return false;
	}

	/**
	 * Create new trainer post.
	 *
	 * Creates a new trainer custom post type with basic information.
	 *
	 * @since 1.0.0
	 * @param int    $simplyorg_trainer_id SimplyOrg trainer ID.
	 * @param string $trainer_name         Trainer full name.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	private function create_trainer( $simplyorg_trainer_id, $trainer_name ) {
		// Get configured post type.
		$settings          = get_option( 'simplyorg_connector_settings', array() );
		$trainer_post_type = isset( $settings['trainer_post_type'] ) ? $settings['trainer_post_type'] : 'trainer';

		$post_data = array(
			'post_title'  => sanitize_text_field( $trainer_name ),
			'post_type'   => $trainer_post_type,
			'post_status' => 'draft', // Create as draft for review.
			'post_author' => get_current_user_id(),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Update ACF fields.
		update_field( 'simplyorg_id', $simplyorg_trainer_id, $post_id );

		// Generate and store hash.
		$trainer_data = array(
			'simplyorg_id' => $simplyorg_trainer_id,
			'name'         => $trainer_name,
		);
		$hash = $this->hash_manager->generate_trainer_hash( $trainer_data );
		$this->hash_manager->update_post_hash( $post_id, $hash );

		return $post_id;
	}

	/**
	 * Update trainer post.
	 *
	 * Updates an existing trainer post with new data.
	 *
	 * @since 1.0.0
	 * @param int   $post_id      WordPress post ID.
	 * @param array $trainer_data Trainer data from SimplyOrg.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_trainer( $post_id, $trainer_data ) {
		// Generate new hash.
		$new_hash = $this->hash_manager->generate_trainer_hash( $trainer_data );

		// Check if content has changed.
		if ( ! $this->hash_manager->has_content_changed( $post_id, $new_hash ) ) {
			// No changes, skip update.
			return true;
		}

		// Update post title if name changed.
		if ( isset( $trainer_data['name'] ) ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => sanitize_text_field( $trainer_data['name'] ),
				)
			);
		}

		// Update ACF fields if provided.
		if ( isset( $trainer_data['email'] ) ) {
			update_field( 'e-mail', sanitize_email( $trainer_data['email'] ), $post_id );
		}

		if ( isset( $trainer_data['phone'] ) ) {
			update_field( 'phone', sanitize_text_field( $trainer_data['phone'] ), $post_id );
		}

		if ( isset( $trainer_data['mobile'] ) ) {
			update_field( 'mobile', sanitize_text_field( $trainer_data['mobile'] ), $post_id );
		}

		// Update hash.
		$this->hash_manager->update_post_hash( $post_id, $new_hash );

		return true;
	}

	/**
	 * Sync all trainers from event data.
	 *
	 * Extracts unique trainers from event data and creates/updates them.
	 *
	 * @since 1.0.0
	 * @param array $events Array of events from SimplyOrg API.
	 * @return array Array of results with 'created' and 'updated' counts.
	 */
	public function sync_trainers_from_events( $events ) {
		$results = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		// Extract unique trainers from events.
		$trainers = array();
		foreach ( $events as $event ) {
			// Skip events without trainer.
			if ( empty( $event['trainer_name'] ) ) {
				continue;
			}

			// Get trainer ID from schedule_slot.
			$trainer_id = null;
			if ( isset( $event['schedule_slot'][0]['trainer'] ) ) {
				$trainer_id = intval( $event['schedule_slot'][0]['trainer'] );
			}

			// Skip if no trainer ID.
			if ( empty( $trainer_id ) ) {
				continue;
			}

			// Add to unique trainers array.
			$trainers[ $trainer_id ] = $event['trainer_name'];
		}

		// Process each unique trainer.
		foreach ( $trainers as $trainer_id => $trainer_name ) {
			$result = $this->find_or_create_trainer( $trainer_id, $trainer_name );

			if ( is_wp_error( $result ) ) {
				$results['errors'][] = sprintf(
					/* translators: 1: Trainer name, 2: Error message */
					__( 'Failed to sync trainer "%1$s": %2$s', 'simplyorg-connector' ),
					$trainer_name,
					$result->get_error_message()
				);
			} else {
				// Check if it was newly created or existing.
				$existing_hash = $this->hash_manager->get_post_hash( $result );
				if ( empty( $existing_hash ) ) {
					$results['created']++;
				} else {
					$results['updated']++;
				}
			}
		}

		return $results;
	}
}

