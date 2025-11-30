<?php

class ICLLC_HR_Admin_Interface {
    
    private $database;
    private $applicant_handler;
    private $employee_handler;
    
    public function __construct($database, $applicant_handler, $employee_handler) {
        $this->database = $database;
        $this->applicant_handler = $applicant_handler;
        $this->employee_handler = $employee_handler;
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('HR Management', 'ic-hr-erp-extension'),
            __('HR Management', 'ic-hr-erp-extension'),
            'manage_options',
            'icllc-hr-management',
            array($this, 'hr_management_page'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'icllc-hr-management',
            __('Applicants', 'ic-hr-erp-extension'),
            __('Applicants', 'ic-hr-erp-extension'),
            'manage_options',
            'icllc-hr-applicants',
            array($this, 'applicants_management_page')
        );
    }
    
    /**
     * Handle form submissions with proper nonce verification
     */
    public function handle_form_submissions() {
        // Check if we're on the right page
        if (!isset($_GET['page']) || 'icllc-hr-applicants' !== $_GET['page']) {
            return;
        }

        // Verify nonce for all form submissions
        $nonce_verified = false;
        
        // Check for bulk actions nonce (POST requests)
        if (isset($_POST['_wpnonce']) && !empty($_POST['_wpnonce']) && 
            isset($_POST['action']) && $_POST['action'] != '-1' &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bulk-applicants')) {
            $nonce_verified = true;
            $this->handle_bulk_actions();
        }
        
        // Check for secondary bulk actions nonce
        if (isset($_POST['_wpnonce']) && !empty($_POST['_wpnonce']) && 
            isset($_POST['action2']) && $_POST['action2'] != '-1' &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bulk-applicants')) {
            $nonce_verified = true;
            $_POST['action'] = sanitize_text_field(wp_unslash($_POST['action2']));
            $this->handle_bulk_actions();
        }
        
        // Check for individual actions nonce (GET requests)
        if (isset($_GET['_wpnonce']) && !empty($_GET['_wpnonce']) && 
            isset($_GET['action']) && isset($_GET['applicant'])) {
            $applicant_id = intval($_GET['applicant']);
            $action = sanitize_text_field(wp_unslash($_GET['action']));
            $nonce_action = '';
            
            switch ($action) {
                case 'approve_single':
                    $nonce_action = 'approve_applicant_' . $applicant_id;
                    break;
                case 'reject_single':
                    $nonce_action = 'reject_applicant_' . $applicant_id;
                    break;
                case 'delete':
                    $nonce_action = 'delete_applicant_' . $applicant_id;
                    break;
            }
            
            if ($nonce_action && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), $nonce_action)) {
                $nonce_verified = true;
                $this->handle_individual_actions();
            }
        }
    }
    
    public function hr_management_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('HR Management Dashboard', 'ic-hr-erp-extension'); ?></h1>
            <p><?php echo esc_html__('Welcome to your custom HR management system.', 'ic-hr-erp-extension'); ?></p>
            <hr size="1"><br><br>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=icllc-hr-applicants')); ?>" class="button button-primary"><?php echo esc_html__('Manage Applicants', 'ic-hr-erp-extension'); ?></a> 
                <a href="<?php echo esc_url(admin_url('admin.php?page=erp-hr&section=people&sub-section=employee')); ?>" class="button button-primary"><?php echo esc_html__('View Employees', 'ic-hr-erp-extension'); ?></a>
            </p>
        </div>
        <?php
    }
    
    public function applicants_management_page() {
        // Get current status filter with proper sanitization
        $current_status = 'pending';
        if (isset($_GET['status'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Status filter for display only
            $current_status = sanitize_text_field(wp_unslash($_GET['status'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Status filter for display only
        }
        
        $valid_statuses = array('pending', 'approved', 'rejected', 'completed');
        
        if (!in_array($current_status, $valid_statuses)) {
            $current_status = 'pending';
        }
        
        // Pagination setup
        $per_page = 20;
        $current_page = 1;
        if (isset($_GET['paged'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination for display only
            $current_page = max(1, intval($_GET['paged'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination for display only
        }
        $offset = ($current_page - 1) * $per_page;
        
        // Get applicants and counts
        $applicants = $this->database->get_applicants_by_status($current_status, $per_page, $offset);
        $total_applicants = $this->database->count_applicants_by_status($current_status);
        $total_pages = ceil($total_applicants / $per_page);
        
        // Get counts for each status for the filter tabs
        $status_counts = array();
        foreach ($valid_statuses as $status) {
            $status_counts[$status] = $this->database->count_applicants_by_status($status);
        }
        
        $this->render_applicants_page($current_status, $valid_statuses, $status_counts, $applicants, $total_applicants, $total_pages, $current_page);
    }
    
    private function render_applicants_page($current_status, $valid_statuses, $status_counts, $applicants, $total_applicants, $total_pages, $current_page) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Applicant Management', 'ic-hr-erp-extension'); ?></h1>
            
            <!-- Status Filter Tabs -->
            <div class="wp-filter">
                <ul class="filter-links">
                    <?php foreach ($valid_statuses as $status): ?>
                        <li>
                            <a href="<?php 
                                echo esc_url(wp_nonce_url(
                                    add_query_arg('status', $status, remove_query_arg('paged')),
                                    'status_filter'
                                )); 
                            ?>" 
                               class="<?php echo $current_status === $status ? 'current' : ''; ?>">
                                <?php echo esc_html(ucfirst($status)); ?> 
                                <span class="count">(<?php echo esc_html($status_counts[$status]); ?>)</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <hr size="1"><br>
            
            <?php 
            // Display bulk messages with proper nonce verification
            if (isset($_GET['bulk_message']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'bulk_message')): // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, nonce verified in condition
                $bulk_success = isset($_GET['bulk_success']) ? (bool) sanitize_text_field(wp_unslash($_GET['bulk_success'])) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Already verified above
                $bulk_message = isset($_GET['bulk_message']) ? sanitize_text_field(wp_unslash($_GET['bulk_message'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Already verified above
                ?>
                <div class="notice notice-<?php echo esc_attr($bulk_success ? 'success' : 'error'); ?> is-dismissible">
                    <p><?php echo esc_html(urldecode($bulk_message)); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" id="applicants-filter">
                <?php wp_nonce_field('bulk-applicants', '_wpnonce'); ?>
                <input type="hidden" name="page" value="icllc-hr-applicants">
                
                <!-- Bulk Actions -->
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text"><?php echo esc_html__('Select bulk action', 'ic-hr-erp-extension'); ?></label>
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1"><?php echo esc_html__('Bulk Actions', 'ic-hr-erp-extension'); ?></option>
                            <?php if ($current_status !== 'completed'): ?>
                                <option value="approve"><?php echo esc_html__('Approve', 'ic-hr-erp-extension'); ?></option>
                                <option value="reject"><?php echo esc_html__('Reject', 'ic-hr-erp-extension'); ?></option>
                            <?php endif; ?>
                                <option value="delete"><?php echo esc_html__('Delete', 'ic-hr-erp-extension'); ?></option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="<?php echo esc_attr__('Apply', 'ic-hr-erp-extension'); ?>">
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo esc_html($total_applicants); ?> <?php echo esc_html(_n('item', 'items', $total_applicants, 'ic-hr-erp-extension')); ?></span>
                            <span class="pagination-links">
                                <?php
                                $pagination_args = array(
                                    'base' => wp_nonce_url(add_query_arg('paged', '%#%'), 'pagination'),
                                    'format' => '',
                                    'prev_text' => __('&laquo;', 'ic-hr-erp-extension'),
                                    'next_text' => __('&raquo;', 'ic-hr-erp-extension'),
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'show_all' => false,
                                    'end_size' => 1,
                                    'mid_size' => 2,
                                );
                                echo wp_kses_post(paginate_links($pagination_args));
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <br class="clear">
                </div>
                
                <?php if (empty($applicants)): ?>
                    <div class="notice notice-warning">
                        <p><?php
                        /* translators: %s: Applicant status (pending, approved, rejected, completed) */
                        printf(esc_html__('No %s applicants found.', 'ic-hr-erp-extension'), esc_html($current_status)); 
                        ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <thead>
                            <tr>
                                <td id="cb" class="manage-column column-cb check-column">
                                    <label class="screen-reader-text" for="cb-select-all-1"><?php echo esc_html__('Select All', 'ic-hr-erp-extension'); ?></label>
                                    <input id="cb-select-all-1" type="checkbox">
                                </td>
                                <th class="manage-column column-primary"><?php echo esc_html__('Name', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Email', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Position', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Status', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Date Applied', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Actions', 'ic-hr-erp-extension'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applicants as $applicant): ?>
                                <?php $this->render_applicant_row($applicant, $current_status); ?>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <label class="screen-reader-text" for="cb-select-all-2"><?php echo esc_html__('Select All', 'ic-hr-erp-extension'); ?></label>
                                    <input id="cb-select-all-2" type="checkbox">
                                </td>
                                <th class="manage-column column-primary"><?php echo esc_html__('Name', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Email', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Position', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Status', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Date Applied', 'ic-hr-erp-extension'); ?></th>
                                <th class="manage-column"><?php echo esc_html__('Actions', 'ic-hr-erp-extension'); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <!-- Bottom Bulk Actions -->
                    <div class="tablenav bottom">
                        <div class="alignleft actions bulkactions">
                            <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php echo esc_html__('Select bulk action', 'ic-hr-erp-extension'); ?></label>
                            <select name="action2" id="bulk-action-selector-bottom">
                                <option value="-1"><?php echo esc_html__('Bulk Actions', 'ic-hr-erp-extension'); ?></option>
                                <?php if ($current_status !== 'completed'): ?>
                                    <option value="approve"><?php echo esc_html__('Approve', 'ic-hr-erp-extension'); ?></option>
                                    <option value="reject"><?php echo esc_html__('Reject', 'ic-hr-erp-extension'); ?></option>
                                <?php endif; ?>
                                <option value="delete"><?php echo esc_html__('Delete', 'ic-hr-erp-extension'); ?></option>
                            </select>
                            <input type="submit" id="doaction2" class="button action" value="<?php echo esc_attr__('Apply', 'ic-hr-erp-extension'); ?>">
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                            <div class="tablenav-pages">
                                <span class="displaying-num"><?php echo esc_html($total_applicants); ?> <?php echo esc_html(_n('item', 'items', $total_applicants, 'ic-hr-erp-extension')); ?></span>
                                <?php
                                $pagination_args = array(
                                    'base' => wp_nonce_url(add_query_arg('paged', '%#%'), 'pagination'),
                                    'format' => '',
                                    'prev_text' => __('&laquo;', 'ic-hr-erp-extension'),
                                    'next_text' => __('&raquo;', 'ic-hr-erp-extension'),
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'show_all' => false,
                                    'end_size' => 1,
                                    'mid_size' => 2,
                                );
                                echo wp_kses_post(paginate_links($pagination_args));
                                ?>
                            </div>
                        <?php endif; ?>
                        <br class="clear">
                    </div>
                <?php endif; ?>
            </form>
            
            <?php $this->render_applicant_modal(); ?>
        </div>
        
        <style>
        .check-column { width: 2.2em; }
        .check-column input[type="checkbox"] { margin: 0; }
        .applicant-detail-section { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
        .applicant-detail-section:last-child { border-bottom: none; }
        .applicant-detail-section h3 { color: #0073aa; margin-top: 0; }
        .status-completed { color: #46b450; font-weight: bold; }
        .row-actions { color: #ddd; font-size: 12px; visibility: hidden; }
        tr:hover .row-actions { visibility: visible; }
        .wp-filter .filter-links { display: flex; margin: 0; padding: 0; list-style: none; }
        .wp-filter .filter-links li { margin: 0; }
        .wp-filter .filter-links a { 
            display: block; padding: 8px 12px; text-decoration: none; border: 1px solid #ccc; 
            border-bottom: none; margin-right: 5px; border-radius: 4px 4px 0 0; background: #f9f9f9; 
        }
        .wp-filter .filter-links a.current { background: #fff; border-bottom: 1px solid #fff; margin-bottom: -1px; }
        .wp-filter .filter-links .count { color: #666; font-weight: normal; }
        .button-small { padding: 2px 8px; font-size: 11px; margin: 2px; }
        </style>
        <?php
    }
    
    private function render_applicant_row($applicant, $current_status) {
        $applicant_data = array(
            'id' => $applicant->id,
            'first_name' => $applicant->first_name,
            'last_name' => $applicant->last_name,
            'email' => $applicant->email,
            'phone' => $applicant->phone,
            'state' => $applicant->state,
            'date_of_birth' => $applicant->date_of_birth,
            'position' => $applicant->position,
            'cover_letter' => $applicant->cover_letter,
            'resume_filename' => $applicant->resume_filename,
            'status' => $applicant->status,
            'created_at' => $applicant->created_at
        );
        ?>
        <tr>
            <th scope="row" class="check-column">
                <label class="screen-reader-text" for="cb-select-<?php echo esc_attr($applicant->id); ?>">
                    <?php 
                    /* translators: %s: Applicant full name */
                    printf(esc_html__('Select %s', 'ic-hr-erp-extension'), esc_html($applicant->first_name . ' ' . $applicant->last_name)); 
                    ?>
                </label>
                <input id="cb-select-<?php echo esc_attr($applicant->id); ?>" type="checkbox" name="applicants[]" value="<?php echo esc_attr($applicant->id); ?>">
            </th>
            <td class="column-primary">
                <strong><?php echo esc_html($applicant->first_name . ' ' . $applicant->last_name); ?></strong>
                <div class="row-actions">
                    <span class="view">
                        <a href="#" class="view-application" 
                           data-applicant-id="<?php echo esc_attr($applicant->id); ?>"
                           data-applicant-data='<?php echo esc_attr(wp_json_encode($applicant_data)); ?>'>
                            <?php echo esc_html__('View', 'ic-hr-erp-extension'); ?>
                        </a> |
                    </span>
                    <span class="delete">
                        <a href="<?php 
                            echo esc_url(wp_nonce_url(
                                admin_url('admin.php?page=icllc-hr-applicants&action=delete&applicant=' . $applicant->id), 
                                'delete_applicant_' . $applicant->id
                            )); 
                        ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this applicant?', 'ic-hr-erp-extension')); ?>')">
                            <?php echo esc_html__('Delete', 'ic-hr-erp-extension'); ?>
                        </a>
                    </span>
                </div>
                <button type="button" class="toggle-row">
                    <span class="screen-reader-text"><?php echo esc_html__('Show more details', 'ic-hr-erp-extension'); ?></span>
                </button>
            </td>
            <td><?php echo esc_html($applicant->email); ?></td>
            <td><?php echo esc_html($applicant->position); ?></td>
            <td>
                <?php if ($current_status === 'completed'): ?>
                    <span class="status-completed"><?php echo esc_html__('Completed', 'ic-hr-erp-extension'); ?></span>
                <?php else: ?>
                    <span style="text-transform: capitalize;"><?php echo esc_html($applicant->status); ?></span>
                    <?php $this->render_status_buttons($applicant); ?>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html(gmdate('M j, Y g:i A', strtotime($applicant->created_at))); ?></td>
            <td>
                <?php if ($applicant->status === 'approved'): ?>
                    <button class="button button-primary create-employee" data-applicant-id="<?php echo esc_attr($applicant->id); ?>">
                        <?php echo esc_html__('Create Employee', 'ic-hr-erp-extension'); ?>
                    </button>
                <?php elseif ($applicant->resume_filename): ?>
                    <a href="#" class="button download-resume-btn" data-applicant-id="<?php echo esc_attr($applicant->id); ?>">
                        <?php echo esc_html__('Download Resume', 'ic-hr-erp-extension'); ?>
                    </a>
                <?php else: ?>
                    <span class="description"><?php echo esc_html__('No resume', 'ic-hr-erp-extension'); ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
    
    private function render_status_buttons($applicant) {
        echo '<br>';
        
        if ($applicant->status === 'pending') {
            $this->render_button(__('Approve', 'ic-hr-erp-extension'), 'approve_single', 'approve_applicant', $applicant->id, __('Approve this applicant?', 'ic-hr-erp-extension'));
            $this->render_button(__('Reject', 'ic-hr-erp-extension'), 'reject_single', 'reject_applicant', $applicant->id, __('Reject this applicant?', 'ic-hr-erp-extension'));
        } elseif ($applicant->status === 'approved') {
            $this->render_button(__('Reject', 'ic-hr-erp-extension'), 'reject_single', 'reject_applicant', $applicant->id, __('Reject this applicant?', 'ic-hr-erp-extension'));
        } elseif ($applicant->status === 'rejected') {
            $this->render_button(__('Approve', 'ic-hr-erp-extension'), 'approve_single', 'approve_applicant', $applicant->id, __('Approve this applicant?', 'ic-hr-erp-extension'));
        }
    }
    
    private function render_button($text, $action, $nonce_action, $applicant_id, $confirm_message) {
        ?>
        <a href="<?php 
            echo esc_url(wp_nonce_url(
                admin_url('admin.php?page=icllc-hr-applicants&action=' . $action . '&applicant=' . $applicant_id), 
                $nonce_action . '_' . $applicant_id
            )); 
        ?>" class="button button-small" onclick="return confirm('<?php echo esc_js($confirm_message); ?>')">
            <?php echo esc_html($text); ?>
        </a>
        <?php
    }
    
    private function render_applicant_modal() {
        ?>
        <div id="applicant-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 5px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 1rem;">
                    <h2><?php echo esc_html__('Applicant Details', 'ic-hr-erp-extension'); ?></h2>
                    <button type="button" id="close-modal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
                </div>
                <div id="applicant-details"><!-- Applicant details loaded here --></div>
                <div style="margin-top: 1rem; text-align: right;">
                    <button type="button" class="button" id="close-modal-btn"><?php echo esc_html__('Close', 'ic-hr-erp-extension'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    private function handle_individual_actions() {
        // This method is now called from handle_form_submissions after nonce verification
        if (isset($_GET['action']) && isset($_GET['applicant'])) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Already verified in handle_form_submissions
            $applicant_id = intval($_GET['applicant']);   // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Already verified in handle_form_submissions
            $action = sanitize_text_field(wp_unslash($_GET['action']));   // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Already verified in handle_form_submissions
            
            switch ($action) {
                case 'approve_single':
                    $this->handle_single_status_update($applicant_id, 'approved', 'approved');
                    break;
                    
                case 'reject_single':
                    $this->handle_single_status_update($applicant_id, 'rejected', 'rejected');
                    break;
                    
                case 'delete':
                    $this->handle_single_delete($applicant_id);
                    break;
            }
        }
    }
    
    private function handle_single_status_update($applicant_id, $new_status, $action_text) {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }
        
        $this->database->update_applicant_status($applicant_id, $new_status);
        
        wp_safe_redirect(wp_nonce_url(add_query_arg(array(
            'bulk_message' => urlencode(
                /* translators: %s: Action text (approved, rejected) */
                sprintf(__('Applicant %s successfully.', 'ic-hr-erp-extension'), $action_text)
            ),
            'bulk_success' => true
        ), admin_url('admin.php?page=icllc-hr-applicants')), 'bulk_message'));
        exit;
    }
    
    private function handle_single_delete($applicant_id) {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }
        
        // Use the database class method instead of direct database call
        $result = $this->database->delete_applicants(array($applicant_id));
        
        $message = $result ? __('Applicant deleted successfully.', 'ic-hr-erp-extension') : __('Failed to delete applicant.', 'ic-hr-erp-extension');
        $success = (bool) $result;
        
        wp_safe_redirect(wp_nonce_url(add_query_arg(array(
            'bulk_message' => urlencode($message),
            'bulk_success' => $success
        ), admin_url('admin.php?page=icllc-hr-applicants')), 'bulk_message'));
        exit;
    }
    
    private function handle_bulk_actions() {
        // This method is only called after nonce verification in handle_form_submissions
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified in handle_form_submissions
        
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        // Verify that we have the required POST data
        if (empty($_POST['applicants']) || !isset($_POST['action']) || $_POST['action'] == '-1') { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Already validated
            wp_safe_redirect(wp_nonce_url(add_query_arg(array(
                'bulk_message' => urlencode(__('No applicants selected or invalid action.', 'ic-hr-erp-extension')),
                'bulk_success' => false
            ), admin_url('admin.php?page=icllc-hr-applicants')), 'bulk_message'));
            exit;
        }
            
        $applicant_ids = array_map('intval', $_POST['applicants']); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Already validated
        $action = sanitize_text_field(wp_unslash($_POST['action'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Already validated
        
        $message = '';
        $success = true;
        $result = 0;
        
        switch ($action) {
            case 'approve':
                foreach ($applicant_ids as $applicant_id) {
                    $result += $this->database->update_applicant_status($applicant_id, 'approved');
                }
                /* translators: %d: Number of applicants approved */
                $message = sprintf(_n('Approved %d applicant', 'Approved %d applicants', $result, 'ic-hr-erp-extension'), $result);
                break;
                
            case 'reject':
                foreach ($applicant_ids as $applicant_id) {
                    $result += $this->database->update_applicant_status($applicant_id, 'rejected');
                }
                /* translators: %d: Number of applicants rejected */
                $message = sprintf(_n('Rejected %d applicant', 'Rejected %d applicants', $result, 'ic-hr-erp-extension'), $result);
                break;
                
            case 'delete':
                $result = $this->database->delete_applicants($applicant_ids);
                /* translators: %d: Number of applicants deleted */
                $message = sprintf(_n('Deleted %d applicant', 'Deleted %d applicants', $result, 'ic-hr-erp-extension'), $result);
                break;
                    
            default:
                $message = __('Invalid bulk action', 'ic-hr-erp-extension');
                $success = false;
        }
        
        wp_safe_redirect(wp_nonce_url(add_query_arg(array(
            'bulk_message' => urlencode($message),
            'bulk_success' => $success
        ), admin_url('admin.php?page=icllc-hr-applicants')), 'bulk_message'));
        exit;
    }
}