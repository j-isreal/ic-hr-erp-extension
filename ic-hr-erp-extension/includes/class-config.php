<?php

class ICLLC_HR_Config {
    
    // Plugin settings
    const SETTINGS_GROUP = 'icllc_hr_settings';
    const SETTINGS_PAGE = 'icllc-hr-settings';
    const SETUP_COMPLETE_OPTION = 'icllc_hr_setup_complete';
    
    // Default values - EMPTY to force setup
    const DEFAULTS = [
        'admin_email' => '',
        'from_name' => '',
        'from_email' => '',
        'reply_to_email' => '',
        'company_name' => '',
        'min_age' => 18,
        'max_file_size' => 5, // MB
    ];
    
    // File upload settings
    const MAX_FILE_SIZE = 5242880; // 5MB in bytes
    const ALLOWED_FILE_TYPES = ['application/pdf'];
    
    // Pagination
    const ITEMS_PER_PAGE = 20;
    
    /**
     * Get plugin settings with defaults
     */
    public static function get_settings() {
        $defaults = self::DEFAULTS;
        
        return [
            'admin_email' => get_option('icllc_hr_admin_email', $defaults['admin_email']),
            'from_name' => get_option('icllc_hr_from_name', $defaults['from_name']),
            'from_email' => get_option('icllc_hr_from_email', $defaults['from_email']),
            'reply_to_email' => get_option('icllc_hr_reply_to_email', $defaults['reply_to_email']),
            'company_name' => get_option('icllc_hr_company_name', $defaults['company_name']),
            'min_age' => get_option('icllc_hr_min_age', $defaults['min_age']),
            'max_file_size' => get_option('icllc_hr_max_file_size', $defaults['max_file_size']),
        ];
    }
    
    /**
     * Check if setup is complete
     */
    public static function is_setup_complete() {
        return get_option(self::SETUP_COMPLETE_OPTION, false);
    }
    
    /**
     * Mark setup as complete
     */
    public static function complete_setup() {
        update_option(self::SETUP_COMPLETE_OPTION, true);
    }
    
    /**
     * Check if required settings are configured
     */
    public static function has_required_settings() {
        $settings = self::get_settings();
        
        return !empty($settings['admin_email']) &&
               !empty($settings['from_name']) &&
               !empty($settings['from_email']) &&
               !empty($settings['company_name']);
    }
    
    /**
     * Get site-specific URLs
     */
    public static function get_urls() {
        return [
            'admin_applicants' => admin_url('admin.php?page=icllc-hr-applicants&status=pending'),
            'hr_portal' => home_url('/hr-portal'),
            'login' => wp_login_url(),
            'lost_password' => wp_lostpassword_url(),
            'settings_page' => admin_url('admin.php?page=' . self::SETTINGS_PAGE),
        ];
    }
    
    /**
     * Validate email settings
     */
    public static function validate_email_settings($settings) {
        $errors = [];
        
    if (empty($settings['admin_email']) || !is_email($settings['admin_email'])) {
        $errors[] = __('Please enter a valid admin notification email address.', 'ic-hr-erp-extension');
    }
    
    if (empty($settings['from_name'])) {
        $errors[] = __('Please enter a from name for emails.', 'ic-hr-erp-extension');
    }
        
        if (empty($settings['from_email'])) {
            $errors[] = __('From email is required', 'ic-hr-erp-extension');
        } elseif (!is_email($settings['from_email'])) {
            $errors[] = 'From email is invalid';
        }
        
        if (!empty($settings['reply_to_email']) && !is_email($settings['reply_to_email'])) {
            $errors[] = __('Reply-to email is invalid', 'ic-hr-erp-extension');
        }
        
        if (empty($settings['company_name'])) {
            $errors[] = __('Company name is required', 'ic-hr-erp-extension');
        }
        
        return $errors;
    }
}