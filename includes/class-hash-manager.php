<?php
/**
 * Hash Manager for change detection.
 *
 * Generates and compares hashes to detect changes in synced data.
 *
 * @package SimplyOrg_Connector
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages hash generation and comparison for sync operations.
 *
 * Uses hashing to efficiently detect changes in event and trainer data
 * without comparing all fields individually.
 *
 * @since 1.0.0
 */
class SimplyOrg_Hash_Manager {

	/**
	 * Meta key for storing content hash.
	 *
	 * @var string
	 */
	const HASH_META_KEY = '_simplyorg_content_hash';

	/**
	 * Generate hash for event data.
	 *
	 * Creates a hash from relevant event fields to detect changes.
	 *
	 * @since 1.0.0
	 * @param array $event_data Normalized event data array.
	 * @return string MD5 hash of the event data.
	 */
	public function generate_event_hash( $event_data ) {
		// Extract only the fields that matter for change detection.
		$relevant_data = array(
			'simplyorg_id'    => isset( $event_data['simplyorg_id'] ) ? $event_data['simplyorg_id'] : '',
			'title'           => isset( $event_data['title'] ) ? $event_data['title'] : '',
			'trainer_name'    => isset( $event_data['trainer_name'] ) ? $event_data['trainer_name'] : '',
			'event_category'  => isset( $event_data['event_category'] ) ? $event_data['event_category'] : '',
			'dates'           => isset( $event_data['dates'] ) ? $event_data['dates'] : array(),
		);

		// Serialize and hash the data.
		$serialized = wp_json_encode( $relevant_data );
		return md5( $serialized );
	}

	/**
	 * Generate hash for trainer data.
	 *
	 * Creates a hash from relevant trainer fields to detect changes.
	 *
	 * @since 1.0.0
	 * @param array $trainer_data Trainer data array.
	 * @return string MD5 hash of the trainer data.
	 */
	public function generate_trainer_hash( $trainer_data ) {
		// Extract only the fields that matter for change detection.
		$relevant_data = array(
			'simplyorg_id' => isset( $trainer_data['simplyorg_id'] ) ? $trainer_data['simplyorg_id'] : '',
			'name'         => isset( $trainer_data['name'] ) ? $trainer_data['name'] : '',
			'email'        => isset( $trainer_data['email'] ) ? $trainer_data['email'] : '',
		);

		// Serialize and hash the data.
		$serialized = wp_json_encode( $relevant_data );
		return md5( $serialized );
	}

	/**
	 * Get stored hash for a post.
	 *
	 * Retrieves the previously stored content hash from post meta.
	 *
	 * @since 1.0.0
	 * @param int $post_id WordPress post ID.
	 * @return string|false Stored hash or false if not found.
	 */
	public function get_post_hash( $post_id ) {
		return get_post_meta( $post_id, self::HASH_META_KEY, true );
	}

	/**
	 * Update stored hash for a post.
	 *
	 * Saves the content hash to post meta for future comparison.
	 *
	 * @since 1.0.0
	 * @param int    $post_id WordPress post ID.
	 * @param string $hash    Content hash to store.
	 * @return bool True on success, false on failure.
	 */
	public function update_post_hash( $post_id, $hash ) {
		return update_post_meta( $post_id, self::HASH_META_KEY, $hash );
	}

	/**
	 * Check if content has changed.
	 *
	 * Compares the new hash with the stored hash to detect changes.
	 *
	 * @since 1.0.0
	 * @param int    $post_id  WordPress post ID.
	 * @param string $new_hash New content hash.
	 * @return bool True if content has changed, false if unchanged.
	 */
	public function has_content_changed( $post_id, $new_hash ) {
		$stored_hash = $this->get_post_hash( $post_id );

		// If no hash exists, consider it changed (new post).
		if ( empty( $stored_hash ) ) {
			return true;
		}

		// Compare hashes.
		return $stored_hash !== $new_hash;
	}

	/**
	 * Find post by SimplyOrg ID.
	 *
	 * Searches for a WordPress post with the given SimplyOrg ID.
	 *
	 * @since 1.0.0
	 * @param int    $simplyorg_id SimplyOrg entity ID.
	 * @param string $post_type    WordPress post type to search.
	 * @return int|false Post ID if found, false otherwise.
	 */
	public function find_post_by_simplyorg_id( $simplyorg_id, $post_type = 'seminar' ) {
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'meta_query'     => array(
				array(
					'key'     => 'simplyorg_id',
					'value'   => $simplyorg_id,
					'compare' => '=',
				),
			),
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return false;
	}
}

