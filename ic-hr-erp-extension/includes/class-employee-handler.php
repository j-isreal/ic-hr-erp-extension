<?php

class ICLLC_HR_Employee_Handler {
    
    private $database;
    private $email_handler;
    
    public function __construct($database, $email_handler) {
        $this->database = $database;
        $this->email_handler = $email_handler;
    }
    
    public function init() {
        add_action('wp_ajax_create_employee_from_applicant', array($this, 'handle_create_employee_from_applicant'));
    }
    
    public function create_employee_role() {
        if (!get_role('employee')) {
            add_role('employee', 'Employee', array(
                'read' => true,
                'upload_files' => true,
            ));
        }
    }
    
    public function handle_create_employee_from_applicant() {
        // Verify nonce exists and is valid
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'create_employee_from_applicant')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        global $wpdb;
        
        $applicant_id = isset($_POST['applicant_id']) ? intval($_POST['applicant_id']) : 0;
        
        // Get applicant data
        $applicant = $this->database->get_applicant_by_id($applicant_id);
        
        if (!$applicant) {
            wp_send_json_error('Applicant not found');
            return;
        }
        
        // Check if user already exists
        if (email_exists($applicant->email)) {
            wp_send_json_error('A user with this email already exists');
            return;
        }
        
        try {
            // Create WordPress user first
            $user_id = wp_create_user(
                $applicant->email,
                wp_generate_password(),
                $applicant->email
            );
            
            if (is_wp_error($user_id)) {
                wp_send_json_error('Failed to create user: ' . $user_id->get_error_message());
                return;
            }
            
            // Update user details
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $applicant->first_name,
                'last_name' => $applicant->last_name,
                'display_name' => $applicant->first_name . ' ' . $applicant->last_name
            ));
            
            // Save essential contact information to user meta
            //update_user_meta($user_id, 'phone', $applicant->phone);
            update_user_meta($user_id, 'mobile', $applicant->phone);
            
            // Save state and address information
            update_user_meta($user_id, 'state', $applicant->state);
            update_user_meta($user_id, 'address_state', $applicant->state);
            update_user_meta($user_id, 'country', 'US');
            
            // Save date of birth to user meta
            update_user_meta($user_id, 'date_of_birth', $applicant->date_of_birth);

            // ASSIGN EMPLOYEE ROLE
            $user = new WP_User($user_id);
            $user->set_role('employee');
            
            // Generate employee_id
            $employee_id = $this->generate_employee_id();
            
            // Give WP ERP a moment to create the employee record automatically
            sleep(1);
            
            // CHECK IF EMPLOYEE RECORD ALREADY EXISTS
            $existing_employee = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT * FROM {$wpdb->prefix}erp_hr_employees WHERE user_id = %d",
                $user_id
            ));
            
            if ($existing_employee) {
                // Employee record already exists - UPDATE IT
                $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prefix . 'erp_hr_employees',
                    array(
                        'employee_id' => $employee_id,
                        'designation' => 21,
                        'department' => 12,
                        'hiring_source' => 'applicant_tracking',
                        'hiring_date' => current_time('mysql'),
                        'date_of_birth' => $applicant->date_of_birth,
                        'type' => 'contract',
                        'status' => 'active'
                    ),
                    array('user_id' => $user_id),
                    array('%s', '%d', '%d', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result === false) {
                    throw new Exception('Failed to update WP-ERP employee record: ' . $wpdb->last_error);
                }
            } else {
                // Create employee in WP-ERP directly
                $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prefix . 'erp_hr_employees',
                    array(
                        'user_id' => $user_id,
                        'employee_id' => $employee_id,
                        'designation' => 21,
                        'department' => 12,
                        'location' => 0,
                        'hiring_source' => 'applicant_tracking',
                        'hiring_date' => current_time('mysql'),
                        'termination_date' => '0000-00-00',
                        'date_of_birth' => $applicant->date_of_birth,
                        'reporting_to' => 0,
                        'pay_rate' => 0.00,
                        'pay_type' => 'monthly',
                        'type' => 'contract',
                        'status' => 'active',
                        'deleted_at' => NULL,
                    ),
                    array('%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s')
                );
                
                if ($result === false) {
                    throw new Exception('Failed to insert into WP-ERP employees table: ' . $wpdb->last_error);
                }
            }
            
            // Create employee note if table exists
            $this->create_employee_note($user_id, "Employee created from applicant tracking system. Applied for position: " . $applicant->position);
            
            // Send welcome email
            $this->email_handler->send_welcome_email($user_id, $applicant->email);
            
            // Update applicant status to 'completed'
            $this->database->update_applicant_status($applicant_id, 'completed');
            
            wp_send_json_success('Employee created successfully in WP-ERP! Employee ID: ' . $employee_id);
            
        } catch (Exception $e) {
            // Clean up the WordPress user if WP-ERP insertion failed
            if (isset($user_id) && $user_id) {
                wp_delete_user($user_id);
            }
            wp_send_json_error('Error creating employee: ' . $e->getMessage());
        }
    }
    
    private function generate_employee_id() {
    global $wpdb;
    
    // Get the highest employee_id value using direct query (no variables to prepare)
    $last_employee_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnnecessaryPrepare
        "SELECT MAX(CAST(employee_id AS UNSIGNED)) FROM {$wpdb->prefix}erp_hr_employees"
    );
    
    //error_log('ICLLC HR: Last employee_id found: ' . $last_employee_id);
    
    if ($last_employee_id && is_numeric($last_employee_id)) {
        $new_id = intval($last_employee_id) + 1;
        //error_log('ICLLC HR: Generated new employee_id: ' . $new_id);
        return strval($new_id);
    } else {
        //error_log('ICLLC HR: Using default starting employee_id: 2025201');
        return '2025201'; // Starting employee ID
    }
}
    
    /**
     * Check if employee notes table exists
     */
    private function employee_notes_table_exists() {
        global $wpdb;
        
        $notes_table = $wpdb->prefix . 'erp_hr_employee_notes';
        
        // Check cache first
        $cache_key = 'icllc_hr_notes_table_exists';
        $table_exists = wp_cache_get($cache_key);
        
        if (false === $table_exists) {
            // Check if employee notes table exists using prepared statement
            $table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare("SHOW TABLES LIKE %s", $notes_table)
            ) === $notes_table;
            
            // Cache the result for 1 hour
            wp_cache_set($cache_key, $table_exists, '', HOUR_IN_SECONDS);
        }
        
        return $table_exists;
    }

    private function create_employee_note($user_id, $note) {
        global $wpdb;
        
        // Use the cached table check
        if ($this->employee_notes_table_exists()) {
            $notes_table = $wpdb->prefix . 'erp_hr_employee_notes';
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $notes_table,
                array(
                    'user_id' => $user_id,
                    'comment' => $note,
                    'comment_by' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%s', '%d', '%s')
            );
        }
    }
    
    public function restrict_employee_access() {
    // Don't run during AJAX requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        
        // Don't restrict administrators
        if (in_array('administrator', $current_user->roles)) {
            return;
        }
        
        // Only restrict employees
        if (in_array('employee', $current_user->roles)) {
            // If trying to access admin area, redirect to portal
            if (is_admin() && !wp_doing_ajax()) {
                wp_safe_redirect(home_url('/hr-portal'));
                exit;
            }
            
            // For frontend, only allow specific pages
            $current_slug = get_post_field('post_name', get_the_ID());
            $allowed_slugs = array('hr-portal', 'paystubs', 'employee-documents');
            
            if (!is_front_page() && !in_array($current_slug, $allowed_slugs) && $current_slug) {
                wp_safe_redirect(home_url('/hr-portal'));
                exit;
            }
        }
    }
}
    
    public function remove_admin_bar_for_employees() {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            
            // Don't remove admin bar for administrators
            if (in_array('administrator', $current_user->roles)) {
                return;
            }
            
            // Only remove for employees
            if (in_array('employee', $current_user->roles)) {
                show_admin_bar(false);
            }
        }
    }
    
    public function employee_login_redirect($redirect_to, $request, $user) {
        // Is there a user to check?
        if (isset($user->roles) && is_array($user->roles)) {
            // Only redirect employees, not administrators
            if (in_array('employee', $user->roles) && !in_array('administrator', $user->roles)) {
                return home_url('/hr-portal');
            }
        }
        return $redirect_to;
    }
    
    public function display_employee_paystubs($user_id) {
        // This would connect to your payroll system
        // For now, showing placeholder
        echo '<p>Paystub functionality would be integrated with your payroll system here.</p>';
        
        // Example paystub list
        $paystubs = array(
            array('date' => '2024-01-15', 'amount' => '$2,500.00', 'status' => 'Available'),
            array('date' => '2024-01-01', 'amount' => '$2,500.00', 'status' => 'Available'),
        );
        
        echo '<ul>';
        foreach ($paystubs as $paystub) {
            echo '<li>' . esc_html($paystub['date']) . ' - ' . esc_html($paystub['amount']) . ' 
                  <a href="#" class="view-paystub">View</a></li>';
        }
        echo '</ul>';
    }
    
    public function display_pending_documents($user_id) {
        echo '<p>No pending documents at this time.</p>';
    }

    /**
     * Get employee data from wp_usermeta
     */
    public function get_employee_usermeta_data($user_id) {
        // WP ERP usermeta keys - only mobile (no phone)
        $meta_keys = [
            'street_1' => 'street_1',
            'street_2' => 'street_2',
            'city' => 'city',
            'state' => 'state',
            'postal_code' => 'postal_code',
            'mobile' => 'mobile'  // Only this phone field
        ];
        
        $data = [];
        
        foreach ($meta_keys as $key => $meta_key) {
            $value = get_user_meta($user_id, $meta_key, true);
            $data[$key] = $value;
        }
        
        return $data;
    }

    /**
     * Update employee data in wp_usermeta
     */
    public function update_employee_usermeta($user_id, $data) {
        $updated = false;
        
        // Direct meta keys (no prefix) - only mobile
        $meta_keys = [
            'street_1' => 'street_1',
            'street_2' => 'street_2',
            'city' => 'city',
            'state' => 'state',
            'postal_code' => 'postal_code',
            'mobile' => 'mobile'  // Only this phone field
        ];
        
        foreach ($data as $key => $value) {
            if (isset($meta_keys[$key])) {
                $meta_key = $meta_keys[$key];
                $result = update_user_meta($user_id, $meta_key, $value);
                
                if ($result !== false) {
                    $updated = true;
                }
            }
        }
        
        // Log the update
        if ($updated) {
            $employee_id = $this->get_employee_id_by_user_id($user_id);
            $this->log_employee_update($employee_id, $user_id, $data);
        }
        
        return $updated;
    }

    /**
     * Get employee ID from wp_erp_hr_employees table using user_id
     */
    public function get_employee_id_by_user_id($user_id) {
        global $wpdb;
        
        $employee_id = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT id FROM {$wpdb->prefix}erp_hr_employees WHERE user_id = %d",
            $user_id
        ));
        
        return $employee_id ? intval($employee_id) : false;
    }

    /**
     * Get user_id from wp_erp_hr_employees table using employee_id
     */
    public function get_user_id_by_employee_id($employee_id) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT user_id FROM {$wpdb->prefix}erp_hr_employees WHERE id = %d",
            $employee_id
        ));
        
        return $user_id ? intval($user_id) : false;
    }

    /**
     * Log employee data updates
     */
    private function log_employee_update($employee_id, $user_id, $data) {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'icllc_hr_logs';
        
        // Check cache first for table existence
        $cache_key = 'icllc_hr_logs_table_exists';
        $table_exists = wp_cache_get($cache_key);
        
        if (false === $table_exists) {
            // Check if log table exists
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table)) === $log_table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            
            // Cache the result for 1 hour
            wp_cache_set($cache_key, $table_exists, '', HOUR_IN_SECONDS);
        }
        
        if (!$table_exists) {
            // Create log table if it doesn't exist
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $log_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                employee_id bigint(20) NOT NULL,
                user_id bigint(20) NOT NULL,
                action varchar(100) NOT NULL,
                details text,
                ip_address varchar(45),
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY employee_id (employee_id),
                KEY user_id (user_id),
                KEY action (action)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Update cache since we just created the table
            wp_cache_set($cache_key, true, '', HOUR_IN_SECONDS);
        }
        
        // Prepare data for logging (don't log full addresses for privacy)
        $log_data = [
            'street_1_updated' => !empty($data['street_1']) ? 'YES' : 'NO',
            'city_updated' => !empty($data['city']) ? 'YES' : 'NO',
            'state_updated' => !empty($data['state']) ? 'YES' : 'NO',
            'postal_code_updated' => !empty($data['postal_code']) ? 'YES' : 'NO',
            'mobile_updated' => !empty($data['mobile']) ? 'YES' : 'NO'
        ];
        
        // Sanitize server variables
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_textarea_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $log_table,
            [
                'employee_id' => $employee_id,
                'user_id' => $user_id,
                'action' => 'employee_contact_update',
                'details' => json_encode($log_data),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Get current address information for display
     */
    public function get_employee_address_display($user_id) {
        $data = $this->get_employee_usermeta_data($user_id);
        
        $address_lines = [];
        if (!empty($data['street_1'])) $address_lines[] = esc_html($data['street_1']);
        if (!empty($data['street_2'])) $address_lines[] = esc_html($data['street_2']);
        
        $city_state_zip = [];
        if (!empty($data['city'])) $city_state_zip[] = esc_html($data['city']);
        if (!empty($data['state'])) $city_state_zip[] = esc_html($data['state']);
        if (!empty($data['postal_code'])) $city_state_zip[] = esc_html($data['postal_code']);
        
        $output = '';
        if (!empty($address_lines)) {
            $output .= implode('<br>', $address_lines);
        }
        if (!empty($city_state_zip)) {
            $output .= (!empty($output) ? '<br>' : '') . implode(', ', $city_state_zip);
        }
        
        return esc_html($output) ?: 'Not provided';
    }

    /**
     * Get current mobile number for display
     */
    public function get_employee_mobile_display($user_id) {
        $data = $this->get_employee_usermeta_data($user_id);
        return !empty($data['mobile']) ? esc_html($data['mobile']) : 'Not provided';
    }

    public function display_employee_info($user_id) {
        $user = get_userdata($user_id);
        
        // Get employee data
        $employee_data = $this->get_employee_usermeta_data($user_id);
        $employee_id = $this->get_employee_id_by_user_id($user_id);
        
        echo '<div class="employee-info-display">';
        echo '<table class="employee-info-table">';
        echo '<tr><th>Email:</th><td>' . esc_html($user->user_email) . '</td></tr>';
        
        if ($employee_id) {
            echo '<tr><th>Employee ID:</th><td>' . esc_html($employee_id) . '</td></tr>';
        }
        
        echo '<tr><th>Joined:</th><td>' . esc_html(gmdate('F j, Y', strtotime($user->user_registered))) . '</td></tr>';
        
        // Display address
        $address_display = $this->get_employee_address_display($user_id);
        echo '<tr><th>Address:</th><td>' . esc_html($address_display) . '</td></tr>';
        
        // Display mobile
        $mobile_display = $this->get_employee_mobile_display($user_id);
        echo '<tr><th>Mobile:</th><td>' . esc_html($mobile_display) . '</td></tr>';
        
        echo '</table>';
        echo '</div>';
    }
}