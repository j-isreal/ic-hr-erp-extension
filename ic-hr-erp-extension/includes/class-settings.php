<?php

class ICLLC_HR_Settings {
    
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'validate_settings_before_save'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_styles'));
        add_action('admin_init', array($this, 'update_setup_status_on_save'));
    }
    
    public function enqueue_settings_styles($hook) {
        if (strpos($hook, 'icllc-hr-settings') === false) {
            return;
        }
        
        wp_add_inline_style('wp-admin', '
            .icllc-settings-wrap {
                display: flex;
                gap: 3rem;
                align-items: flex-start;
                max-width: 1400px;
            }
            .icllc-settings-main {
                flex: 1;
                min-width: 0;
                max-width: 800px;
            }
            .icllc-settings-sidebar {
                width: 450px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .icllc-sidebar-section {
                padding: 1.5rem;
                border-bottom: 1px solid #e1e1e1;
            }
            .icllc-sidebar-section:last-child {
                border-bottom: none;
            }
            .icllc-sidebar-section h3 {
                margin-top: 0;
                color: #23282d;
                font-size: 1.2em;
                font-weight: 600;
                margin-bottom: 1rem;
                padding-bottom: 0.5rem;
                border-bottom: 2px solid #0073aa;
            }
            .shortcode-example {
                background: #f1f1f1;
                padding: 14px;
                border-radius: 6px;
                font-family: "Courier New", monospace;
                font-size: 14px;
                margin: 12px 0;
                border-left: 4px solid #0073aa;
                line-height: 1.4;
                word-break: break-all;
            }
            .shortcode-description {
                margin: 12px 0;
                font-size: 14px;
                line-height: 1.5;
                color: #444;
            }
            .feature-list {
                margin: 12px 0;
                padding-left: 1.5em;
            }
            .feature-list li {
                margin-bottom: 10px;
                line-height: 1.5;
                font-size: 14px;
            }
            .feature-list strong {
                color: #23282d;
            }
            .required {
                color: #d63638;
                font-weight: bold;
            }
            .form-table th label {
                font-weight: 600;
            }
            
            /* Improved sidebar spacing */
            .icllc-sidebar-section > *:first-child {
                margin-top: 0;
            }
            .icllc-sidebar-section > *:last-child {
                margin-bottom: 0;
            }
            
            /* Better list styling */
            .feature-list ol {
                padding-left: 1.5em;
            }
            .feature-list ol li {
                margin-bottom: 12px;
            }
            
            /* Enhanced code block */
            .shortcode-example {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-left: 4px solid #0073aa;
                font-size: 15px;
                padding: 16px;
            }
            
            /* Responsive design */
            @media (max-width: 1200px) {
                .icllc-settings-wrap {
                    flex-direction: column;
                    gap: 2rem;
                }
                .icllc-settings-sidebar {
                    width: 100%;
                    max-width: 800px;
                }
            }
            
            @media (max-width: 782px) {
                .icllc-settings-wrap {
                    gap: 1.5rem;
                }
                .icllc-sidebar-section {
                    padding: 1.25rem;
                }
                .shortcode-example {
                    padding: 12px;
                    font-size: 14px;
                }
            }
        ');
    }
    
    public function add_settings_page() {
        add_submenu_page(
            'icllc-hr-management',
            __('IC HR ERP Extension Settings', 'ic-hr-erp-extension'),
            __('Settings', 'ic-hr-erp-extension'),
            'manage_options',
            $this->config::SETTINGS_PAGE,
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting($this->config::SETTINGS_GROUP, 'icllc_hr_admin_email', [
            'sanitize_callback' => 'sanitize_email',
            'show_in_rest' => false,
        ]);
        register_setting($this->config::SETTINGS_GROUP, 'icllc_hr_from_name', [
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false,
        ]);
        register_setting($this->config::SETTINGS_GROUP, 'icllc_hr_from_email', [
            'sanitize_callback' => 'sanitize_email',
            'show_in_rest' => false,
        ]);
        register_setting($this->config::SETTINGS_GROUP, 'icllc_hr_reply_to_email', [
            'sanitize_callback' => 'sanitize_email',
            'show_in_rest' => false,
        ]);
        register_setting($this->config::SETTINGS_GROUP, 'icllc_hr_company_name', [
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false,
        ]);
        register_setting($this->config::SETTINGS_GROUP, 'icllc_hr_min_age', [
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false,
        ]);
        register_setting($this->config::SETTINGS_GROUP, 'icllc_hr_max_file_size', [
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false,
        ]);
        
        // Add settings sections
        add_settings_section(
            'icllc_hr_email_section',
            __('Email Settings', 'ic-hr-erp-extension'),
            array($this, 'render_email_section'),
            $this->config::SETTINGS_PAGE
        );
        
        add_settings_section(
            'icllc_hr_general_section',
             __('General Settings', 'ic-hr-erp-extension'),
            array($this, 'render_general_section'),
            $this->config::SETTINGS_PAGE
        );
        
        // Email fields
        add_settings_field(
            'icllc_hr_admin_email',
            __('Admin Notification Email', 'ic-hr-erp-extension') . ' <span class="required">*</span>',
            array($this, 'render_admin_email_field'),
            $this->config::SETTINGS_PAGE,
            'icllc_hr_email_section'
        );
        
        add_settings_field(
            'icllc_hr_from_name',
            __('From Name', 'ic-hr-erp-extension') . ' <span class="required">*</span>',
            array($this, 'render_from_name_field'),
            $this->config::SETTINGS_PAGE,
            'icllc_hr_email_section'
        );
        
        add_settings_field(
            'icllc_hr_from_email',
            __('From Email', 'ic-hr-erp-extension') . ' <span class="required">*</span>',
            array($this, 'render_from_email_field'),
            $this->config::SETTINGS_PAGE,
            'icllc_hr_email_section'
        );
        
        add_settings_field(
            'icllc_hr_reply_to_email',
            __('Reply-To Email', 'ic-hr-erp-extension') . ' (Optional)',
            array($this, 'render_reply_to_email_field'),
            $this->config::SETTINGS_PAGE,
            'icllc_hr_email_section'
        );
        
        // General fields
        add_settings_field(
            'icllc_hr_company_name',
            __('Company Name', 'ic-hr-erp-extension') . ' <span class="required">*</span>',
            array($this, 'render_company_name_field'),
            $this->config::SETTINGS_PAGE,
            'icllc_hr_general_section'
        );
        
        add_settings_field(
            'icllc_hr_min_age',
            __('Minimum Applicant Age', 'ic-hr-erp-extension') . ' ',
            array($this, 'render_min_age_field'),
            $this->config::SETTINGS_PAGE,
            'icllc_hr_general_section'
        );
        
        add_settings_field(
            'icllc_hr_max_file_size',
            __('Maximum File Size', 'ic-hr-erp-extension') . ' (MB)',
            array($this, 'render_max_file_size_field'),
            $this->config::SETTINGS_PAGE,
            'icllc_hr_general_section'
        );
    }

    /**
     * Add uninstall section to settings page
     */
    public function render_uninstall_section() {
        // Only show if uninstall class exists
        if (class_exists('ICLLC_HR_Uninstall')) {
            do_action('icllc_hr_settings_uninstall_section');
        }
    }
    
    /**
     * Validate settings before they are saved
     */
    public function validate_settings_before_save() {
        if (isset($_POST['option_page']) && $_POST['option_page'] === $this->config::SETTINGS_GROUP) {
            // Verify nonce
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), $this->config::SETTINGS_GROUP . '-options')) {
                return;
            }
            
            $settings = [
                'admin_email' => isset($_POST['icllc_hr_admin_email']) ? sanitize_email(wp_unslash($_POST['icllc_hr_admin_email'])) : '',
                'from_name' => isset($_POST['icllc_hr_from_name']) ? sanitize_text_field(wp_unslash($_POST['icllc_hr_from_name'])) : '',
                'from_email' => isset($_POST['icllc_hr_from_email']) ? sanitize_email(wp_unslash($_POST['icllc_hr_from_email'])) : '',
                'reply_to_email' => isset($_POST['icllc_hr_reply_to_email']) ? sanitize_email(wp_unslash($_POST['icllc_hr_reply_to_email'])) : '',
                'company_name' => isset($_POST['icllc_hr_company_name']) ? sanitize_text_field(wp_unslash($_POST['icllc_hr_company_name'])) : '',
            ];
            
            $errors = $this->config::validate_email_settings($settings);
            
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    add_settings_error(
                        $this->config::SETTINGS_GROUP,
                        'validation_error',
                        $error,
                        'error'
                    );
                }
                
                // Prevent saving if there are validation errors
                set_transient('settings_errors', get_settings_errors(), 30);
                wp_safe_redirect(admin_url('admin.php?page=' . $this->config::SETTINGS_PAGE . '&settings-updated=false'));
                exit;
            }
        }
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ic-hr-erp-extension'));
        }
        
        // Show setup warning if not complete
        if (!$this->config::is_setup_complete()) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . esc_html__('Setup Required:', 'ic-hr-erp-extension') . '</strong> ' . esc_html__('Please configure all required settings (marked with', 'ic-hr-erp-extension') . ' <span class="required">*</span>) ' . esc_html__('to complete plugin setup.', 'ic-hr-erp-extension') . '</p>';
            echo '</div>';
        }
        
        // Check for settings errors
        settings_errors();
        ?>
        <div class="icllc-settings-wrap">
            <div class="icllc-settings-main">
                <h1><?php echo esc_html__('IC HR ERP Extension Settings', 'ic-hr-erp-extension'); ?></h1><hr size="1" noshade="noshade">
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields($this->config::SETTINGS_GROUP);
                    do_settings_sections($this->config::SETTINGS_PAGE);
                    submit_button(esc_html__('Save Settings', 'ic-hr-erp-extension'));
                    ?>
                </form>
                <div class="icllc-settings-section" style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid #ddd;">
                    <h2><?php echo esc_html__('Advanced Options', 'ic-hr-erp-extension'); ?></h2>
                    <?php $this->render_uninstall_section(); ?>
                </div>
            </div>
            
            <div class="icllc-settings-sidebar"><br />
                <div class="icllc-sidebar-section">
                    <h3>📋 <?php echo esc_html__('Using Shortcodes', 'ic-hr-erp-extension'); ?></h3>
                    <p class="shortcode-description"><?php echo esc_html__('Add these shortcodes to any page or post to display HR functionality.', 'ic-hr-erp-extension'); ?></p>
                    
                    <div class="shortcode-example">[applicant_form]</div>
                    <p class="shortcode-description"><strong><?php echo esc_html__('Applicant Form', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Displays the job application form for new applicants.', 'ic-hr-erp-extension'); ?></p>
                    <ul class="feature-list">
                        <li><strong><?php echo esc_html__('Cloudflare Turnstile CAPTCHA protection', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Automated spam prevention', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('File upload for resumes', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('PDF only with size validation', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Automatic email notifications', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Both admin and applicant confirmations', 'ic-hr-erp-extension'); ?></li>
                    </ul>
                    
                    <div class="shortcode-example">[employee_portal]</div>
                    <p class="shortcode-description"><strong><?php echo esc_html__('Employee Portal', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Secure portal for employees to access their information.  Default page created /hr-portal', 'ic-hr-erp-extension'); ?></p>
                    <ul class="feature-list">
                        <li><strong><?php echo esc_html__('Requires employee login', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Automatic role-based access control', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Paystub access', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Ready for payroll system integration', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Info updates', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Employees can update their details', 'ic-hr-erp-extension'); ?></li>
                    </ul>
                </div>
                
                <div class="icllc-sidebar-section">
                    <h3>🚀 <?php echo esc_html__('Quick Start Guide', 'ic-hr-erp-extension'); ?></h3>
                    <ol class="feature-list">
                        <li><strong><?php echo esc_html__('Configure Settings', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Fill out all required fields on this page (marked with *)', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Create Application Page', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Create a new page and add', 'ic-hr-erp-extension'); ?> <code>[applicant_form]</code> <?php echo esc_html__('shortcode', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Review Employee Portal', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('The plugin created a protected page with', 'ic-hr-erp-extension'); ?> <code>[employee_portal]</code> <?php echo esc_html__('at /hr-portal with ', 'ic-hr-erp-extension'); ?><?php echo esc_html__('shortcode', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Manage Applicants', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Use the HR Management → Applicants menu to review applications', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Create Employees', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Convert approved applicants to employees with one click', 'ic-hr-erp-extension'); ?></li>
                    </ol>
                </div>
                
                <div class="icllc-sidebar-section">
                    <h3>🔧 <?php echo esc_html__('Plugin Features', 'ic-hr-erp-extension'); ?></h3>
                    <ul class="feature-list">
                        <li>✅ <?php echo esc_html__('Complete applicant tracking system with status management', 'ic-hr-erp-extension'); ?></li>
                        <li>✅ <?php echo esc_html__('Secure employee portal with restricted access', 'ic-hr-erp-extension'); ?></li>
                        <li>✅ <?php echo esc_html__('Customizable email notifications in English and Spanish', 'ic-hr-erp-extension'); ?></li>
                        <li>✅ <?php echo esc_html__('Automated employee creation from approved applicants', 'ic-hr-erp-extension'); ?></li>
                    </ul>
                </div>
                
                <div class="icllc-sidebar-section">
                    <h3>📞 <?php echo esc_html__('Need Help?', 'ic-hr-erp-extension'); ?></h3>
                    <p class="shortcode-description"><?php echo esc_html__('If you need assistance with the plugin setup or usage:', 'ic-hr-erp-extension'); ?></p>
                    <ul class="feature-list">
                        <li><strong><?php echo esc_html__('Check the settings', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Ensure all required settings are properly configured', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Verify shortcode placement', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Make sure shortcodes are placed on the correct pages', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Test the forms', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Submit a test application as a logged-out user', 'ic-hr-erp-extension'); ?></li>
                        <li><strong><?php echo esc_html__('Check email functionality', 'ic-hr-erp-extension'); ?></strong> - <?php echo esc_html__('Verify that notification emails are being sent', 'ic-hr-erp-extension'); ?></li>
                    </ul>
                    <p class="shortcode-description" style="margin-top: 1rem; font-style: italic;">
                        <?php echo esc_html__('Most issues can be resolved by ensuring all required settings are filled out and the shortcodes are properly placed on pages.', 'ic-hr-erp-extension'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_email_section() {
        echo '<p>' . esc_html__('Configure email settings for applicant notifications and communications.', 'ic-hr-erp-extension') . '</p>';
    }
    
    public function render_general_section() {
        echo '<p>' . esc_html__('General plugin settings and configurations.', 'ic-hr-erp-extension') . '</p>';
    }
    
    public function render_admin_email_field() {
        $value = get_option('icllc_hr_admin_email', '');
        echo '<input type="email" name="icllc_hr_admin_email" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('Email address where new application notifications will be sent', 'ic-hr-erp-extension') . '</p>';
    }
    
    public function render_from_name_field() {
        $value = get_option('icllc_hr_from_name', '');
        echo '<input type="text" name="icllc_hr_from_name" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('Name that appears in the "From" field of emails', 'ic-hr-erp-extension') . '</p>';
    }
    
    public function render_from_email_field() {
        $value = get_option('icllc_hr_from_email', '');
        echo '<input type="email" name="icllc_hr_from_email" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('Email address that appears in the "From" field', 'ic-hr-erp-extension') . '</p>';
    }
    
    public function render_reply_to_email_field() {
        $value = get_option('icllc_hr_reply_to_email', '');
        echo '<input type="email" name="icllc_hr_reply_to_email" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Optional: Email address for replies (uses From email if empty)', 'ic-hr-erp-extension') . '</p>';
    }
    
    public function render_company_name_field() {
        $value = get_option('icllc_hr_company_name', '');
        echo '<input type="text" name="icllc_hr_company_name" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('Your company name for email signatures and copyright notices', 'ic-hr-erp-extension') . '</p>';
    }
    
    public function render_min_age_field() {
        $value = get_option('icllc_hr_min_age', 18);
        echo '<input type="number" name="icllc_hr_min_age" value="' . esc_attr($value) . '" min="16" max="100" class="small-text" required>';
        echo '<p class="description">' . esc_html__('Minimum age required for applicants', 'ic-hr-erp-extension') . '</p>';
    }
    
    public function render_max_file_size_field() {
        $value = get_option('icllc_hr_max_file_size', 5);
        echo '<input type="number" name="icllc_hr_max_file_size" value="' . esc_attr($value) . '" min="1" max="20" class="small-text" required>';
        echo '<p class="description">' . esc_html__('Maximum resume file size in megabytes (MB)', 'ic-hr-erp-extension') . '</p>';
    }

    /**
     * Update setup complete status when settings are saved - FIXED VERSION
     */
    public function update_setup_status_on_save() {
        // Check if this is our settings page
        if (isset($_POST['option_page']) && $_POST['option_page'] === $this->config::SETTINGS_GROUP) {
            // Verify nonce FIRST - this fixes the nonce verification warning
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), $this->config::SETTINGS_GROUP . '-options')) {
                return;
            }
            
            // Wait a moment for WordPress to save the options
            add_action('updated_option', function($option, $old_value, $value) {
                // Check if this is one of our settings
                if (strpos($option, 'icllc_hr_') === 0) {
                    // Update setup complete status
                    if ($this->config::has_required_settings()) {
                        $this->config::complete_setup();
                    } else {
                        update_option($this->config::SETUP_COMPLETE_OPTION, false);
                    }
                }
            }, 10, 3);
        }
    }
}