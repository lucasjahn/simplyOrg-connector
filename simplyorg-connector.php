<?php
/**
 * Plugin Name: SimplyOrg Connector
 * Plugin URI: https://github.com/lucasjahn/simplyOrg-connector
 * Description: Syncs events and trainers from SimplyOrg event management platform to WordPress custom post types with ACF fields.
 * Version: 1.0.7
 * Author: Lucas Jahn
 * Author URI: https://krautnerds.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simplyorg-connector
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package SimplyOrg_Connector
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'SIMPLYORG_CONNECTOR_VERSION', '1.0.7' );
define( 'SIMPLYORG_CONNECTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLYORG_CONNECTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLYORG_CONNECTOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class loader.
 *
 * Handles the initialization of the plugin and loading of all required classes.
 *
 * @since 1.0.0
 */
final class SimplyOrg_Connector_Loader {

	/**
	 * The single instance of the class.
	 *
	 * @var SimplyOrg_Connector_Loader|null
	 */
	private static $instance = null;

	/**
	 * Main plugin instance.
	 *
	 * @var SimplyOrg_Connector|null
	 */
	private $plugin = null;

	/**
	 * Get the singleton instance.
	 *
	 * Ensures only one instance of the plugin is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @return SimplyOrg_Connector_Loader
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
	 * Initializes the plugin by setting up autoloading and hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required dependencies.
	 *
	 * Includes all necessary class files for the plugin to function.
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		require_once SIMPLYORG_CONNECTOR_PLUGIN_DIR . 'includes/class-simplyorg-connector.php';
		require_once SIMPLYORG_CONNECTOR_PLUGIN_DIR . 'includes/class-api-client.php';
		require_once SIMPLYORG_CONNECTOR_PLUGIN_DIR . 'includes/class-hash-manager.php';
		require_once SIMPLYORG_CONNECTOR_PLUGIN_DIR . 'includes/class-trainer-syncer.php';
		require_once SIMPLYORG_CONNECTOR_PLUGIN_DIR . 'includes/class-event-syncer.php';
		require_once SIMPLYORG_CONNECTOR_PLUGIN_DIR . 'includes/class-admin.php';
		require_once SIMPLYORG_CONNECTOR_PLUGIN_DIR . 'includes/class-cron.php';
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * Sets up activation, deactivation, and plugin initialization hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize the plugin.
	 *
	 * Runs after WordPress has loaded all plugins.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Check for required dependencies.
		if ( ! $this->check_dependencies() ) {
			add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
			return;
		}

		// Initialize the main plugin class.
		$this->plugin = SimplyOrg_Connector::instance();
	}

	/**
	 * Check for required dependencies.
	 *
	 * Verifies that ACF Pro is installed and active.
	 *
	 * @since 1.0.0
	 * @return bool True if all dependencies are met, false otherwise.
	 */
	private function check_dependencies() {
		// Check if ACF Pro is active.
		if ( ! function_exists( 'acf' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Display admin notice for missing dependencies.
	 *
	 * @since 1.0.0
	 */
	public function dependency_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				esc_html_e(
					'SimplyOrg Connector requires Advanced Custom Fields (ACF) Pro to be installed and activated.',
					'simplyorg-connector'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin activation hook.
	 *
	 * Runs when the plugin is activated.
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Schedule cron job.
		if ( ! wp_next_scheduled( 'simplyorg_daily_sync' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 6:00 AM' ), 'daily', 'simplyorg_daily_sync' );
		}

		// Set default options.
		$default_options = array(
			'api_base_url' => 'https://firm-admin.simplyorg-seminare.de',
			'api_email'    => '',
			'api_password' => '',
			'sync_enabled' => false,
		);

		if ( ! get_option( 'simplyorg_connector_settings' ) ) {
			add_option( 'simplyorg_connector_settings', $default_options );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * Runs when the plugin is deactivated.
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		// Clear scheduled cron job.
		$timestamp = wp_next_scheduled( 'simplyorg_daily_sync' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'simplyorg_daily_sync' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Get the main plugin instance.
	 *
	 * @since 1.0.0
	 * @return SimplyOrg_Connector|null
	 */
	public function get_plugin() {
		return $this->plugin;
	}
}

/**
 * Returns the main instance of SimplyOrg_Connector_Loader.
 *
 * @since 1.0.0
 * @return SimplyOrg_Connector_Loader
 */
function simplyorg_connector() {
	return SimplyOrg_Connector_Loader::instance();
}

// Initialize the plugin.
simplyorg_connector();

