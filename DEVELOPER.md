# Developer Documentation

This document provides technical information for developers who want to understand, extend, or contribute to the SimplyOrg Connector plugin.

## Architecture Overview

The plugin follows a modern object-oriented architecture with clear separation of concerns. Each major functionality is encapsulated in its own class, making the codebase maintainable, testable, and extensible.

### Design Patterns

The plugin employs several design patterns to ensure code quality and maintainability. The **Singleton pattern** is used for main classes to ensure only one instance exists throughout the WordPress lifecycle. This prevents duplicate API connections and ensures consistent state management. The **Dependency Injection pattern** is implemented through constructor injection, making classes more testable and reducing tight coupling between components.

### Class Hierarchy

The plugin's class structure forms a clear hierarchy with well-defined responsibilities. At the top level, `SimplyOrg_Connector_Loader` serves as the bootstrap class, handling plugin activation, deactivation, and initialization. It loads all required files and creates the main `SimplyOrg_Connector` instance.

The `SimplyOrg_Connector` class acts as the central coordinator, instantiating and managing all other components. It provides getter methods for accessing individual components and ensures proper initialization order.

### Core Components

**API Client** (`SimplyOrg_API_Client`) manages all communication with the SimplyOrg platform. It handles the complete authentication flow, including CSRF token extraction, login with credentials, and cookie management. The client maintains authentication state and automatically re-authenticates if needed. All API requests are made through this class, providing a centralized point for error handling and logging.

**Hash Manager** (`SimplyOrg_Hash_Manager`) implements the change detection mechanism. It generates MD5 hashes from normalized event and trainer data, stores these hashes in post meta, and compares them on subsequent syncs. This approach significantly reduces database operations by skipping updates for unchanged content.

**Trainer Syncer** (`SimplyOrg_Trainer_Syncer`) handles all trainer-related synchronization. It extracts unique trainers from event data, searches for existing trainer posts by SimplyOrg ID or name, and creates new trainer posts when needed. The syncer ensures trainers are properly linked to events through ACF relationship fields.

**Event Syncer** (`SimplyOrg_Event_Syncer`) manages event synchronization with sophisticated grouping logic. It normalizes raw API data, groups multi-day events by event_id, filters unwanted events, and creates or updates WordPress posts. The syncer coordinates with the trainer syncer to ensure proper trainer linkage.

**Admin Interface** (`SimplyOrg_Admin`) provides the WordPress admin UI. It registers settings pages, handles form submissions, processes manual sync requests, and displays sync status and logs. The admin class uses WordPress Settings API for proper integration with WordPress core.

**Cron Manager** (`SimplyOrg_Cron`) handles scheduled synchronization tasks. It registers cron hooks, executes daily sync operations, logs sync results, and manages log retention. The cron manager ensures automatic synchronization runs reliably without manual intervention.

## Data Flow

Understanding the data flow through the plugin helps in debugging and extending functionality.

### Sync Process Flow

When a sync operation is triggered (either manually or via cron), the following sequence occurs:

1. The event syncer requests calendar events from the API client for a specified date range
2. The API client authenticates with SimplyOrg if not already authenticated
3. Raw event data is fetched and returned to the event syncer
4. The event syncer normalizes and groups the raw data, filtering out unwanted events
5. For each normalized event, the syncer generates a content hash
6. The syncer checks if a WordPress post exists with the same SimplyOrg ID
7. If the post exists, the syncer compares the new hash with the stored hash
8. If hashes differ or no post exists, the syncer creates or updates the post
9. During post creation/update, the trainer syncer is called to find or create trainer posts
10. ACF fields are updated with the normalized data
11. The new hash is stored in post meta for future comparison
12. Results are logged and returned to the caller

### Authentication Flow

The SimplyOrg API uses a multi-step authentication process that the plugin handles automatically:

1. A GET request is made to the SimplyOrg base URL to retrieve the login page
2. The CSRF token is extracted from a meta tag in the HTML response
3. Cookies from the initial response are captured and stored
4. A POST request is made to the login endpoint with the CSRF token, email, and password
5. Authentication cookies are extracted from the login response
6. The XSRF token is extracted from the authentication cookies
7. Subsequent API requests include both the cookies and XSRF token in headers
8. The authentication state is maintained for the duration of the sync operation

## Extending the Plugin

The plugin is designed to be extensible. Here are common extension points and how to use them.

### Adding New Synced Fields

To sync additional fields from SimplyOrg to WordPress, you need to modify the event syncer's field mapping logic. In the `update_event_fields` method of `SimplyOrg_Event_Syncer`, add new field mappings after the existing ones. Ensure the corresponding ACF fields exist in your field group configuration.

For example, to sync event descriptions:

```php
if ( ! empty( $event_data['description'] ) ) {
    update_field( 'event_description', $event_data['description'], $post_id );
}
```

Remember to also include the new field in the hash generation logic in `SimplyOrg_Hash_Manager::generate_event_hash()` if changes to this field should trigger updates.

### Custom Sync Schedules

The plugin uses WordPress cron to schedule daily syncs at 6:00 AM. To add custom schedules, you can register additional cron intervals and schedule new events.

In the main plugin file or a custom extension, add:

```php
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['twice_daily'] = array(
        'interval' => 12 * HOUR_IN_SECONDS,
        'display'  => __( 'Twice Daily', 'simplyorg-connector' ),
    );
    return $schedules;
} );

// Schedule the event
if ( ! wp_next_scheduled( 'simplyorg_twice_daily_sync' ) ) {
    wp_schedule_event( time(), 'twice_daily', 'simplyorg_twice_daily_sync' );
}

// Hook into the event
add_action( 'simplyorg_twice_daily_sync', function() {
    $connector = simplyorg_connector()->get_plugin();
    $connector->get_cron()->run_daily_sync();
} );
```

### Adding WP-CLI Commands

To add WP-CLI support for command-line sync operations, create a new CLI class:

```php
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    class SimplyOrg_CLI_Commands {
        /**
         * Sync events from SimplyOrg.
         *
         * ## OPTIONS
         *
         * [--start=<date>]
         * : Start date (Y-m-d format)
         *
         * [--end=<date>]
         * : End date (Y-m-d format)
         *
         * ## EXAMPLES
         *
         *     wp simplyorg sync --start=2025-01-01 --end=2025-12-31
         */
        public function sync( $args, $assoc_args ) {
            $start = isset( $assoc_args['start'] ) ? $assoc_args['start'] : null;
            $end   = isset( $assoc_args['end'] ) ? $assoc_args['end'] : null;
            
            $connector = simplyorg_connector()->get_plugin();
            $results = $connector->get_event_syncer()->sync_events( $start, $end );
            
            if ( is_wp_error( $results ) ) {
                WP_CLI::error( $results->get_error_message() );
            }
            
            WP_CLI::success( sprintf(
                'Sync completed. Created: %d, Updated: %d, Skipped: %d',
                $results['created'],
                $results['updated'],
                $results['skipped']
            ) );
        }
    }
    
    WP_CLI::add_command( 'simplyorg', 'SimplyOrg_CLI_Commands' );
}
```

### Custom Filters and Actions

The plugin can be extended with WordPress hooks. While the current version doesn't include many hooks, you can add them to key points in the code for extensibility.

Recommended hook locations:

- Before and after sync operations
- Before and after event creation/update
- Before and after trainer creation/update
- On authentication success/failure
- On API request success/failure

Example implementation:

```php
// In class-event-syncer.php, before creating an event:
$event_data = apply_filters( 'simplyorg_before_create_event', $event_data );

// After creating an event:
do_action( 'simplyorg_after_create_event', $post_id, $event_data );
```

## Testing

While the plugin doesn't currently include automated tests, here's how to manually test functionality.

### Testing Authentication

To verify authentication works correctly, enable WordPress debug mode and check the error log:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Trigger a manual sync and check `/wp-content/debug.log` for authentication-related messages.

### Testing Event Grouping

To test multi-day event grouping, create test events in SimplyOrg with the same event_id but different event_days values. After syncing, verify that only one WordPress post is created with multiple entries in the dates repeater field.

### Testing Hash Comparison

To verify hash-based change detection works:

1. Perform an initial sync
2. Note the number of events created
3. Perform a second sync without changing anything in SimplyOrg
4. Verify that all events are skipped (no updates)
5. Modify an event in SimplyOrg (change title or date)
6. Perform a third sync
7. Verify that only the modified event is updated

### Testing Trainer Auto-Creation

To test trainer auto-creation:

1. Delete all trainer posts in WordPress
2. Perform a sync
3. Verify that trainer posts are automatically created
4. Check that trainers are properly linked to events

## Code Standards

The plugin follows WordPress coding standards and PHP best practices.

### Naming Conventions

All classes use the `SimplyOrg_` prefix to avoid naming conflicts. Class names use PascalCase with underscores separating words. Method and property names use snake_case. Constants use UPPERCASE_WITH_UNDERSCORES.

### Documentation Standards

Every class, method, and property includes a DocBlock comment. DocBlocks follow the PHPDoc standard and include descriptions, parameter types, return types, and since tags. Complex logic includes inline comments explaining the reasoning.

### Security Practices

All user input is sanitized using WordPress sanitization functions. All output is escaped using WordPress escaping functions. Nonces are used for form submissions. Capability checks are performed before sensitive operations. Database queries use prepared statements or WordPress query APIs.

### Error Handling

The plugin uses `WP_Error` objects for error handling throughout. Errors are logged to the WordPress error log when debug mode is enabled. User-facing errors are displayed as admin notices. API errors include the original error message for debugging.

## Performance Considerations

The plugin is designed for performance and efficiency.

### Hash-Based Change Detection

Instead of comparing all fields individually, the plugin generates a single hash from relevant data. This approach significantly reduces the number of database queries and comparison operations needed during sync.

### Batch Processing

Events are fetched in a single API request and processed in memory. This reduces API calls and improves sync speed compared to fetching events individually.

### Selective Updates

Only events with changed content are updated in the database. Unchanged events are skipped entirely, reducing database write operations and improving performance.

### Query Optimization

Post lookups by SimplyOrg ID use meta queries with proper indexing. The plugin limits the number of posts returned when searching for existing posts. Field updates use ACF's `update_field()` function, which handles serialization efficiently.

## Troubleshooting Common Issues

### Authentication Failures

If authentication fails, verify that the API credentials are correct by logging into SimplyOrg manually. Check that your WordPress server can make outbound HTTPS requests. Ensure cookies are being properly extracted from responses. Review the error log for specific authentication error messages.

### Events Not Syncing

If events aren't syncing, verify that ACF Pro is installed and activated. Check that the `seminar` custom post type exists. Ensure ACF field groups are configured with the correct field names. Review sync logs for error messages. Enable debug mode to see detailed error information.

### Trainers Not Linking

If trainers aren't linking to events, verify that the `trainer` custom post type exists. Check that the ACF `trainer` field is configured as a post_object with multiple enabled. Ensure trainer posts have the `simplyorg_id` field populated. Review the sync logs for trainer-related errors.

### Cron Not Running

If automatic sync isn't running, verify that WordPress cron is functioning. Check that "Enable Automatic Sync" is enabled in settings. Use WP-CLI to list scheduled events: `wp cron event list`. Manually trigger the cron event to test: `wp cron event run simplyorg_daily_sync`.

## Contributing

Contributions to the plugin are welcome. When contributing, please follow these guidelines:

- Follow WordPress coding standards
- Include comprehensive DocBlocks for all new code
- Test your changes thoroughly before submitting
- Update documentation to reflect your changes
- Submit pull requests with clear descriptions of changes
- Keep commits focused and atomic

## Support and Resources

For additional support and resources:

- Review the main README.md for usage instructions
- Check the INSTALLATION.md for setup guidance
- Review the CHANGELOG.md for version history
- Submit issues on the GitHub repository
- Contact the development team for questions

## Future Development

Potential areas for future development include:

- Automated unit and integration tests
- Support for additional SimplyOrg API endpoints
- Advanced filtering and search capabilities
- Email notifications for sync events
- Dashboard widgets for sync statistics
- Multi-language support (i18n)
- Webhook support for real-time syncing
- Export/import functionality for settings
- Advanced logging with log levels
- Performance monitoring and optimization tools

