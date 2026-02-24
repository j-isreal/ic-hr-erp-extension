<?php
/**
 * Plugin Name: IC HR ERP Extension
 * Plugin URI: https://github.com/j-isreal/ic-hr-erp-extension
 * Description: Adds applicant tracking and employee portal to WP ERP Free version. Features include job application form, employee management, and secure portal.
 * Version: 1.5.1
 * Author: Isreal Consulting, LLC
 * Author URI: https://www.icllc.cc/
 * Text Domain: ic-hr-erp-extension
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires Plugins: erp, simple-cloudflare-turnstile
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
Complete WordPress HR ERP Extension with applicant tracking and employee portal to WP ERP Free version. Features include job application, and secure HR portal.
Copyright (C) 2026 Isreal Consulting, LLC.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; version 2
of the License.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see
<https://www.gnu.org/licenses/>.
*/

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('ICLLC_HR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ICLLC_HR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ICLLC_HR_VERSION', '1.5.1');

class ICLLC_HR_ERP_Extension {
    
    private $database;
    private $applicant_handler;
    private $employee_handler;
    private $admin_interface;
    private $shortcodes;
    private $email_handler;
    private $uninstall;
    
    /**
     * Constructor - Initialize the plugin
     */
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init_plugin']);
    }
    
    /**
     * Load required class files
     */
    private function load_dependencies() {
        $include_files = [
            'class-config.php',        
            'class-settings.php',      
            'class-setup-wizard.php', 
            'class-database.php',
            'class-applicant-handler.php', 
            'class-employee-handler.php',
            'class-admin-interface.php',
            'class-shortcodes.php',
            'class-email-handler.php',
            'class-uninstall.php'
        ];
        
        foreach ($include_files as $file) {
            $file_path = ICLLC_HR_PLUGIN_DIR . 'includes/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                //error_log('IC HR: Missing file: ' . $file_path);
            }
        }
        
        $this->initialize_components();
    }

    public function init_plugin() {
        // Load text domain FIRST
        //$this->load_textdomain();    //not needed any longer on Wordpress 6.2+
        
        if (!$this->check_dependencies()) {
            return;
        }
        
        $this->load_dependencies();
        $this->register_hooks();
        $this->init();
    }

    private function check_dependencies() {
        $missing_deps = [];
        
        if (!class_exists('WeDevs_ERP')) {
            $missing_deps[] = 'WP ERP Free';
        }
        
        if (!function_exists('cfturnstile_script_enqueue')) {
            $missing_deps[] = 'Simple Cloudflare Turnstile';
        }
        
        if (!empty($missing_deps)) {
            add_action('admin_notices', function() use ($missing_deps) {
                ?>
                <div class="notice notice-error">
                    <p><strong>IC HR ERP Extension:</strong> The following required plugins are missing: 
                    <?php echo esc_html(implode(', ', $missing_deps)); ?>. 
                    Please install and activate them.</p>
                </div>
                <?php
            });
            return false;
        }
        
        return true;
    }

    /**
     * Initialize plugin components with dependencies
     */
    private function initialize_components() {
        $this->config = new ICLLC_HR_Config();
        $this->database = new ICLLC_HR_Database();
        $this->email_handler = new ICLLC_HR_Email_Handler($this->config);
        $this->applicant_handler = new ICLLC_HR_Applicant_Handler($this->database, $this->email_handler, $this->config);
        $this->employee_handler = new ICLLC_HR_Employee_Handler($this->database, $this->email_handler);
        $this->admin_interface = new ICLLC_HR_Admin_Interface($this->database, $this->applicant_handler, $this->employee_handler);
        $this->shortcodes = new ICLLC_HR_Shortcodes($this->applicant_handler, $this->employee_handler);
        $this->settings = new ICLLC_HR_Settings($this->config);
        $this->setup_wizard = new ICLLC_HR_Setup_Wizard($this->config, $this->settings);
        $this->uninstall = new ICLLC_HR_Uninstall($this->config);
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Use safe init
        add_action('init', [$this, 'safe_init']);
    }
    
    /**
     * Initialize plugin functionality
     */
    public function init() {
        $this->init_components();
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        
        // Admin hooks
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_init', [$this->database, 'check_database_upgrade']);
        
        // Employee access control hooks
        add_action('init', [$this->employee_handler, 'restrict_employee_access']);
        add_action('after_setup_theme', [$this->employee_handler, 'remove_admin_bar_for_employees']);
        add_filter('login_redirect', [$this->employee_handler, 'employee_login_redirect'], 10, 3);
        
        // Check if HR Portal page exists on admin init
        add_action('admin_init', [$this, 'check_hr_portal_page_exists']);
        
        // Remove the uninstall hook from here - it should be handled differently
        // add_action('admin_post_icllc_hr_uninstall_data', [$this, 'handle_uninstall_request']);
    }
    
    /**
     * Initialize component functionality
     */
    private function init_components() {
        $this->database->init();
        $this->applicant_handler->init();
        $this->employee_handler->init();
        $this->admin_interface->init();
        $this->shortcodes->init();
        $this->email_handler->init();
        $this->settings->init();
        $this->setup_wizard->init();
        $this->uninstall->init();
    }
 
    /**
     * Plugin activation
     */
    public function activate() {
        // Load dependencies first
        $this->load_dependencies();
        
        // Then create tables
        $this->database->create_custom_tables();
        $this->database->upgrade_database_tables();
        $this->employee_handler->create_employee_role();
        
        // Create HR Portal page
        $this->create_hr_portal_page();
        
        // Only set transient if setup is NOT complete
        if (!$this->config::is_setup_complete()) {
            // Set redirect transient for setup wizard - expire in 30 seconds
            set_transient('icllc_hr_activation_redirect', true, 30);
        }
        
        flush_rewrite_rules();
    }

    /**
     * Create HR Portal page if it doesn't exist
     */
    private function create_hr_portal_page() {
        // Check if page already exists by slug
        $existing_page = get_page_by_path('hr-portal');
        
        if (!$existing_page) {
            // Create the page
            $page_data = array(
                'post_title'    => 'HR Portal',
                'post_name'     => 'hr-portal',  // URL will be /hr-portal
                'post_content'  => '[employee_portal]',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_author'   => get_current_user_id() ?: 1,
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
                'meta_input'    => array(
                    '_wp_page_template' => ''  // Default template
                )
            );
            
            $page_id = wp_insert_post($page_data, true);
            
            if (!is_wp_error($page_id)) {
                // Save the page ID for future reference
                update_option('icllc_hr_portal_page_id', $page_id, false);
                
                // Clear any existing cache for this path
                clean_post_cache($page_id);
            }
        } else {
            // Page exists - update it to ensure it has the correct content
            if ($existing_page->post_content !== '[employee_portal]' || 
                $existing_page->post_title !== 'HR Portal') {
                
                $update_data = array(
                    'ID'           => $existing_page->ID,
                    'post_title'   => 'HR Portal',
                    'post_content' => '[employee_portal]',
                    'post_status'  => 'publish'
                );
                
                wp_update_post($update_data);
            }
            
            // Save the existing page ID
            update_option('icllc_hr_portal_page_id', $existing_page->ID, false);
        }
    }

    /**
     * Safe plugin initialization
     */
    public function safe_init() {
        if (!class_exists('ICLLC_HR_Config')) {
            //error_log('IC HR Extension: Config class not found, loading dependencies');
            $this->load_dependencies();
        }
        
        $this->init();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Check if HR Portal page exists and create if missing
     */
    public function check_hr_portal_page_exists() {
        // Only check for administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $page_exists = get_page_by_path('hr-portal');
        $stored_page_id = get_option('icllc_hr_portal_page_id');
        
        if (!$page_exists && !$stored_page_id) {
            // Page doesn't exist - create it
            $this->create_hr_portal_page();
        } elseif ($page_exists && !$stored_page_id) {
            // Page exists but we don't have it stored
            update_option('icllc_hr_portal_page_id', $page_exists->ID, false);
        }
    }
    
    /**
     * Get HR Portal page URL (static method for use elsewhere)
     */
    public static function get_hr_portal_url() {
        $page_id = get_option('icllc_hr_portal_page_id');
        
        if ($page_id) {
            return get_permalink($page_id);
        }
        
        // Fallback - try to get by slug
        $page = get_page_by_path('hr-portal');
        if ($page) {
            update_option('icllc_hr_portal_page_id', $page->ID, false);
            return get_permalink($page->ID);
        }
        
        return '';
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('icllc-hr-style', ICLLC_HR_PLUGIN_URL . 'assets/css/style.css');
        
        $this->maybe_load_turnstile();
    }
    
    /**
     * Load Turnstile CAPTCHA on applicant form pages
     */
    private function maybe_load_turnstile() {
        if (!is_page() || !has_shortcode(get_post()->post_content, 'applicant_form')) {
            return;
        }
        
        if (shortcode_exists('cf7-simple-turnstile')) {
            do_shortcode('[cf7-simple-turnstile]');
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (!$this->is_plugin_page($hook)) {
            return;
        }
        
        $this->load_admin_scripts();
        $this->load_admin_styles();
    }
    
    /**
     * Check if current page is a plugin admin page
     */
    private function is_plugin_page($hook) {
        return strpos($hook, 'icllc-hr') !== false;
    }
    
    /**
     * Load admin JavaScript
     */
    private function load_admin_scripts() {
        wp_enqueue_script(
            'icllc-hr-admin', 
            ICLLC_HR_PLUGIN_URL . 'assets/js/admin.js', 
            ['jquery'], 
            ICLLC_HR_VERSION, 
            true
        );
        
        $this->localize_admin_script();
    }
    
    /**
     * Localize admin script with AJAX data
     */
    private function localize_admin_script() {
        // Get status safely for admin pages (admin filters don't typically need nonce verification)
        $current_status = 'pending';
        if (is_admin() && isset($_GET['status']) && $this->is_plugin_admin_page()) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $current_status = sanitize_text_field(wp_unslash($_GET['status'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        
        $localize_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'current_status' => $current_status, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'nonces' => array(
                'update_applicant_status' => wp_create_nonce('update_applicant_status'),
                'create_employee' => wp_create_nonce('create_employee_from_applicant'),
                'download_resume' => wp_create_nonce('download_resume')
            )
        );
        
        wp_localize_script('icllc-hr-admin', 'icllc_hr_ajax', $localize_data); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    
    /**
     * Get current applicant status filter
     */
    private function get_current_status() {
        // Add nonce verification for admin pages and proper sanitization
        if (isset($_GET['status']) && $this->is_plugin_admin_page() && $this->verify_admin_nonce()) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return sanitize_text_field(wp_unslash($_GET['status'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        return 'pending';
    }
    
    /**
     * Check if we're on a plugin admin page where nonce verification is appropriate
     */
    private function is_plugin_admin_page() {
        // Check if we're in admin and on one of our plugin pages
        if (!is_admin()) {
            return false;
        }
        
        // You might want to add more specific checks here based on your admin pages
        return true;
    }

    /**
     * Verify admin nonce for plugin pages
     */
    private function verify_admin_nonce() {
        // For admin filter parameters, we can check referer or use a specific nonce
        // Since this is just filtering data in admin, we'll check admin referer
        return check_admin_referer('icllc_hr_admin_nonce');
    }

    /**
     * Load admin CSS
     */
    private function load_admin_styles() {
        wp_enqueue_style('icllc-hr-style', ICLLC_HR_PLUGIN_URL . 'assets/css/style.css');
    }
}

// Initialize the plugin
new ICLLC_HR_ERP_Extension();
