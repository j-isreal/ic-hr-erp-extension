<?php

class ICLLC_HR_Setup_Wizard {
    
    private $config;
    private $settings;
    
    public function __construct($config, $settings) {
        $this->config = $config;
        $this->settings = $settings;
    }
    
    public function init() {
        add_action('admin_notices', array($this, 'show_setup_notice'));
        add_action('admin_init', array($this, 'handle_setup_redirect'));
        add_action('admin_menu', array($this, 'add_setup_page'));
    }
    
    /**
     * Show setup notice if not configured
     */
    public function show_setup_notice() {
        if ($this->config::is_setup_complete() || !current_user_can('manage_options')) {
            return;
        }
        
        $settings_url = $this->config::get_urls()['settings_page'];
        ?>
        <div class="notice notice-warning is-dismissible">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="margin: 0.5em 0;">ICLLC HR Plugin Setup Required</h3>
                    <p style="margin: 0.5em 0;">
                        Please configure the required settings before using the HR plugin.
                    </p>
                </div>
                <div>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">
                        Complete Setup Now
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Redirect to setup page on plugin activation
     */
    public function handle_setup_redirect() {
        // Check if we should redirect to setup
        if (get_transient('icllc_hr_activation_redirect')) {
            delete_transient('icllc_hr_activation_redirect');
            
            // Verify nonce for security
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'activate_plugin')) {
                // Nonce verification passed
            } else {
                // Nonce verification failed, but we'll proceed with caution for activation redirect
                // Use WP_DEBUG for debugging instead of error_log in production
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    //error_log('ICLLC HR: Activation redirect nonce verification failed');
                }
            }
            
            if (!empty($_GET['activate-multi'])) {
                return;
            }
            
            if (!current_user_can('manage_options')) {
                return;
            }
            
            // Redirect to settings page
            wp_safe_redirect(admin_url('admin.php?page=' . $this->config::SETTINGS_PAGE));
            exit;
        }
    }
    
    /**
     * Add setup interstitial page
     */
    public function add_setup_page() {
        if ($this->config::is_setup_complete()) {
            return;
        }
        
        add_menu_page(
            'HR Setup Required',
            'HR Setup',
            'manage_options',
            'icllc-hr-setup',
            array($this, 'render_setup_page'),
            'dashicons-warning',
            30
        );
        
        // Remove the regular HR menu until setup is complete
        remove_menu_page('icllc-hr-management');
    }
    
    /**
     * Render the setup interstitial page
     */
    public function render_setup_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        // Verify nonce for security when processing settings updates
        $nonce_verified = false;
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), $this->config::SETTINGS_GROUP . '-options')) {
                $nonce_verified = true;
            }
        }
        
        // Check if settings were just saved (with nonce verification)
        $just_saved = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true' && $nonce_verified;
        
        if ($just_saved && $this->config::has_required_settings()) {
            $this->config::complete_setup();
            $this->show_setup_complete_message();
            return;
        }
        ?>
        <div class="wrap">
            <div class="card" style="max-width: 800px; margin: 2rem auto; border-left: 4px solid #ffb900;">
                <h1 style="color: #ffb900; margin-top: 0;">⚠️ HR Plugin Setup Required</h1>
                
                <div style="background: #fff8e5; padding: 1.5rem; border-radius: 4px; margin: 1.5rem 0;">
                    <h3 style="margin-top: 0;">Almost Ready!</h3>
                    <p>Before you can start using the ICLLC HR plugin, you need to configure a few required settings.</p>
                    <p><strong>These settings are essential for the plugin to function properly:</strong></p>
                    <ul>
                        <li>✅ <strong>Admin Notification Email</strong> - Where application notifications are sent</li>
                        <li>✅ <strong>From Name & Email</strong> - What applicants see as the sender</li>
                        <li>✅ <strong>Company Name</strong> - Used in emails and copyright notices</li>
                    </ul>
                </div>
                
                <div style="background: #f0f9ff; padding: 1.5rem; border-radius: 4px; margin: 1.5rem 0;">
                    <h3 style="margin-top: 0;">Let's Get Started</h3>
                    <p>Please fill out the form below with your company information. All fields marked with * are required.</p>
                    
                    <?php if (!$just_saved): ?>
                        <div style="text-align: center; margin: 2rem 0;">
                            <a href="#settings-form" class="button button-primary button-hero">
                                Configure Settings Now →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($just_saved && !$this->config::has_required_settings()): ?>
                    <div class="notice notice-error">
                        <p><strong>Missing required settings.</strong> Please fill in all required fields marked with *.</p>
                    </div>
                <?php endif; ?>
                
                <div id="settings-form">
                    <h2>Plugin Configuration</h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields($this->config::SETTINGS_GROUP);
                        do_settings_sections($this->config::SETTINGS_PAGE);
                        submit_button('Save Settings & Complete Setup', 'primary', 'submit', true);
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Show setup complete message
     */
    private function show_setup_complete_message() {
        ?>
        <div class="wrap">
            <div class="card" style="max-width: 600px; margin: 2rem auto; border-left: 4px solid #46b450;">
                <h1 style="color: #46b450; margin-top: 0;">🎉 Setup Complete!</h1>
                
                <div style="background: #f0fff0; padding: 1.5rem; border-radius: 4px; margin: 1.5rem 0;">
                    <h3 style="margin-top: 0;">Your HR plugin is ready to use!</h3>
                    <p>All required settings have been configured. You can now:</p>
                    <ul>
                        <li>✅ Add the <code>[applicant_form]</code> shortcode to any page</li>
                        <li>✅ Add the <code>[employee_portal]</code> shortcode for employee access</li>
                        <li>✅ Manage applicants from the HR Management menu</li>
                        <li>✅ Review and adjust settings anytime</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 2rem 0;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=icllc-hr-management')); ?>" class="button button-primary button-hero">
                        Go to HR Dashboard →
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=icllc-hr-applicants')); ?>" class="button button-hero">
                        View Applicants
                    </a>
                </div>
                
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px; text-align: center;">
                    <p style="margin: 0;"><small>You can always adjust these settings later under <strong>HR Management → Settings</strong></small></p>
                </div>
            </div>
        </div>
        <?php
    }
}