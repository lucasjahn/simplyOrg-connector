<?php
/**
 * Admin Interface.
 *
 * Handles the WordPress admin interface for the plugin.
 *
 * @package SimplyOrg_Connector
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface for plugin settings and manual sync.
 *
 * Provides settings page and manual sync functionality.
 *
 * @since 1.0.0
 */
class SimplyOrg_Admin {

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
	 * Flag to prevent recursive validation.
	 *
	 * @var bool
	 */
	private $is_validating = false;

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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_simplyorg_manual_sync', array( $this, 'handle_manual_sync' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add admin menu page.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'SimplyOrg Connector', 'simplyorg-connector' ),
			__( 'SimplyOrg Sync', 'simplyorg-connector' ),
			'manage_options',
			'simplyorg-connector',
			array( $this, 'render_settings_page' ),
			'dashicons-update',
			80
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			'simplyorg_connector_settings',
			'simplyorg_connector_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// API Settings Section.
		add_settings_section(
			'simplyorg_api_settings',
			__( 'API Settings', 'simplyorg-connector' ),
			array( $this, 'render_api_settings_section' ),
			'simplyorg-connector'
		);

		add_settings_field(
			'api_base_url',
			__( 'API Base URL', 'simplyorg-connector' ),
			array( $this, 'render_text_field' ),
			'simplyorg-connector',
			'simplyorg_api_settings',
			array(
				'label_for'   => 'api_base_url',
				'placeholder' => 'https://firm-admin.simplyorg-seminare.de',
			)
		);

		add_settings_field(
			'api_email',
			__( 'API Email', 'simplyorg-connector' ),
			array( $this, 'render_text_field' ),
			'simplyorg-connector',
			'simplyorg_api_settings',
			array(
				'label_for'   => 'api_email',
				'placeholder' => 'your-email@example.com',
			)
		);

		add_settings_field(
			'api_password',
			__( 'API Password', 'simplyorg-connector' ),
			array( $this, 'render_password_field' ),
			'simplyorg-connector',
			'simplyorg_api_settings',
			array(
				'label_for' => 'api_password',
			)
		);

		// Sync Settings Section.
		add_settings_section(
			'simplyorg_sync_settings',
			__( 'Sync Settings', 'simplyorg-connector' ),
			array( $this, 'render_sync_settings_section' ),
			'simplyorg_connector'
		);

		add_settings_field(
			'sync_enabled',
			__( 'Enable Automatic Sync', 'simplyorg-connector' ),
			array( $this, 'render_sync_enabled_field' ),
			'simplyorg_connector',
			'simplyorg_sync_settings'
		);

		add_settings_field(
			'debug_mode',
			__( 'Debug Mode', 'simplyorg-connector' ),
			array( $this, 'render_debug_mode_field' ),
			'simplyorg_connector',
			'simplyorg_sync_settings'
		);

		add_settings_field(
			'event_post_type',
			__( 'Event Post Type', 'simplyorg-connector' ),
			array( $this, 'render_event_post_type_field' ),
			'simplyorg_connector',
			'simplyorg_sync_settings'
		);

		add_settings_field(
			'trainer_post_type',
			__( 'Trainer Post Type', 'simplyorg-connector' ),
			array( $this, 'render_trainer_post_type_field' ),
			'simplyorg_connector',
			'simplyorg_sync_settings'
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @since 1.0.0
	 * @param array $input Raw input values.
	 * @return array Sanitized values.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['api_base_url'] ) ) {
			$sanitized['api_base_url'] = esc_url_raw( $input['api_base_url'] );
		}

		if ( isset( $input['api_email'] ) ) {
			$sanitized['api_email'] = sanitize_email( $input['api_email'] );
		}

		if ( isset( $input['api_password'] ) ) {
			$sanitized['api_password'] = sanitize_text_field( $input['api_password'] );
		}

		$sanitized['sync_enabled'] = isset( $input['sync_enabled'] ) ? true : false;
		$sanitized['debug_mode']   = isset( $input['debug_mode'] ) ? true : false;

		if ( isset( $input['event_post_type'] ) ) {
			$sanitized['event_post_type'] = sanitize_key( $input['event_post_type'] );
		}

		if ( isset( $input['trainer_post_type'] ) ) {
			$sanitized['trainer_post_type'] = sanitize_key( $input['trainer_post_type'] );
		}

		// Validate credentials if all required fields are provided and not already validating.
		if ( ! $this->is_validating && ! empty( $sanitized['api_base_url'] ) && ! empty( $sanitized['api_email'] ) && ! empty( $sanitized['api_password'] ) ) {
			$this->validate_credentials( $sanitized );
		}

		return $sanitized;
	}

	/**
	 * Validate API credentials by attempting authentication.
	 *
	 * @since 1.0.0
	 * @param array $settings Settings to validate.
	 */
	private function validate_credentials( $settings ) {
		// Set flag to prevent recursive validation.
		$this->is_validating = true;

		// Temporarily update settings for validation.
		$old_settings = get_option( 'simplyorg_connector_settings', array() );
		update_option( 'simplyorg_connector_settings', $settings, false ); // Use autoload=false to prevent caching issues.

		// Create a new API client instance with the new settings.
		$api_client = new SimplyOrg_API_Client();
		$result = $api_client->authenticate();

		// Restore old settings temporarily (they'll be saved again by WordPress).
		update_option( 'simplyorg_connector_settings', $old_settings, false );

		// Reset validation flag.
		$this->is_validating = false;

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'simplyorg_connector_settings',
				'credential_validation_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Credential validation failed: %s', 'simplyorg-connector' ),
					$result->get_error_message()
				),
				'error'
			);
		} else {
			add_settings_error(
				'simplyorg_connector_settings',
				'credential_validation_success',
				__( '✓ Credentials validated successfully! Connection to SimplyOrg is working.', 'simplyorg-connector' ),
				'success'
			);
		}
	}

	/**
	 * Render API settings section description.
	 *
	 * @since 1.0.0
	 */
	public function render_api_settings_section() {
		echo '<p>' . esc_html__( 'Configure your SimplyOrg API credentials.', 'simplyorg-connector' ) . '</p>';
	}

	/**
	 * Render sync settings section description.
	 *
	 * @since 1.0.0
	 */
	public function render_sync_settings_section() {
		echo '<p>' . esc_html__( 'Configure automatic synchronization settings.', 'simplyorg-connector' ) . '</p>';
	}

	/**
	 * Render text input field.
	 *
	 * @since 1.0.0
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$settings    = get_option( 'simplyorg_connector_settings', array() );
		$field_id    = $args['label_for'];
		$value       = isset( $settings[ $field_id ] ) ? $settings[ $field_id ] : '';
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';

		printf(
			'<input type="text" id="%s" name="simplyorg_connector_settings[%s]" value="%s" placeholder="%s" class="regular-text" />',
			esc_attr( $field_id ),
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	/**
	 * Render password input field.
	 *
	 * @since 1.0.0
	 * @param array $args Field arguments.
	 */
	public function render_password_field( $args ) {
		$settings = get_option( 'simplyorg_connector_settings', array() );
		$field_id = $args['label_for'];
		$value    = isset( $settings[ $field_id ] ) ? $settings[ $field_id ] : '';

		printf(
			'<input type="password" id="%s" name="simplyorg_connector_settings[%s]" value="%s" class="regular-text" />',
			esc_attr( $field_id ),
			esc_attr( $field_id ),
			esc_attr( $value )
		);
	}

	/**
	 * Render sync enabled checkbox field.
	 *
	 * @since 1.0.0
	 */
	public function render_sync_enabled_field() {
		$settings = get_option( 'simplyorg_connector_settings', array() );
		$checked  = isset( $settings['sync_enabled'] ) ? $settings['sync_enabled'] : false;

		printf(
			'<label><input type="checkbox" id="sync_enabled" name="simplyorg_connector_settings[sync_enabled]" value="1" %s /> %s</label>',
			checked( $checked, true, false ),
			esc_html__( 'Enable daily automatic synchronization at 6:00 AM', 'simplyorg-connector' )
		);
	}

	/**
	 * Render debug mode checkbox field.
	 *
	 * @since 1.0.3
	 */
	public function render_debug_mode_field() {
		$settings = get_option( 'simplyorg_connector_settings', array() );
		$checked  = isset( $settings['debug_mode'] ) ? $settings['debug_mode'] : false;

		printf(
			'<label><input type="checkbox" id="debug_mode" name="simplyorg_connector_settings[debug_mode]" value="1" %s /> %s</label><p class="description">%s</p>',
			checked( $checked, true, false ),
			esc_html__( 'Enable debug logging', 'simplyorg-connector' ),
			esc_html__( 'When enabled, detailed API requests and responses will be logged to the WordPress debug log. Make sure WP_DEBUG_LOG is enabled in wp-config.php.', 'simplyorg-connector' )
		);
	}

	/**
	 * Render event post type select field.
	 *
	 * @since 1.0.5
	 */
	public function render_event_post_type_field() {
		$settings      = get_option( 'simplyorg_connector_settings', array() );
		$selected      = isset( $settings['event_post_type'] ) ? $settings['event_post_type'] : 'seminar';
		$post_types    = get_post_types( array( 'public' => true ), 'objects' );

		echo '<select id="event_post_type" name="simplyorg_connector_settings[event_post_type]">';
		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type->name, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
				continue;
			}
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $post_type->name ),
				selected( $selected, $post_type->name, false ),
				esc_html( $post_type->label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the post type to use for events/seminars. Default: seminar', 'simplyorg-connector' ) . '</p>';
	}

	/**
	 * Render trainer post type select field.
	 *
	 * @since 1.0.5
	 */
	public function render_trainer_post_type_field() {
		$settings      = get_option( 'simplyorg_connector_settings', array() );
		$selected      = isset( $settings['trainer_post_type'] ) ? $settings['trainer_post_type'] : 'trainer';
		$post_types    = get_post_types( array( 'public' => true ), 'objects' );

		echo '<select id="trainer_post_type" name="simplyorg_connector_settings[trainer_post_type]">';
		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type->name, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
				continue;
			}
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $post_type->name ),
				selected( $selected, $post_type->name, false ),
				esc_html( $post_type->label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the post type to use for trainers. Default: trainer', 'simplyorg-connector' ) . '</p>';
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for sync result message.
		$sync_message = get_transient( 'simplyorg_sync_message' );
		if ( $sync_message ) {
			delete_transient( 'simplyorg_sync_message' );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( $sync_message ) : ?>
				<div class="notice notice-<?php echo esc_attr( $sync_message['type'] ); ?> is-dismissible">
					<p><?php echo wp_kses_post( $sync_message['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'simplyorg_connector_settings' );
				do_settings_sections( 'simplyorg-connector' );
				submit_button( __( 'Save Settings', 'simplyorg-connector' ) );
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Manual Sync', 'simplyorg-connector' ); ?></h2>
			<p><?php esc_html_e( 'Click the button below to manually trigger a synchronization of events and trainers from SimplyOrg.', 'simplyorg-connector' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="simplyorg_manual_sync" />
				<?php wp_nonce_field( 'simplyorg_manual_sync', 'simplyorg_sync_nonce' ); ?>
				<?php submit_button( __( 'Sync Now', 'simplyorg-connector' ), 'primary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Sync Status', 'simplyorg-connector' ); ?></h2>
			<?php $this->render_sync_status(); ?>

			<hr />

			<h2><?php esc_html_e( 'Recent Sync Logs', 'simplyorg-connector' ); ?></h2>
			<?php $this->render_sync_logs(); ?>
		</div>
		<?php
	}

	/**
	 * Render sync status information.
	 *
	 * @since 1.0.0
	 */
	private function render_sync_status() {
		$next_scheduled = wp_next_scheduled( 'simplyorg_daily_sync' );
		$settings       = get_option( 'simplyorg_connector_settings', array() );
		$sync_enabled   = isset( $settings['sync_enabled'] ) ? $settings['sync_enabled'] : false;

		echo '<table class="widefat">';
		echo '<tbody>';

		// Automatic sync status.
		echo '<tr>';
		echo '<th style="width: 200px;">' . esc_html__( 'Automatic Sync', 'simplyorg-connector' ) . '</th>';
		echo '<td>';
		if ( $sync_enabled ) {
			echo '<span style="color: green;">●</span> ' . esc_html__( 'Enabled', 'simplyorg-connector' );
		} else {
			echo '<span style="color: red;">●</span> ' . esc_html__( 'Disabled', 'simplyorg-connector' );
		}
		echo '</td>';
		echo '</tr>';

		// Next scheduled sync.
		echo '<tr>';
		echo '<th>' . esc_html__( 'Next Scheduled Sync', 'simplyorg-connector' ) . '</th>';
		echo '<td>';
		if ( $next_scheduled ) {
			echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_scheduled ), 'Y-m-d H:i:s' ) );
		} else {
			echo esc_html__( 'Not scheduled', 'simplyorg-connector' );
		}
		echo '</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Render recent sync logs.
	 *
	 * @since 1.0.0
	 */
	private function render_sync_logs() {
		$logs = get_option( 'simplyorg_connector_logs', array() );
		$logs = array_slice( array_reverse( $logs ), 0, 10 );

		if ( empty( $logs ) ) {
			echo '<p>' . esc_html__( 'No sync logs available yet.', 'simplyorg-connector' ) . '</p>';
			return;
		}

		echo '<table class="widefat">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Timestamp', 'simplyorg-connector' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'simplyorg-connector' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $logs as $log ) {
			echo '<tr>';
			echo '<td>' . esc_html( $log['timestamp'] ) . '</td>';
			echo '<td>' . esc_html( $log['message'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Handle manual sync request.
	 *
	 * @since 1.0.0
	 */
	public function handle_manual_sync() {
		// Check permissions and nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simplyorg-connector' ) );
		}

		check_admin_referer( 'simplyorg_manual_sync', 'simplyorg_sync_nonce' );

		// Validate post types exist before syncing.
		$validation_error = $this->validate_post_types();
		if ( is_wp_error( $validation_error ) ) {
			set_transient(
				'simplyorg_sync_message',
				array(
					'type'    => 'error',
					'message' => $validation_error->get_error_message(),
				),
				30
			);
			wp_safe_redirect( admin_url( 'admin.php?page=simplyorg-connector' ) );
			exit;
		}

		// Run sync (limited to 10 events for manual sync to avoid timeouts).
		$current_year = gmdate( 'Y' );
		$next_year    = $current_year + 1;
		$start_date   = $current_year . '-01-01';
		$end_date     = $next_year . '-12-31';

		$results = $this->event_syncer->sync_events( $start_date, $end_date, 10 );

		// Prepare message.
		if ( is_wp_error( $results ) ) {
			$message = array(
				'type'    => 'error',
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Sync failed: %s', 'simplyorg-connector' ),
					$results->get_error_message()
				),
			);
		} else {
			$message = array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: 1: Created count, 2: Updated count, 3: Skipped count, 4: Error count */
					__( 'Manual sync completed (limited to 10 events to avoid timeouts)! Created: %1$d, Updated: %2$d, Skipped: %3$d, Errors: %4$d. Full sync runs automatically via cron.', 'simplyorg-connector' ),
					$results['created'],
					$results['updated'],
					$results['skipped'],
					count( $results['errors'] )
				),
			);

			// Add error details if any.
			if ( ! empty( $results['errors'] ) ) {
				$message['message'] .= '<br><br><strong>' . __( 'Errors:', 'simplyorg-connector' ) . '</strong><ul>';
				foreach ( $results['errors'] as $error ) {
					$message['message'] .= '<li>' . esc_html( $error ) . '</li>';
				}
				$message['message'] .= '</ul>';
			}
		}

		// Store message in transient.
		set_transient( 'simplyorg_sync_message', $message, 30 );

		// Redirect back to settings page.
		wp_safe_redirect( admin_url( 'admin.php?page=simplyorg-connector' ) );
		exit;
	}

	/**
	 * Validate that required post types exist.
	 *
	 * @since 1.0.5
	 * @return true|WP_Error True if valid, WP_Error if post types don't exist.
	 */
	private function validate_post_types() {
		$settings          = get_option( 'simplyorg_connector_settings', array() );
		$event_post_type   = isset( $settings['event_post_type'] ) ? $settings['event_post_type'] : 'seminar';
		$trainer_post_type = isset( $settings['trainer_post_type'] ) ? $settings['trainer_post_type'] : 'trainer';

		$missing_types = array();

		if ( ! post_type_exists( $event_post_type ) ) {
			$missing_types[] = $event_post_type;
		}

		if ( ! post_type_exists( $trainer_post_type ) ) {
			$missing_types[] = $trainer_post_type;
		}

		if ( ! empty( $missing_types ) ) {
			return new WP_Error(
				'post_types_missing',
				sprintf(
					/* translators: %s: Comma-separated list of missing post types */
					__( 'The following post types do not exist: %s. Please create them or select different post types in settings.', 'simplyorg-connector' ),
					implode( ', ', $missing_types )
				)
			);
		}

		return true;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our settings page.
		if ( 'toplevel_page_simplyorg-connector' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'simplyorg-connector-admin',
			SIMPLYORG_CONNECTOR_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			SIMPLYORG_CONNECTOR_VERSION
		);
	}
}

