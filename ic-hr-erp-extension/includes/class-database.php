<?php

class ICLLC_HR_Database {
    
    public function init() {
        // Database related hooks can go here if needed
    }
    
    public function create_custom_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql_applicants = "CREATE TABLE {$wpdb->prefix}icllc_hr_applicants (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20),
            state varchar(50),
            date_of_birth date,
            position varchar(100) NOT NULL,
            cover_letter longtext,
            resume_data LONGBLOB,
            resume_filename varchar(255),
            resume_mime_type varchar(100),
            status varchar(20) DEFAULT 'pending',
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";
        
        $sql_employee_docs = "CREATE TABLE {$wpdb->prefix}icllc_hr_employee_docs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            employee_id mediumint(9) NOT NULL,
            document_type varchar(50) NOT NULL,
            document_name varchar(255) NOT NULL,
            document_data LONGBLOB,
            document_mime_type varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY employee_id (employee_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Add error handling
        $result1 = dbDelta($sql_applicants);
        $result2 = dbDelta($sql_employee_docs);
    }
    
    public function upgrade_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'icllc_hr_applicants';
        
        // Use direct table name with proper escaping for DDL statements
        $has_state_column = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$wpdb->prefix}icllc_hr_applicants LIKE %s",
                'state'
            )
        );
        
        if (!$has_state_column) {
            // For DDL statements (ALTER TABLE), direct queries are necessary
            $wpdb->query("ALTER TABLE {$wpdb->prefix}icllc_hr_applicants ADD COLUMN state varchar(50) AFTER phone"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("ALTER TABLE {$wpdb->prefix}icllc_hr_applicants ADD COLUMN date_of_birth date AFTER state"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        }
    }
    
    public function check_database_upgrade() {
        global $wpdb;
        
        // Use prepared statement with LIKE placeholder
        $has_old_structure = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$wpdb->prefix}icllc_hr_applicants LIKE %s",
                'application_data'
            )
        );
        
        if ($has_old_structure) {
            $this->upgrade_database_tables();
        }
    }
    
    public function get_applicants_by_status($status, $per_page = 20, $offset = 0) {
        global $wpdb;
        
        $cache_key = 'icllc_hr_applicants_' . md5($status . $per_page . $offset);
        $cached = wp_cache_get($cache_key, 'icllc_hr');
        
        if ($cached !== false) {
            return $cached;
        }
        
        $results = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            "SELECT * FROM {$wpdb->prefix}icllc_hr_applicants 
             WHERE status = %s 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $status, $per_page, $offset
        ));
        
        wp_cache_set($cache_key, $results, 'icllc_hr', 15 * MINUTE_IN_SECONDS);
        
        return $results;
    }
    
    public function count_applicants_by_status($status) {
        global $wpdb;
        
        $cache_key = 'icllc_hr_applicants_count_' . md5($status);
        $cached = wp_cache_get($cache_key, 'icllc_hr');
        
        if ($cached !== false) {
            return $cached;
        }
        
        $count = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            "SELECT COUNT(*) FROM {$wpdb->prefix}icllc_hr_applicants WHERE status = %s",
            $status
        ));
        
        wp_cache_set($cache_key, $count, 'icllc_hr', 15 * MINUTE_IN_SECONDS);
        
        return $count;
    }
    
    public function get_applicant_by_id($applicant_id) {
        global $wpdb;
        
        $cache_key = 'icllc_hr_applicant_' . $applicant_id;
        $cached = wp_cache_get($cache_key, 'icllc_hr');
        
        if ($cached !== false) {
            return $cached;
        }
        
        $applicant = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            "SELECT * FROM {$wpdb->prefix}icllc_hr_applicants WHERE id = %d",
            $applicant_id
        ));
        
        wp_cache_set($cache_key, $applicant, 'icllc_hr', 15 * MINUTE_IN_SECONDS);
        
        return $applicant;
    }
    
    public function update_applicant_status($applicant_id, $status) {
        global $wpdb;
        
        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'icllc_hr_applicants',
            array('status' => $status),
            array('id' => $applicant_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Clear relevant caches
            $this->clear_applicant_caches($applicant_id);
            $this->clear_applicants_list_caches();
        }
        
        return $result;
    }
    
    public function delete_applicants($applicant_ids) {
        global $wpdb;
        
        if (empty($applicant_ids)) {
            return false;
        }
        
        // Convert all IDs to integers
        $applicant_ids = array_map('intval', $applicant_ids);
        $applicant_ids = array_filter($applicant_ids);
        
        if (empty($applicant_ids)) {
            return false;
        }
        
        // Handle single ID case
        if (count($applicant_ids) === 1) {
            $result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prefix . 'icllc_hr_applicants',
                array('id' => $applicant_ids[0]),
                array('%d')
            );
        } else {
            // For multiple IDs, use individual deletes to avoid complex prepared statements
            $result = true;
            foreach ($applicant_ids as $applicant_id) {
                $delete_result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prefix . 'icllc_hr_applicants',
                    array('id' => $applicant_id),
                    array('%d')
                );
                if ($delete_result === false) {
                    $result = false;
                }
            }
        }
        
        if ($result !== false) {
            // Clear relevant caches
            foreach ($applicant_ids as $applicant_id) {
                $this->clear_applicant_caches($applicant_id);
            }
            $this->clear_applicants_list_caches();
        }
        
        return $result;
    }
    
    public function insert_application($application_data) {
        global $wpdb;
        
        // Define format for each field
        $formats = array();
        foreach ($application_data as $key => $value) {
            if ($key === 'resume_data' && is_string($value)) {
                $formats[] = '%s'; // LONGBLOB as string
            } elseif (in_array($key, ['id', 'phone', 'state', 'date_of_birth', 'position', 'cover_letter', 'resume_filename', 'resume_mime_type', 'status', 'ip_address'])) {
                $formats[] = '%s';
            } else {
                $formats[] = '%s'; // Default to string
            }
        }
        
        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'icllc_hr_applicants',
            $application_data,
            $formats
        );
        
        if ($result !== false) {
            // Clear list caches since we added a new applicant
            $this->clear_applicants_list_caches();
        }
        
        return $result;
    }
    
    /**
     * Clear cache for a specific applicant
     */
    private function clear_applicant_caches($applicant_id) {
        wp_cache_delete('icllc_hr_applicant_' . $applicant_id, 'icllc_hr');
    }
    
    /**
     * Clear all applicants list caches
     */
    private function clear_applicants_list_caches() {
        // Simple approach: delete common cache patterns
        wp_cache_delete('icllc_hr_tracked_keys', 'icllc_hr');
    }
}