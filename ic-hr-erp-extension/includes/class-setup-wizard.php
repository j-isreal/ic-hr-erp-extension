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
    }
    
    /**
     * Show setup notice if not configured
     */
    public function show_setup_notice() {
        // If setup is complete, don't show notice
        if ($this->config::is_setup_complete() || !current_user_can('manage_options')) {
            return;
        }
        
        // Don't show notice on settings page - safe GET parameter check
        if (isset($_GET['page']) && $_GET['page'] === $this->config::SETTINGS_PAGE) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
     * Redirect to setup page on plugin activation - FIXED VERSION with nonce ignores
     */
    public function handle_setup_redirect() {
        // Check if we should redirect to setup
        if (get_transient('icllc_hr_activation_redirect')) {
            // If setup is already complete, just delete transient and return
            if ($this->config::is_setup_complete()) {
                delete_transient('icllc_hr_activation_redirect');
                return;
            }
            
            // Delete the transient immediately
            delete_transient('icllc_hr_activation_redirect');
            
            // Check for multi-activation - safe GET parameter check
            if (!empty($_GET['activate-multi'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return;
            }
            
            if (!current_user_can('manage_options')) {
                return;
            }
            
            // Don't redirect if we're already on the settings page - safe GET parameter check
            if (isset($_GET['page']) && $_GET['page'] === $this->config::SETTINGS_PAGE) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return;
            }
            
            // Redirect to settings page
            wp_safe_redirect(admin_url('admin.php?page=' . $this->config::SETTINGS_PAGE));
            exit;
        }
    }
}