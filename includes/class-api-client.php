<?php
/**
 * SimplyOrg API Client.
 *
 * Handles authentication and API requests to SimplyOrg platform.
 *
 * @package SimplyOrg_Connector
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API client for SimplyOrg platform.
 *
 * Manages authentication flow (CSRF token, login, cookies) and API requests.
 *
 * @since 1.0.0
 */
class SimplyOrg_API_Client {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * API email credential.
	 *
	 * @var string
	 */
	private $email;

	/**
	 * API password credential.
	 *
	 * @var string
	 */
	private $password;

	/**
	 * Authentication cookies.
	 *
	 * @var string
	 */
	private $cookies = '';

	/**
	 * XSRF token for authenticated requests.
	 *
	 * @var string
	 */
	private $xsrf_token = '';

	/**
	 * Whether the client is authenticated.
	 *
	 * @var bool
	 */
	private $is_authenticated = false;

	/**
	 * Constructor.
	 *
	 * Loads API credentials from WordPress options.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$settings       = get_option( 'simplyorg_connector_settings', array() );
		$this->base_url = isset( $settings['api_base_url'] ) ? trailingslashit( $settings['api_base_url'] ) : '';
		$this->email    = isset( $settings['api_email'] ) ? $settings['api_email'] : '';
		$this->password = isset( $settings['api_password'] ) ? $settings['api_password'] : '';
	}

	/**
	 * Authenticate with SimplyOrg API.
	 *
	 * Performs the full authentication flow:
	 * 1. GET request to obtain CSRF token
	 * 2. POST login with credentials
	 * 3. Store cookies and XSRF token for subsequent requests
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function authenticate() {
		// Check if credentials are configured.
		if ( empty( $this->base_url ) || empty( $this->email ) || empty( $this->password ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'API credentials are not configured. Please configure them in the settings page.', 'simplyorg-connector' )
			);
		}

		$this->debug_log( 'Starting authentication' );

		// Step 1: Get CSRF token from the login page.
		$initial_response = wp_remote_get(
			$this->base_url . 'de',
			array(
				'timeout' => 30,
			)
		);

		$this->debug_log( 'Initial request completed', array(
			'status_code' => wp_remote_retrieve_response_code( $initial_response ),
		) );

		if ( is_wp_error( $initial_response ) ) {
			return new WP_Error(
				'auth_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to connect to SimplyOrg: %s', 'simplyorg-connector' ),
					$initial_response->get_error_message()
				)
			);
		}

		$body = wp_remote_retrieve_body( $initial_response );

		// Extract CSRF token from meta tag.
		preg_match( '/<meta name="csrf-token" content="([^"]+)"/', $body, $matches );
		if ( empty( $matches[1] ) ) {
			return new WP_Error(
				'csrf_token_missing',
				__( 'Failed to extract CSRF token from SimplyOrg login page.', 'simplyorg-connector' )
			);
		}
		$csrf_token = $matches[1];

		// Extract cookies from response headers (raw format).
		$response_headers = wp_remote_retrieve_headers( $initial_response );
		$set_cookies = isset( $response_headers['set-cookie'] ) ? $response_headers['set-cookie'] : array();
		
		if ( empty( $set_cookies ) ) {
			return new WP_Error(
				'cookies_missing',
				__( 'Failed to retrieve cookies from SimplyOrg.', 'simplyorg-connector' )
			);
		}

		// Ensure set_cookies is an array.
		if ( ! is_array( $set_cookies ) ) {
			$set_cookies = array( $set_cookies );
		}

		// Build cookie string - join all cookies with '; '.
		$initial_cookie_string = implode( '; ', $set_cookies );
		
		// Extract XSRF token from first cookie (as per N8N workflow).
		// Format: "XSRF-TOKEN=value; path=/; ...".
		if ( ! empty( $set_cookies[0] ) ) {
			$first_cookie_parts = explode( ';', $set_cookies[0] );
			if ( ! empty( $first_cookie_parts[0] ) ) {
				$cookie_pair = explode( '=', $first_cookie_parts[0], 2 );
				if ( count( $cookie_pair ) === 2 ) {
					$this->xsrf_token = $cookie_pair[1];
				}
			}
		}

		// Step 2: Perform login.
		$login_response = wp_remote_post(
			$this->base_url . 'de/login',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Accept'        => 'application/json, text/javascript, */*; q=0.01',
					'X-CSRF-Token'  => $this->xsrf_token,
					'Cookie'        => $initial_cookie_string,
				),
				'body'    => array(
					'_token'   => $csrf_token,
					'email'    => $this->email,
					'password' => $this->password,
				),
			)
		);

		if ( is_wp_error( $login_response ) ) {
			return new WP_Error(
				'login_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Login to SimplyOrg failed: %s', 'simplyorg-connector' ),
					$login_response->get_error_message()
				)
			);
		}

		// Check login status - SimplyOrg returns 204 (No Content) on successful login.
		$login_status = wp_remote_retrieve_response_code( $login_response );
		if ( 204 !== $login_status && 200 !== $login_status ) {
			return new WP_Error(
				'login_invalid',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Login to SimplyOrg failed with status code: %d. Please check your credentials.', 'simplyorg-connector' ),
					$login_status
				)
			);
		}

		// Step 3: Extract authentication cookies from response headers.
		$login_headers = wp_remote_retrieve_headers( $login_response );
		$auth_set_cookies = isset( $login_headers['set-cookie'] ) ? $login_headers['set-cookie'] : array();
		
		if ( empty( $auth_set_cookies ) ) {
			return new WP_Error(
				'auth_cookies_missing',
				__( 'Failed to retrieve authentication cookies after login.', 'simplyorg-connector' )
			);
		}

		// Ensure auth_set_cookies is an array.
		if ( ! is_array( $auth_set_cookies ) ) {
			$auth_set_cookies = array( $auth_set_cookies );
		}

		// Build authenticated cookie string - join all cookies with '; '.
		$this->cookies = implode( '; ', $auth_set_cookies );
		
		// Extract XSRF token from first cookie.
		if ( ! empty( $auth_set_cookies[0] ) ) {
			$first_cookie_parts = explode( ';', $auth_set_cookies[0] );
			if ( ! empty( $first_cookie_parts[0] ) ) {
				$cookie_pair = explode( '=', $first_cookie_parts[0], 2 );
				if ( count( $cookie_pair ) === 2 ) {
					$this->xsrf_token = $cookie_pair[1];
				}
			}
		}

		$this->is_authenticated = true;

		$this->debug_log( 'Authentication successful' );

		return true;
	}

	/**
	 * Check if client is authenticated.
	 *
	 * @since 1.0.0
	 * @return bool True if authenticated, false otherwise.
	 */
	public function is_authenticated() {
		return $this->is_authenticated;
	}

	/**
	 * Fetch calendar events from SimplyOrg API.
	 *
	 * Retrieves all events within the specified date range.
	 *
	 * @since 1.0.0
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @return array|WP_Error Array of events on success, WP_Error on failure.
	 */
	public function fetch_calendar_events( $start_date = null, $end_date = null ) {
		// Ensure authentication.
		if ( ! $this->is_authenticated ) {
			$auth_result = $this->authenticate();
			if ( is_wp_error( $auth_result ) ) {
				return $auth_result;
			}
		}

		// Default date range: current year.
		if ( null === $start_date ) {
			$start_date = gmdate( 'Y' ) . '-01-01';
		}
		if ( null === $end_date ) {
			$end_date = gmdate( 'Y' ) . '-12-31';
		}

		// Prepare request body - match N8N exactly with string literals.
		$body = array(
			'event_id'          => 'null',
			'location_id'       => 'null',
			'event_category_id' => 'null',
			'project_support'   => 'undefined',
			'planned_by'        => 'undefined',
			'serminar_manager'  => 'undefined',
			'contact_person'    => 'undefined',
			'trainer_id'        => 'null',
			'status'            => 'null',
			'viewType'          => 'month',
			'start'             => $start_date,
			'end'               => $end_date,
		);

		// Make API request (POST with JSON body).
		$response = wp_remote_post(
			$this->base_url . 'de/event-calendar/calendar/fetchdata',
			array(
				'timeout' => 60,
				'headers' => array(
					'Cookie'        => $this->cookies,
					'X-CSRF-Token'  => $this->xsrf_token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		// Debug logging if enabled.
		$this->debug_log( 'Calendar Events Request', array(
			'url'     => $this->base_url . 'de/event-calendar/calendar/fetchdata',
			'body'    => $body,
			'cookies' => substr( $this->cookies, 0, 100 ) . '...', // Truncate for security.
		) );

		if ( is_wp_error( $response ) ) {
			$this->debug_log( 'API request failed (WP_Error)', array(
				'error' => $response->get_error_message(),
			) );
			return new WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to fetch calendar events: %s', 'simplyorg-connector' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$this->debug_log( 'API response received', array(
			'status_code' => $status_code,
			'headers'     => wp_remote_retrieve_headers( $response )->getAll(),
		) );

		if ( 200 !== $status_code ) {
			$response_body = wp_remote_retrieve_body( $response );
			$this->debug_log( 'API request invalid status', array(
				'status_code'   => $status_code,
				'response_body' => substr( $response_body, 0, 500 ),
			) );
			return new WP_Error(
				'api_request_invalid',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Failed to fetch calendar events. Status code: %d', 'simplyorg-connector' ),
					$status_code
				)
			);
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'json_decode_failed',
				__( 'Failed to decode API response.', 'simplyorg-connector' )
			);
		}

		// Return the body array which contains the events.
		return isset( $data['body'] ) ? $data['body'] : array();
	}

	/**
	 * Get trainer information by ID.
	 *
	 * Note: This is a placeholder. SimplyOrg may have a dedicated trainer endpoint.
	 * For now, we extract trainer info from event data.
	 *
	 * @since 1.0.0
	 * @param int $trainer_id Trainer ID from SimplyOrg.
	 * @return array|WP_Error Trainer data on success, WP_Error on failure.
	 */
	public function fetch_trainer( $trainer_id ) {
		// This would require a dedicated trainer API endpoint.
		// For now, trainers are extracted from event data.
		return new WP_Error(
			'not_implemented',
			__( 'Direct trainer fetching is not yet implemented. Trainers are synced from event data.', 'simplyorg-connector' )
		);
	}

	/**
	 * Debug log helper.
	 *
	 * Logs debug information if debug mode is enabled in settings.
	 *
	 * @since 1.0.3
	 * @param string $message Log message.
	 * @param array  $data    Optional data to log.
	 */
	private function debug_log( $message, $data = array() ) {
		$settings = get_option( 'simplyorg_connector_settings', array() );
		if ( empty( $settings['debug_mode'] ) ) {
			return;
		}

		$log_message = '[SimplyOrg API] ' . $message;
		if ( ! empty( $data ) ) {
			$log_message .= ' | Data: ' . wp_json_encode( $data );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $log_message );
	}
}

