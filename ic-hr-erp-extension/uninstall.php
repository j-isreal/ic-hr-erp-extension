<?php
// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Don't run cleanup automatically - user must choose via interface
// We'll just clean up the minimal stuff that's safe

global $wpdb;

// Delete the HR Portal page we created
$icllc_hr_page_id = get_option('icllc_hr_portal_page_id'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ($icllc_hr_page_id) {
    wp_delete_post($icllc_hr_page_id, true); // true = force delete (bypass trash)
}

// Delete page ID option
delete_option('icllc_hr_portal_page_id');

// Delete activation transient if it exists
delete_transient('icllc_hr_activation_redirect');

// Clear any cached data
wp_cache_flush();

// That's it! We don't delete tables or settings unless user explicitly chooses to
// The full cleanup with user choice is done via the settings page option