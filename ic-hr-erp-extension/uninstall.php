<?php
// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up plugin data
global $wpdb;

// Get table names with proper prefix
$icllc_hr_table = $wpdb->prefix . 'icllc_hr_applicants';
$icllc_hr_docs_table = $wpdb->prefix . 'icllc_hr_documents';

// Validate table names to prevent SQL injection
$icllc_hr_allowed_tables = [ // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wpdb->prefix . 'icllc_hr_applicants',
    $wpdb->prefix . 'icllc_hr_documents'
];

// Only drop tables that are in our allowed list
if (in_array($icllc_hr_table, $icllc_hr_allowed_tables, true)) {
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $icllc_hr_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

if (in_array($icllc_hr_docs_table, $icllc_hr_allowed_tables, true)) {
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $icllc_hr_docs_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

// Delete options
delete_option('icllc_hr_settings');
delete_option('icllc_hr_version');
delete_option('icllc_hr_setup_complete');

// Clean up user meta if any
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('icllc_hr_') . '%'
    )
);

// Clear any cached data
wp_cache_flush();