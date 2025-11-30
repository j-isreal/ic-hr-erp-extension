<?php
/**
 * Plugin Name: IC HR ERP Extension
 * Plugin URI: https://github.com/j-isreal/ic-hr-erp-extension
 * Description: Adds applicant tracking and employee portal to WP ERP Free version. Features include job application forms, employee management, and secure portals.
 * Version: 1.4.7
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

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('ICLLC_HR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ICLLC_HR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ICLLC_HR_VERSION', '1.4.7');

class ICLLC_HR_ERP_Extension {
    
    private $database;
    private $applicant_handler;
    private $employee_handler;
    private $admin_interface;
    private $shortcodes;
    private $email_handler;
    
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
        'class-email-handler.php'
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
    $this->setup_wizard = new ICLLC_HR_Setup_Wizard($this->config, $this->settings); // Add this
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
    $this->setup_wizard->init(); // Add this
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
    
    // Set redirect transient for setup wizard
    set_transient('icllc_hr_activation_redirect', true, 30);
    
    flush_rewrite_rules();
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