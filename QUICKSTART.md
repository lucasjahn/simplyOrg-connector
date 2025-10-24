# Quick Start Guide

Get up and running with SimplyOrg Connector in 5 minutes.

## Prerequisites Checklist

Before you begin, ensure you have:

- ✅ WordPress 5.8 or higher installed
- ✅ PHP 7.4 or higher on your server
- ✅ Advanced Custom Fields (ACF) Pro plugin activated
- ✅ Custom post types `seminar` and `trainer` registered
- ✅ ACF field groups configured for seminars and trainers
- ✅ SimplyOrg account credentials (email and password)

## Installation (2 minutes)

### Step 1: Upload Plugin

Download the plugin and upload the `simplyorg-connector` folder to your WordPress plugins directory at `/wp-content/plugins/`.

### Step 2: Activate

Go to **Plugins** in your WordPress admin and click **Activate** next to "SimplyOrg Connector".

## Configuration (2 minutes)

### Step 3: Configure API Credentials

Navigate to **SimplyOrg Sync** in your WordPress admin menu. Fill in the following fields:

| Field | Value | Example |
|-------|-------|---------|
| **API Base URL** | Your SimplyOrg instance URL | `https://firm-admin.simplyorg-seminare.de` |
| **API Email** | Your SimplyOrg login email | `your-email@example.com` |
| **API Password** | Your SimplyOrg password | `your-password` |

Click **Save Settings**.

### Step 4: Enable Automatic Sync

Check the box next to **Enable Automatic Sync** to enable daily synchronization at 6:00 AM.

Click **Save Settings** again.

## First Sync (1 minute)

### Step 5: Run Manual Sync

Scroll down to the **Manual Sync** section and click the **Sync Now** button.

Wait for the sync to complete. You'll see a success message showing:
- Number of events created
- Number of events updated
- Number of events skipped
- Any errors that occurred

## Verification

### Check Your Events

Navigate to **Seminare** in your WordPress admin. You should see newly created event posts with:
- Event titles from SimplyOrg
- Linked trainer posts
- Event categories
- Multiple dates (for multi-day events)
- Status set to "Draft" (ready for review)

### Check Your Trainers

Navigate to **Trainer** in your WordPress admin. You should see automatically created trainer posts with:
- Trainer names from SimplyOrg
- SimplyOrg ID populated
- Status set to "Draft"

## What's Next?

### Review and Publish Events

New events are created as drafts. Review each event, add any additional information (descriptions, images, etc.), and publish when ready.

### Monitor Sync Logs

Return to **SimplyOrg Sync** to view sync logs and monitor automatic synchronization.

### Customize Settings

Adjust sync settings as needed. You can disable automatic sync if you prefer manual control.

## Common First-Time Issues

### "API credentials are not configured"

**Solution**: Make sure you've entered and saved your API credentials in the settings page.

### "Failed to connect to SimplyOrg"

**Solution**: Verify your API Base URL is correct and your server can make outbound HTTPS connections.

### "Failed to extract CSRF token"

**Solution**: Check that your API Base URL is correct and accessible. Try accessing it in your browser.

### No events synced

**Solution**: Verify that you have events in SimplyOrg within the current and next year date range.

### Events created but trainers not linked

**Solution**: Ensure the ACF `trainer` field is configured as a post_object with multiple enabled.

## Getting Help

If you encounter issues:

1. Check the **Recent Sync Logs** section for error messages
2. Enable WordPress debug mode to see detailed logs
3. Review the full README.md for detailed documentation
4. Check the INSTALLATION.md for troubleshooting tips
5. Submit an issue on the GitHub repository

## Daily Workflow

Once configured, the plugin works automatically:

1. **Every morning at 6:00 AM**: Plugin syncs events from SimplyOrg
2. **New events**: Created as drafts for your review
3. **Changed events**: Automatically updated
4. **Unchanged events**: Skipped (no unnecessary updates)
5. **Trainers**: Automatically created and linked

Your content managers can focus on reviewing and publishing events without worrying about data entry.

## Pro Tips

- **Initial Setup**: Do a manual sync first to verify everything works before enabling automatic sync
- **Review Process**: Set up a workflow for content managers to review draft events daily
- **Monitoring**: Check sync logs weekly to ensure synchronization is running smoothly
- **Testing Changes**: Use manual sync to test after making configuration changes
- **Performance**: The plugin uses hash-based change detection, so re-syncing is fast and efficient

## Next Steps

For more advanced usage and customization:

- Read the full **README.md** for detailed features and usage
- Review **DEVELOPER.md** if you want to extend the plugin
- Check **CHANGELOG.md** for version history and updates

---

**Congratulations!** You've successfully set up SimplyOrg Connector. Your events and trainers will now stay synchronized automatically.

