# SimplyOrg Connector

A professional WordPress plugin that synchronizes events and trainers from the SimplyOrg event management platform to WordPress custom post types with Advanced Custom Fields (ACF).

## Features

- **Automatic Daily Sync**: Scheduled synchronization every morning at 6:00 AM
- **Manual Sync**: Trigger synchronization on-demand via admin interface
- **Smart Change Detection**: Uses content hashing to only update changed data
- **Multi-Day Event Support**: Properly groups multi-day events into single posts with multiple date entries
- **Trainer Auto-Creation**: Automatically creates trainer posts when syncing events
- **Draft Mode**: New events are created as drafts for content manager review
- **Comprehensive Logging**: Tracks all sync operations with detailed logs
- **Secure Authentication**: Full SimplyOrg API authentication flow with CSRF tokens and cookies

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Advanced Custom Fields (ACF) Pro plugin
- Custom post types: `seminar` and `trainer`
- ACF field groups configured for seminars and trainers

## Installation

1. Upload the `simplyorg-connector` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **SimplyOrg Sync** in the WordPress admin menu
4. Configure your API credentials in the settings page
5. Enable automatic sync or trigger a manual sync

## Configuration

### API Settings

Navigate to **SimplyOrg Sync** in the WordPress admin menu and configure:

- **API Base URL**: Your SimplyOrg instance URL (e.g., `https://firm-admin.simplyorg-seminare.de`)
- **API Email**: Your SimplyOrg login email
- **API Password**: Your SimplyOrg login password

### Sync Settings

- **Enable Automatic Sync**: Toggle daily automatic synchronization at 6:00 AM

## Usage

### Manual Sync

1. Go to **SimplyOrg Sync** in the WordPress admin
2. Click the **Sync Now** button
3. View sync results and logs on the same page

### Automatic Sync

Once enabled in settings, the plugin will automatically sync events and trainers every day at 6:00 AM. Check the **Recent Sync Logs** section to monitor automatic sync operations.

## How It Works

### Event Synchronization

1. **Fetch Events**: Retrieves all events from SimplyOrg API for the current and next year
2. **Filter & Group**: Filters out unwanted events (e.g., "Einmietung") and groups multi-day events by `event_id`
3. **Change Detection**: Generates a hash from event data (title, dates, trainer, category) and compares with stored hash
4. **Create/Update**: Creates new posts as drafts or updates existing posts if content has changed
5. **Link Trainers**: Automatically finds or creates trainer posts and links them to events

### Trainer Synchronization

1. **Extract from Events**: Trainers are identified from event data
2. **Find or Create**: Searches for existing trainers by SimplyOrg ID or name
3. **Auto-Creation**: Creates new trainer posts as drafts if not found
4. **Link to Events**: Links trainer posts to event posts via ACF relationship field

### Multi-Day Event Handling

Events with multiple days (same `event_id`, different `event_days`) are grouped into a single WordPress post with multiple entries in the ACF "dates" repeater field.

Example:
- SimplyOrg: "Leadership Training Tag - 1" (Jan 10) + "Leadership Training Tag - 2" (Jan 11)
- WordPress: One post "Leadership Training" with two date entries

### Hash-Based Change Detection

The plugin generates an MD5 hash from relevant event data:
- SimplyOrg ID
- Title
- Trainer name
- Event category
- All dates and times

This hash is stored in post meta (`_simplyorg_content_hash`) and compared on subsequent syncs. Only events with changed hashes are updated, improving performance and reducing unnecessary database writes.

## ACF Field Mapping

### Seminar (Event) Fields

| ACF Field Name | SimplyOrg Data | Description |
|----------------|----------------|-------------|
| `simplyorg_id` | `event_id` | SimplyOrg event ID for tracking |
| `seminar-typ` | `event_category_name` | Event category/type |
| `trainer` | `trainer_name` → Trainer post | Linked trainer post(s) |
| `dates` (repeater) | `event_startdate`, `event_enddate`, `schedule_slot` | Multiple date entries |
| `dates.from` | `event_startdate` + `start_time` | Start date/time |
| `dates.bis` | `event_enddate` + `end_time` | End date/time (if multi-day) |

### Trainer Fields

| ACF Field Name | SimplyOrg Data | Description |
|----------------|----------------|-------------|
| `simplyorg_id` | `trainer` ID from `schedule_slot` | SimplyOrg trainer ID |
| Post Title | `trainer_name` | Trainer full name |

## Architecture

The plugin follows modern object-oriented PHP principles with clear separation of concerns:

### Class Structure

```
SimplyOrg_Connector_Loader (main plugin file)
└── SimplyOrg_Connector (coordinator)
    ├── SimplyOrg_API_Client (API authentication & requests)
    ├── SimplyOrg_Hash_Manager (hash generation & comparison)
    ├── SimplyOrg_Trainer_Syncer (trainer sync logic)
    ├── SimplyOrg_Event_Syncer (event sync logic)
    ├── SimplyOrg_Admin (admin interface)
    └── SimplyOrg_Cron (scheduled tasks)
```

### File Structure

```
simplyorg-connector/
├── simplyorg-connector.php       # Main plugin file
├── includes/
│   ├── class-simplyorg-connector.php  # Main coordinator class
│   ├── class-api-client.php           # API authentication & requests
│   ├── class-hash-manager.php         # Hash generation & comparison
│   ├── class-trainer-syncer.php       # Trainer synchronization
│   ├── class-event-syncer.php         # Event synchronization
│   ├── class-admin.php                # Admin interface
│   └── class-cron.php                 # Cron job management
├── admin/
│   └── css/
│       └── admin.css                  # Admin styles
└── README.md                          # This file
```

## Code Quality

This plugin adheres to professional development standards:

- **Modern OOP PHP**: Clean, maintainable object-oriented architecture
- **DRY Principle**: No code duplication, reusable components
- **Comprehensive DocBlocks**: Every class, method, and property is documented
- **WordPress Coding Standards**: Follows WordPress PHP coding standards
- **Security Best Practices**: Proper sanitization, escaping, and nonce verification
- **Error Handling**: Comprehensive error handling with WP_Error
- **Performance Optimized**: Hash-based change detection minimizes database operations

## Logging

All sync operations are logged and can be viewed in the admin interface:

- Sync start/completion timestamps
- Number of events created, updated, and skipped
- Error messages with details
- Last 100 log entries are retained

Logs are also written to the WordPress error log when `WP_DEBUG` is enabled.

## Troubleshooting

### Sync Fails with Authentication Error

- Verify your API credentials in settings
- Ensure the API Base URL is correct
- Check that your SimplyOrg account has proper permissions

### Events Not Creating

- Verify ACF field groups are properly configured
- Check that custom post types `seminar` and `trainer` exist
- Review sync logs for specific error messages

### Trainers Not Linking

- Ensure trainer posts have the `simplyorg_id` field populated
- Check that the ACF `trainer` field is configured as a post_object with multiple enabled

### Cron Not Running

- Verify "Enable Automatic Sync" is checked in settings
- Check WordPress cron is functioning: `wp cron event list` (WP-CLI)
- Manually trigger sync to test functionality

## Development

### Debug Mode

Enable WordPress debug mode to see detailed logs:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Logs will be written to `/wp-content/debug.log`.

### Manual Testing

Use WP-CLI to test cron jobs:

```bash
wp cron event run simplyorg_daily_sync
```

## Changelog

### Version 1.0.0
- Initial release
- Full event and trainer synchronization
- Multi-day event grouping
- Hash-based change detection
- Daily automatic sync
- Manual sync interface
- Comprehensive logging

## Support

For issues, questions, or feature requests, please contact the development team or submit an issue on the GitHub repository.

## License

GPL v2 or later

## Credits

Developed by Lucas Jahn for firm Leipzig event management synchronization.

