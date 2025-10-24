# Changelog

All notable changes to the SimplyOrg Connector plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-24

### Added
- Initial release of SimplyOrg Connector plugin
- Full authentication flow with SimplyOrg API (CSRF token, cookies, XSRF token)
- Event synchronization from SimplyOrg to WordPress seminar custom post type
- Trainer synchronization with auto-creation functionality
- Multi-day event grouping by event_id
- Hash-based change detection for efficient syncing
- Daily automatic synchronization scheduled at 6:00 AM
- Manual sync trigger via WordPress admin interface
- Comprehensive admin settings page for API credentials
- Sync status dashboard with next scheduled sync time
- Detailed sync logging with last 100 entries retained
- ACF field mapping for seminar and trainer post types
- Draft post creation for new events requiring review
- Filtering of unwanted events (e.g., "Einmietung" category)
- Support for multiple dates per event via ACF repeater field
- Trainer linking via ACF post_object relationship field
- WordPress coding standards compliance
- Comprehensive inline documentation with DocBlocks
- Professional README with usage instructions
- Security best practices (sanitization, escaping, nonce verification)

### Technical Details
- Modern object-oriented PHP architecture
- Singleton pattern for main classes
- Dependency injection for better testability
- WP_Error for comprehensive error handling
- Transients for admin notices
- WordPress options API for settings storage
- WordPress cron API for scheduled tasks
- ACF functions for custom field updates

### Supported Features
- Event date range syncing (current year + next year)
- Trainer extraction from event schedule slots
- Event category mapping to ACF select field
- Start/end time extraction from schedule slots
- Multi-day event detection and grouping
- Duplicate prevention via SimplyOrg ID tracking
- Update skipping for unchanged content (via hash comparison)

## [1.0.7] - 2025-10-24

### Fixed
- Fixed settings page slug inconsistency causing Sync Settings section to not display
- Post type dropdowns now visible in settings

### Added
- Comprehensive logging throughout event syncer when debug mode enabled
- Log API fetch status and event counts
- Log normalization and filtering details
- Log each event sync with result (created/updated/skipped)
- Log skip reasons (no data, Einmietung category, no trainer)
- All logs prefixed with [SimplyOrg Event Syncer]

### Changed
- Logging only active when both debug mode enabled AND WP_DEBUG_LOG is true
- More detailed sync progress tracking in debug log

## [1.0.6] - 2025-10-24

### Fixed
- **CRITICAL:** Changed API request from POST to GET with query parameters
- Match exact browser request format (as shown in curl example)
- Add X-Requested-With header for AJAX requests
- Update Accept header to match browser exactly
- Should finally resolve 405 Method Not Allowed error
- N8N workflow was misleading - actual API uses GET, not POST

## [1.0.5] - 2025-10-24

### Added
- Configurable post type selection in settings for Events and Trainers
- Post type validation before syncing (checks if post types exist)
- Manual sync limited to 10 events to avoid browser timeouts
- Helpful message indicating manual sync limit and full cron sync

### Changed
- Event and Trainer post types are now configurable via dropdown in settings
- Default to 'seminar' and 'trainer' but can be changed to any custom post type
- Full sync via cron still processes all events without limits

### Fixed
- Prevent timeout issues during manual sync in browser
- Better error messages when post types don't exist

## [1.0.4] - 2025-10-24

### Fixed
- Fixed API request body to use string literals 'null' and 'undefined'
- Match exact N8N request body format
- SimplyOrg API expects filter values as strings, not JSON null/undefined
- Should resolve 405 Method Not Allowed error

## [1.0.3] - 2025-10-24

### Added
- Debug mode toggle in settings page
- Comprehensive debug logging throughout API client
- Logs authentication flow, API requests, and responses
- Detailed error logging for troubleshooting 405 and other API errors
- Debug logs written to WordPress error_log when enabled

### Fixed
- Added Accept header to API requests
- Fixed settings field registration for sync and debug options
- Improved API request headers to match N8N workflow

### Changed
- Debug logging can be enabled/disabled via settings page
- Requires WP_DEBUG_LOG enabled in wp-config.php for logging

## [1.0.2] - 2025-10-24

### Fixed
- Fixed memory exhaustion error when saving credentials
- Added validation flag to prevent recursive validation loop
- Use autoload=false when temporarily updating options during validation
- Prevent infinite loop when update_option triggers sanitize callback

## [1.0.1] - 2025-10-24

### Fixed
- Fixed authentication flow to match SimplyOrg API requirements
- Accept 204 status code for successful login (SimplyOrg returns 204 No Content)
- Extract XSRF token from first cookie value instead of searching by cookie name
- Use raw set-cookie headers and join with '; ' to match exact API requirements
- Improved cookie handling to match N8N workflow implementation

### Added
- Automatic credential validation when saving settings
- Success/error messages displayed immediately after saving credentials
- Green checkmark message when credentials are validated successfully
- Detailed error messages when credential validation fails

## [Unreleased]

### Planned Features
- WP-CLI commands for sync operations
- Bulk event deletion for removed SimplyOrg events
- Advanced filtering options (by category, trainer, date range)
- Email notifications for sync failures
- Sync statistics dashboard widget
- Export sync logs to CSV
- Trainer detail syncing (bio, contact info) if API available
- Event description and content syncing
- Featured image syncing if available in API
- Custom sync schedules (hourly, twice daily, etc.)
- Webhook support for real-time syncing
- Multi-language support (i18n)

### Known Limitations
- Trainer details (bio, contact, etc.) are not synced as they're not available in the calendar events API
- Event content/description is not synced (may require separate API endpoint)
- Images are not synced (not available in current API response)
- Only supports one SimplyOrg instance per WordPress installation
- Requires ACF Pro (not compatible with free ACF version)

---

[1.0.7]: https://github.com/lucasjahn/simplyOrg-connector/releases/tag/v1.0.7
[1.0.6]: https://github.com/lucasjahn/simplyOrg-connector/releases/tag/v1.0.6
[1.0.5]: https://github.com/lucasjahn/simplyOrg-connector/releases/tag/v1.0.5
[1.0.4]: https://github.com/lucasjahn/simplyOrg-connector/releases/tag/v1.0.4
[1.0.3]: https://github.com/lucasjahn/simplyOrg-connector/releases/tag/v1.0.3
[1.0.2]: https://github.com/lucasjahn/simplyOrg-connector/releases/tag/v1.0.2
[1.0.1]: https://github.com/lucasjahn/simplyOrg-connector/releases/tag/v1.0.1
[1.0.0]: https://github.com/lucasjahn/simplyOrg-connector/releases/tag/v1.0.0

