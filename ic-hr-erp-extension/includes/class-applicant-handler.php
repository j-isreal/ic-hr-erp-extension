<?php

class ICLLC_HR_Applicant_Handler {
    
    private $database;
    private $email_handler;
    private $config;
    
    public function __construct($database, $email_handler, $config) {
        $this->database = $database;
        $this->email_handler = $email_handler;
        $this->config = $config;
    }
    
    public function init() {
        add_action('wp_ajax_submit_application', array($this, 'handle_application_submission'));
        add_action('wp_ajax_nopriv_submit_application', array($this, 'handle_application_submission'));
        add_action('wp_ajax_update_applicant_status', array($this, 'handle_applicant_status_update'));
        add_action('wp_ajax_download_resume', array($this, 'handle_ajax_resume_download'));
    }
    
    public function handle_application_submission() {
        // Check if plugin is properly configured
        if (!$this->config::is_setup_complete()) {
            wp_send_json_error('Plugin is not properly configured. Please contact the site administrator.');
            return;
        }

        // Verify nonce exists and is valid
        if (!isset($_POST['application_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['application_nonce'])), 'submit_application')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Verify Cloudflare Turnstile
        $turnstile_response = isset($_POST['cf-turnstile-response']) ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'])) : '';
        if (!empty($turnstile_response) && !$this->verify_turnstile($turnstile_response)) {
            wp_send_json_error('CAPTCHA verification failed. Please refresh and try again.');
            return;
        }
        
        // Sanitize data with proper validation
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $state = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';
        $date_of_birth = isset($_POST['date_of_birth']) ? sanitize_text_field(wp_unslash($_POST['date_of_birth'])) : '';
        $position = isset($_POST['position']) ? sanitize_text_field(wp_unslash($_POST['position'])) : '';
        $cover_letter = isset($_POST['cover_letter']) ? wp_kses_post(wp_unslash($_POST['cover_letter'])) : '';
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($email) || empty($position) || empty($state) || empty($date_of_birth)) {
            wp_send_json_error('Please fill in all required fields.');
            return;
        }
        
        // Handle file upload
        $resume_data = null;
        $resume_filename = null;
        $resume_mime_type = null;
        
        if (!empty($_FILES['resume']) && isset($_FILES['resume']['error']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            // Sanitize file array elements
            $file = array(
                'name' => isset($_FILES['resume']['name']) ? sanitize_file_name(wp_unslash($_FILES['resume']['name'])) : '',
                'type' => isset($_FILES['resume']['type']) ? sanitize_mime_type($_FILES['resume']['type']) : '',
                'tmp_name' => isset($_FILES['resume']['tmp_name']) ? $_FILES['resume']['tmp_name'] : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- This is a server path, doesn't need sanitization
                'error' => isset($_FILES['resume']['error']) ? intval($_FILES['resume']['error']) : 0,
                'size' => isset($_FILES['resume']['size']) ? intval($_FILES['resume']['size']) : 0
            );
            
            // Validate file type
            $allowed_types = array('application/pdf');
            $file_info = wp_check_filetype($file['name']);
            
            if (!in_array($file_info['type'], $allowed_types)) {
                wp_send_json_error('Only PDF files are allowed for resumes.');
                return;
            }
            
            // Validate file size (5MB limit)
            if ($file['size'] > 5 * 1024 * 1024) {
                wp_send_json_error('Resume file must be less than 5MB.');
                return;
            }
            
            // Read file content
$tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';
if (empty($tmp_name) || !is_uploaded_file($tmp_name)) {
    wp_send_json_error('Invalid file upload.');
    return;
}

$resume_data = file_get_contents($tmp_name); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
if ($resume_data === false) {
    wp_send_json_error('Error reading resume file.');
    return;
}
            
            $resume_filename = $file['name']; // Already sanitized above
            $resume_mime_type = $file_info['type'];
        } else {
            wp_send_json_error('Please upload a resume file.');
            return;
        }
        
        // Get IP address safely
        $ip_address = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        
        // Store application in database
        $application_data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'state' => $state,
            'date_of_birth' => $date_of_birth,
            'position' => $position,
            'cover_letter' => $cover_letter,
            'resume_data' => $resume_data,
            'resume_filename' => $resume_filename,
            'resume_mime_type' => $resume_mime_type,
            'ip_address' => $ip_address,
            'status' => 'pending'
        );
        
        $result = $this->database->insert_application($application_data);
        
        if ($result) {
            // Send notification email to admin
            $this->email_handler->send_application_notification($application_data);
            
            // Send confirmation email to applicant
            $this->email_handler->send_applicant_confirmation_email($application_data);
            
            wp_send_json_success(array(
                'message_english' => 'Thank you for your application! We will review it and contact you soon.',
                'message_spanish' => '¡Gracias por su solicitud! La revisaremos y nos pondremos en contacto pronto.'
            ));
        } else {
            global $wpdb;
            wp_send_json_error('There was an error submitting your application. Please try again. Database error: ' . $wpdb->last_error);
        }
    }
    
    public function handle_applicant_status_update() {
        // Verify nonce exists and is valid
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'update_applicant_status')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $applicant_id = isset($_POST['applicant_id']) ? intval($_POST['applicant_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        
        if (empty($applicant_id) || empty($status)) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $result = $this->database->update_applicant_status($applicant_id, $status);
        
        if ($result !== false) {
            wp_send_json_success('Status updated');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }
    
    public function handle_ajax_resume_download() {
        if (!isset($_GET['applicant_id']) || !isset($_GET['nonce'])) {
            wp_die('Invalid request. Missing parameters.');
        }
        
        $applicant_id = intval($_GET['applicant_id']);
        $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
        
        if (!wp_verify_nonce($nonce, 'download_resume')) {
            wp_die('Invalid download link. Security check failed.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions. You must be an administrator to download resumes.');
        }
        
        $applicant = $this->database->get_applicant_by_id($applicant_id);
        
        if (!$applicant) {
            wp_die('Applicant not found.');
        }
        
        if (empty($applicant->resume_data)) {
            wp_die('No resume data found for this applicant.');
        }
        
        // Set headers for file download
        header('Content-Type: ' . $applicant->resume_mime_type);
        header('Content-Disposition: attachment; filename="' . $applicant->resume_filename . '"');
        header('Content-Length: ' . strlen($applicant->resume_data));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Clear any output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output the file data (binary data doesn't need escaping)
        echo $applicant->resume_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary data doesn't need escaping
        exit;
    }
    
    private function verify_turnstile($response) {
        $secret_key = get_option('cfturnstile_secret');
        
        if (empty($secret_key)) {
            // Skip verification if not configured
            return true;
        }
        
        if (empty($response)) {
            return false;
        }
        
        // Get IP address safely
        $remote_ip = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $remote_ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        
        $verification = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'body' => array(
                'secret' => $secret_key,
                'response' => $response,
                'remoteip' => $remote_ip
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($verification)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($verification);
        $body = wp_remote_retrieve_body($verification);
        $result = json_decode($body, true);
        $success = $result['success'] ?? false;
        
        return $success;
    }
}