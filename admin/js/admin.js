/**
 * Admin JavaScript for SimplyOrg Connector.
 *
 * Handles AJAX batch synchronization with progress indicator.
 *
 * @package SimplyOrg_Connector
 * @since 1.0.13
 */

(function($) {
	'use strict';

	let syncInProgress = false;
	let totalEvents = 0;
	let processedEvents = 0;
	let results = {
		created: 0,
		updated: 0,
		skipped: 0,
		errors: []
	};

	/**
	 * Initialize sync button handler.
	 */
	$(document).ready(function() {
		$('#simplyorg-sync-button').on('click', function() {
			if (syncInProgress) {
				return;
			}

			startSync();
		});
	});

	/**
	 * Start the synchronization process.
	 */
	function startSync() {
		syncInProgress = true;
		processedEvents = 0;
		results = {
			created: 0,
			updated: 0,
			skipped: 0,
			errors: []
		};

		// Disable button and show progress
		$('#simplyorg-sync-button').prop('disabled', true).text('Syncing...');
		$('#simplyorg-sync-progress').show();
		$('#simplyorg-sync-result').hide();

		// Reset progress
		updateProgress(0, 'Initializing sync...');

		// Start first batch
		processBatch(0);
	}

	/**
	 * Process a batch of events.
	 *
	 * @param {number} offset Current offset in the event list.
	 */
	function processBatch(offset) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'simplyorg_batch_sync',
				nonce: simplyorgAdmin.nonce,
				offset: offset
			},
			success: function(response) {
				if (response.success) {
					const data = response.data;

					// Update total on first batch
					if (offset === 0) {
						totalEvents = data.total;
					}

					// Update results
					results.created += data.created;
					results.updated += data.updated;
					results.skipped += data.skipped;
					if (data.errors && data.errors.length > 0) {
						results.errors = results.errors.concat(data.errors);
					}

					// Update processed count
					processedEvents = data.processed;

					// Update progress
					const percentage = totalEvents > 0 ? Math.round((processedEvents / totalEvents) * 100) : 0;
					const statusText = 'Syncing ' + processedEvents + ' of ' + totalEvents + ' events...';
					const detailsText = 'Created: ' + results.created + ' | Updated: ' + results.updated + ' | Skipped: ' + results.skipped;
					updateProgress(percentage, statusText, detailsText);

					// Continue with next batch if not done
					if (!data.done) {
						processBatch(data.next_offset);
					} else {
						finishSync();
					}
				} else {
					showError(response.data || 'Unknown error occurred');
				}
			},
			error: function(xhr, status, error) {
				showError('AJAX error: ' + error);
			}
		});
	}

	/**
	 * Update progress indicator.
	 *
	 * @param {number} percentage Progress percentage (0-100).
	 * @param {string} statusText Status message.
	 * @param {string} detailsText Details message (optional).
	 */
	function updateProgress(percentage, statusText, detailsText) {
		$('#simplyorg-sync-progress-bar').css('width', percentage + '%');
		$('#simplyorg-sync-percentage').text(percentage + '%');
		$('#simplyorg-sync-status').text(statusText);
		
		if (detailsText) {
			$('#simplyorg-sync-details').text(detailsText);
		}
	}

	/**
	 * Finish sync and show results.
	 */
	function finishSync() {
		syncInProgress = false;

		// Hide progress
		$('#simplyorg-sync-progress').hide();

		// Show results
		let resultHtml = '<div class="notice notice-success is-dismissible"><p>';
		resultHtml += '<strong>Sync completed successfully!</strong><br>';
		resultHtml += 'Total events: ' + totalEvents + '<br>';
		resultHtml += 'Created: ' + results.created + '<br>';
		resultHtml += 'Updated: ' + results.updated + '<br>';
		resultHtml += 'Skipped: ' + results.skipped + '<br>';
		
		if (results.errors.length > 0) {
			resultHtml += '<br><strong>Errors (' + results.errors.length + '):</strong><ul>';
			results.errors.forEach(function(error) {
				resultHtml += '<li>' + error + '</li>';
			});
			resultHtml += '</ul>';
		}
		
		resultHtml += '</p></div>';

		$('#simplyorg-sync-result').html(resultHtml).show();

		// Re-enable button
		$('#simplyorg-sync-button').prop('disabled', false).text('Sync Now');
	}

	/**
	 * Show error message.
	 *
	 * @param {string} message Error message.
	 */
	function showError(message) {
		syncInProgress = false;

		// Hide progress
		$('#simplyorg-sync-progress').hide();

		// Show error
		const errorHtml = '<div class="notice notice-error is-dismissible"><p><strong>Sync failed:</strong> ' + message + '</p></div>';
		$('#simplyorg-sync-result').html(errorHtml).show();

		// Re-enable button
		$('#simplyorg-sync-button').prop('disabled', false).text('Sync Now');
	}

})(jQuery);

