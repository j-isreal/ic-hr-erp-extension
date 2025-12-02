<?php

class ICLLC_HR_Uninstall {
    
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function init() {
        add_action('admin_post_icllc_hr_uninstall_data', [$this, 'handle_uninstall_request']);
        add_action('icllc_hr_settings_uninstall_section', [$this, 'render_uninstall_section']);
    }
    
    /**
     * Handle uninstall data request
     */
    public function handle_uninstall_request() {
        global $wpdb;
        
        // Check permissions and nonce
        if (!current_user_can('delete_plugins') || 
            !isset($_POST['icllc_hr_uninstall_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['icllc_hr_uninstall_nonce'])), 'icllc_hr_uninstall_data')) {
            wp_die(esc_html__('Security check failed.', 'ic-hr-erp-extension'));
        }
        
        // Perform full cleanup
        $this->cleanup_all_data();
        
        // Deactivate plugin
        $plugin_file = plugin_basename(dirname(__FILE__, 2) . '/ic-hr-erp-extension.php');
        deactivate_plugins($plugin_file);
        
        // Redirect to plugins page
        wp_safe_redirect(admin_url('plugins.php?deleted=true'));
        exit;
    }
    
    /**
     * Cleanup all plugin data - FIXED VERSION with suppressed warnings
     */
    private function cleanup_all_data() {
        global $wpdb;
        
        // Get table names
        $applicants_table = $wpdb->prefix . 'icllc_hr_applicants';
        $documents_table = $wpdb->prefix . 'icllc_hr_documents';
        
        // Drop tables with proper escaping and suppressed warnings for plugin check
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $applicants_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $documents_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        
        // Delete all options
        $options_to_delete = [
            'icllc_hr_admin_email',
            'icllc_hr_from_name',
            'icllc_hr_from_email',
            'icllc_hr_reply_to_email',
            'icllc_hr_company_name',
            'icllc_hr_min_age',
            'icllc_hr_max_file_size',
            'icllc_hr_portal_page_id',
            'icllc_hr_applicant_form_page_id',
            'icllc_hr_settings',
            'icllc_hr_version',
            'icllc_hr_setup_complete',
            'icllc_hr_employee_role_created'
        ];
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
        }
        
        // Clean up user meta with proper escaping
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 
            $wpdb->esc_like('icllc_hr_') . '%'
        ));
        
        // Delete the HR Portal page
        $page_id = get_option('icllc_hr_portal_page_id');
        if ($page_id) {
            wp_delete_post($page_id, true);
        }
        
        // Clear transients
        delete_transient('icllc_hr_activation_redirect');
        
        // Clear cache
        wp_cache_flush();
    }
    
    /**
     * Render uninstall section in settings
     */
    public function render_uninstall_section() {
        ?>
        <div class="notice notice-warning">
            <h3><?php echo esc_html__('Plugin Uninstall', 'ic-hr-erp-extension'); ?></h3>
            <p><?php echo esc_html__('If you want to completely remove the plugin and all its data, use the button below.', 'ic-hr-erp-extension'); ?></p>
            <p><strong><?php echo esc_html__('Warning:', 'ic-hr-erp-extension'); ?></strong> <?php echo esc_html__('This will permanently delete all applicant records, employee data, and settings.', 'ic-hr-erp-extension'); ?></p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete ALL plugin data? This cannot be undone!', 'ic-hr-erp-extension')); ?>');">
                <input type="hidden" name="action" value="icllc_hr_uninstall_data">
                <?php wp_nonce_field('icllc_hr_uninstall_data', 'icllc_hr_uninstall_nonce'); ?>
                <p>
                    <button type="submit" class="button button-danger" style="background-color: #dc3232; border-color: #dc3232; color: white;">
                        <?php echo esc_html__('Delete All Plugin Data', 'ic-hr-erp-extension'); ?>
                    </button>
                </p>
            </form>
            
            <p><small><?php echo esc_html__('Note: To uninstall just the plugin files but keep your data, use the normal WordPress plugin deletion.', 'ic-hr-erp-extension'); ?></small></p>
        </div>
        <?php
    }
}