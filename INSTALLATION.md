# Installation Guide

This guide provides detailed instructions for installing and configuring the SimplyOrg Connector plugin.

## Prerequisites

Before installing the plugin, ensure your WordPress installation meets the following requirements:

### System Requirements

The plugin requires a WordPress environment with specific technical capabilities. Your server must run **WordPress version 5.8 or higher** and **PHP version 7.4 or higher**. These versions ensure compatibility with modern WordPress APIs and PHP features used throughout the plugin.

### Required Plugins

The SimplyOrg Connector depends on **Advanced Custom Fields (ACF) Pro** for managing custom field data. The free version of ACF is not sufficient, as the plugin utilizes Pro features such as the repeater field for managing multiple event dates and the post_object field for linking trainers to events.

### Custom Post Types

Your WordPress installation must have two custom post types configured: **seminar** for events and **trainer** for trainer profiles. These post types should be registered before activating the plugin. If you're using a custom post type plugin or theme functionality, ensure these are properly configured.

### ACF Field Groups

The plugin expects specific ACF field groups to be configured for both the seminar and trainer post types. The seminar field group should include fields for the SimplyOrg ID, seminar type (category), linked trainers, pricing information, dates repeater, and other event details. The trainer field group should include the SimplyOrg ID, biographical information, contact details, and social links.

## Installation Steps

### Step 1: Upload Plugin Files

Download the plugin files and upload the entire `simplyorg-connector` folder to your WordPress plugins directory at `/wp-content/plugins/`. You can do this via FTP, SFTP, or through your hosting control panel's file manager.

### Step 2: Activate the Plugin

Navigate to the WordPress admin dashboard and access the **Plugins** menu. Locate "SimplyOrg Connector" in the plugin list and click the **Activate** link. Upon activation, the plugin will automatically create a scheduled cron job for daily synchronization and set up default configuration options.

### Step 3: Configure API Credentials

After activation, a new menu item labeled **SimplyOrg Sync** will appear in your WordPress admin menu. Click this menu item to access the plugin settings page. In the API Settings section, you need to configure three essential fields:

The **API Base URL** should be set to your SimplyOrg instance URL, typically something like `https://firm-admin.simplyorg-seminare.de`. Do not include a trailing slash or any path components beyond the domain.

Enter your **API Email** - this is the email address you use to log into your SimplyOrg account. The plugin will use this credential to authenticate with the SimplyOrg API.

Provide your **API Password** - this is your SimplyOrg account password. The password is stored securely in the WordPress options table and is only transmitted over HTTPS during API authentication.

After entering all credentials, click the **Save Settings** button at the bottom of the form.

### Step 4: Enable Automatic Sync

In the Sync Settings section of the settings page, you'll find a checkbox labeled **Enable Automatic Sync**. When enabled, the plugin will automatically synchronize events and trainers from SimplyOrg every day at 6:00 AM server time. This ensures your WordPress site stays up-to-date with the latest event information from SimplyOrg without manual intervention.

### Step 5: Perform Initial Sync

Before relying on automatic synchronization, it's recommended to perform an initial manual sync to verify everything is working correctly. On the same settings page, scroll down to the **Manual Sync** section and click the **Sync Now** button. The plugin will immediately connect to SimplyOrg, authenticate, fetch all events for the current and next year, and create or update corresponding WordPress posts.

After the sync completes, you'll see a summary showing how many events were created, updated, or skipped, along with any errors that occurred. Review this information to ensure the sync was successful.

## Verification

### Check Synced Events

Navigate to the **Seminare** (Seminars) post type in your WordPress admin. You should see newly created event posts with the status set to "Draft". Each event should have its title, linked trainer, event category, and dates properly populated in the ACF fields.

### Check Synced Trainers

Navigate to the **Trainer** post type in your WordPress admin. You should see trainer posts that were automatically created during the event sync. Each trainer post should have the SimplyOrg ID field populated and the trainer's name as the post title.

### Review Sync Logs

Return to the **SimplyOrg Sync** settings page and scroll down to the **Recent Sync Logs** section. This table shows the most recent sync operations, including timestamps and detailed messages about what was synced. Review these logs to ensure there are no errors or warnings.

## Troubleshooting Installation

### Plugin Activation Fails

If the plugin fails to activate, check that ACF Pro is installed and activated first. The plugin will not activate without this dependency. You should see an admin notice explaining the missing dependency.

### API Connection Errors

If you receive API connection errors during the first sync, verify that your API credentials are correct by logging into SimplyOrg directly with the same email and password. Also ensure that your WordPress server can make outbound HTTPS connections to the SimplyOrg domain.

### Missing Custom Post Types

If you see errors about missing post types, ensure that your theme or another plugin has registered the `seminar` and `trainer` custom post types. The plugin does not create these post types automatically, as they may already exist in your WordPress installation.

### ACF Field Errors

If you encounter errors about missing ACF fields, verify that your ACF field groups match the expected structure. The plugin expects specific field names like `simplyorg_id`, `seminar-typ`, `trainer`, and `dates`. Field names must match exactly, including hyphens and underscores.

## Next Steps

After successful installation and initial sync, you can proceed to configure the plugin according to your workflow. Review the main README.md file for detailed usage instructions, including how to manage synced events, customize sync behavior, and monitor ongoing synchronization operations.

Consider setting up a regular review process for newly synced events, as they are created as drafts by default. Your content managers should review each event, add any additional information not available in SimplyOrg (such as detailed descriptions or featured images), and publish the events when ready.

