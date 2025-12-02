<?php

class ICLLC_HR_Shortcodes {
    
    private $applicant_handler;
    private $employee_handler;
    
    public function __construct($applicant_handler, $employee_handler) {
        $this->applicant_handler = $applicant_handler;
        $this->employee_handler = $employee_handler;
    }
    
    public function init() {
        add_shortcode('applicant_form', array($this, 'applicant_form_shortcode'));
        add_shortcode('employee_portal', array($this, 'employee_portal_shortcode'));
    }
    
    public function applicant_form_shortcode() {
        if (is_user_logged_in()) {
            return '<p>You are already logged in. Please log out to submit an application.</p>';
        }
        
        // Calculate the max date (18 years ago) safely
        $max_date = gmdate('Y-m-d', strtotime('-18 years'));
        
        ob_start();
        ?>
        <div id="applicant-form-container">
            <form id="applicant-form" method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field('submit_application', 'application_nonce'); ?>
                
                <div class="form-section">
                    <h3>Personal Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Mobile Phone *</label>
                            <input type="tel" id="phone" name="phone" 
                                   pattern="\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}" 
                                   title="Please enter a valid 10-digit phone number (e.g., 123-456-7890)"
                                   placeholder="123-456-7890"
                                   required>
                            <div class="phone-hint" style="font-size: 0.85em; color: #666; margin-top: 4px;">
                                Format: 123-456-7890
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="state">State *</label>
                            <select id="state" name="state" required>
                                <option value="" selected disabled>Select your state...</option>
                                <?php
                                $states = $this->get_us_states();
                                foreach ($states as $code => $name) {
                                    echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth *</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" 
                                   max="<?php echo esc_attr($max_date); ?>"
                                   required>
                            <div class="dob-hint" style="font-size: 0.85em; color: #666; margin-top: 4px;">
                                Must be 18 years or older
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Employment Information</h3>
                    
                    <div class="form-group">
                        <label for="position">Desired Position *</label>
                        <select id="position" name="position" required>
                            <option value="" selected disabled>Select a position...</option>
                            <option value="Work-at-Home">Work-at-Home</option>
                            <option value="Customer Service">Customer Service</option>
                            <option value="Data Entry">Data Entry</option>
                            <option value="Sales">Sales</option>
                            <option value="Tax Preparer">Tax Preparer</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="resume">Resume (PDF only) *</label>
                        <input type="file" id="resume" name="resume" accept=".pdf" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cover_letter">Cover Letter</label>
                        <textarea id="cover_letter" name="cover_letter" rows="5"></textarea>
                    </div>
                </div>
                
                <!-- Add Cloudflare Turnstile -->
                <div class="form-section">
                    <h3>Verification</h3>
                    <div class="form-group">
                        <?php 
                        if (shortcode_exists('cf7-simple-turnstile')) {
                            echo do_shortcode('[cf7-simple-turnstile]');
                        } else {
                            echo '<div class="turnstile-error">Security verification is currently unavailable. Please try again later.</div>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="form-submit">
                    <button type="submit" id="submit-application">Submit Application</button>
                    <div id="form-message"></div>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Phone number formatting
            $('#phone').on('input', function(e) {
                var phone = $(this).val().replace(/\D/g, '');
                if (phone.length >= 6) {
                    phone = phone.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
                } else if (phone.length >= 3) {
                    phone = phone.replace(/(\d{3})(\d+)/, '$1-$2');
                }
                $(this).val(phone);
            });

            // Age validation function
            function calculateAge(birthDate) {
                var today = new Date();
                var birthDate = new Date(birthDate);
                var age = today.getFullYear() - birthDate.getFullYear();
                var m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                return age;
            }

            // Real-time age validation
            $('#date_of_birth').on('change', function() {
                var dob = $(this).val();
                if (dob) {
                    var age = calculateAge(dob);
                    if (age < 18) {
                        $('#form-message').html('<div class="error-message">You must be at least 18 years old to apply.</div>');
                        $(this).focus();
                    } else {
                        $('#form-message').html('');
                    }
                }
            });

            // Phone validation function
            function validatePhone(phone) {
                // Remove all non-digits
                var cleaned = phone.replace(/\D/g, '');
                // Check if it's exactly 10 digits
                return cleaned.length === 10;
            }

            // Form submission handler
            $('#applicant-form').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Clear any previous messages
                $('#form-message').html('');
                
                // Validate phone number
                var phone = $('#phone').val();
                if (!validatePhone(phone)) {
                    $('#form-message').html('<div class="error-message">Please enter a valid 10-digit phone number.</div>');
                    $('#phone').focus();
                    return false;
                }
                
                // Validate age
                var dob = $('#date_of_birth').val();
                if (dob) {
                    var age = calculateAge(dob);
                    if (age < 18) {
                        $('#form-message').html('<div class="error-message">You must be at least 18 years old to apply.</div>');
                        $('#date_of_birth').focus();
                        return false;
                    }
                }
                
                // Check if Turnstile is completed
                var turnstileResponse = $('[name="cf-turnstile-response"]').val();
                console.log('Turnstile response length:', turnstileResponse ? turnstileResponse.length : 0);
                
                if (!turnstileResponse || turnstileResponse.length === 0) {
                    $('#form-message').html('<div class="error-message">Please complete the CAPTCHA verification.</div>');
                    return false;
                }
                
                var formData = new FormData(this);
                formData.append('action', 'submit_application');
                formData.append('cf-turnstile-response', turnstileResponse);
                
                // Show loading state
                var submitButton = $('#submit-application');
                var originalText = submitButton.text();
                submitButton.prop('disabled', true).text('Submitting...');
                
                $.ajax({
                    url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log('Response:', response);
                        
                        if (response.success) {
                            // Replace the entire form container with success message
                            $('#applicant-form-container').html(
                                '<div class="success-message">' + 
                                '<h2>Application Submitted Successfully!</h2>' +
                                '<p>' + (response.data.message_english || 'Thank you for your application! We will review it and contact you soon.') + '</p>' +
                                '<p><strong>What happens next?</strong></p>' +
                                '<ul style="text-align: left; display: inline-block; margin: 1rem auto;">' +
                                '<li>We will review your application</li>' +
                                '<li>You will be contacted if we wish to proceed</li>' +
                                '<li>Thank you for your interest!</li>' +
                                '</ul>' +
                                '</div>' +
                                '<div class="spanish-message" style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; font-size: 0.9em; color: #666;">' +
                                '<h3 style="margin-top: 0; color: #495057;">¡Solicitud Enviada Exitosamente!</h3>' +
                                '<p>' + (response.data.message_spanish || '¡Gracias por su solicitud! La revisaremos y nos pondremos en contacto pronto.') + '</p>' +
                                '<p><strong>¿Qué sucede después?</strong></p>' +
                                '<ul style="text-align: left; display: inline-block; margin: 1rem auto;">' +
                                '<li>Revisaremos su solicitud</li>' +
                                '<li>Será contactado si deseamos proceder</li>' +
                                '<li>¡Gracias por su interés!</li>' +
                                '</ul>' +
                                '</div>'
                            );
                        } else {
                            // Show error message and keep form visible
                            $('#form-message').html('<div class="error-message">' + response.data + '</div>');
                            submitButton.prop('disabled', false).text(originalText);
                            
                            // Reset Turnstile if it exists
                            if (typeof turnstile !== 'undefined') {
                                turnstile.reset();
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        // Handle AJAX errors
                        console.error('AJAX Error:', error);
                        $('#form-message').html('<div class="error-message">There was an error submitting your application. Please try again.</div>');
                        submitButton.prop('disabled', false).text(originalText);
                        
                        // Reset Turnstile if it exists
                        if (typeof turnstile !== 'undefined') {
                            turnstile.reset();
                        }
                    }
                });
                
                return false; // Prevent default form submission
            });
        });
        </script>
        
        <style>
        .form-section { margin-bottom: 2rem; }
        .form-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .form-group { flex: 1; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; padding: 0.5rem; border: 1px solid #ddd; 
        }
        input:invalid, select:invalid {
            border-color: #ff6b6b;
            background-color: #fff5f5;
        }
        input:valid, select:valid {
            border-color: #51cf66;
        }
        .success-message { 
           color: green; 
           padding: 2rem; 
           background: #f0fff0; 
           border: 2px solid #00cc00;
           border-radius: 8px;
           margin: 2rem 0;
           font-weight: bold;
           text-align: center;
           font-size: 1.2em;
           box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
       }    
        .error-message { 
            color: red; 
            padding: 1.5rem; 
            background: #fff0f0; 
            border: 1px solid #ff0000;
            border-radius: 4px;
            margin: 1rem 0;
            font-weight: bold;
            text-align: center;
        }
        .validation-error {
            color: #e74c3c;
            font-size: 0.85em;
            margin-top: 4px;
            display: block;
        }
        /* Style for Turnstile widget */
        .cf-turnstile {
            margin: 1rem 0;
        }
        /* Style for Turnstile widget */
        .cf7-simple-turnstile {
            margin: 1rem 0;
        }
        .turnstile-error {
            color: #dc3232;
            padding: 10px;
            background: #fff0f0;
            border: 1px solid #dc3232;
            border-radius: 4px;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    public function employee_portal_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . esc_url(wp_login_url()) . '">log in</a> to access the HR Portal.</p>';
        }
        
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        
        // Check if user has employee role
        if (!in_array('employee', $current_user->roles)) {
            return '<p>Access denied. This portal is for employees only.</p>';
        }
        
        // Get employee ID from wp_erp_hr_employees table using user_id
        $employee_id = $this->employee_handler->get_employee_id_by_user_id($user_id);
        
        if (!$employee_id) {
            return '<p>Employee record not found. Please contact HR.</p>';
        }
        
        // Initialize message
        $message = '';
        
        // Process form submissions if any
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_employee_contact') {  // phpcs:ignore WordPress.Security.NonceVerification.Missing  -- nonce already verified in process_employee_update() method
    // Nonce verification is now done in process_employee_update() method
    $message = $this->process_employee_update($employee_id, $user_id);
    
    // After processing form, ALWAYS show display mode (not edit mode)
    $is_edit_mode = false;
} else {
    // Only check for edit mode if no form was just submitted
    $is_edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'contact'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended  -- nonce already verified in process_employee_update() method
}        
        // Get current employee data from wp_usermeta
        $employee_data = $this->employee_handler->get_employee_usermeta_data($user_id);
        
        ob_start();
        ?>
        <div class="employee-portal">
            <header class="portal-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2>HR Portal</h2>
                        <p>Welcome, <?php echo esc_html($current_user->display_name); ?></p>
                    </div>
                    <div>
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="button" style="text-decoration: none;">
                            Log Out
                        </a>
                    </div>
                </div>
            </header>
            
            <?php if ($message) : ?>
            <div class="portal-message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo wp_kses_post($message); ?>
            </div>
            <?php endif; ?>
            
            <div class="portal-sections">
                <div class="portal-section">
                    <h3>Your Paystubs</h3>
                    <div class="paystub-list">
                        <?php $this->employee_handler->display_employee_paystubs($user_id); ?>
                    </div>
                </div>
                
                <div class="portal-section">
                    <h3>Documents to Sign</h3>
                    <div class="documents-list">
                        <?php $this->employee_handler->display_pending_documents($user_id); ?>
                    </div>
                </div>
                
                <div class="portal-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="margin: 0;">Your Contact Information</h3>
                        <?php if (!$is_edit_mode) : ?>
                        <a href="?edit=contact" class="button button-small">Edit</a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$is_edit_mode) : ?>
                    <!-- Display Mode -->
                    <div class="contact-info-display">
                        <table class="employee-info-table">
                            <tr>
                                <th>Address:</th>
                                <td>
                                    <?php 
                                    $address_lines = [];
                                    if (!empty($employee_data['street_1'])) $address_lines[] = esc_html($employee_data['street_1']);
                                    if (!empty($employee_data['street_2'])) $address_lines[] = esc_html($employee_data['street_2']);
                                    
                                    $city_state_zip = [];
                                    if (!empty($employee_data['city'])) $city_state_zip[] = esc_html($employee_data['city']);
                                    if (!empty($employee_data['state'])) $city_state_zip[] = esc_html($employee_data['state']);
                                    if (!empty($employee_data['postal_code'])) $city_state_zip[] = esc_html($employee_data['postal_code']);
                                    
                                    $output = '';
                                    if (!empty($address_lines)) {
                                        $output .= implode('<br>', $address_lines);
                                    }
                                    if (!empty($city_state_zip)) {
                                        $output .= (!empty($output) ? '<br>' : '') . implode(', ', $city_state_zip);
                                    }
                                    
                                    echo $output ? wp_kses_post($output) : 'Not provided'; // FIXED: Added wp_kses_post
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Mobile:</th>
                                <td><?php echo !empty($employee_data['mobile']) ? esc_html($employee_data['mobile']) : 'Not provided'; ?></td>
                            </tr>
                        </table>
                    </div>
                    <?php else : ?>
                    <!-- Edit Mode -->
                    <form method="POST" class="employee-update-form">
                        <?php wp_nonce_field('update_employee_contact_' . $employee_id, 'employee_nonce'); ?>
                        <input type="hidden" name="action" value="update_employee_contact">
                        <input type="hidden" name="employee_id" value="<?php echo esc_attr($employee_id); ?>">
                        
                        <div class="form-group">
                            <label for="street_1">Street Address *</label>
                            <input type="text" id="street_1" name="street_1" 
                                   value="<?php echo esc_attr($employee_data['street_1'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="street_2">Apartment, Suite, Unit (Optional)</label>
                            <input type="text" id="street_2" name="street_2" 
                                   value="<?php echo esc_attr($employee_data['street_2'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City *</label>
                                <input type="text" id="city" name="city" 
                                       value="<?php echo esc_attr($employee_data['city'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="state">State *</label>
                                <select id="state" name="state" required>
                                    <option value="">Select State</option>
                                    <?php
                                    $states = $this->get_us_states();
                                    $current_state = $employee_data['state'] ?? '';
                                    foreach ($states as $code => $name) {
                                        echo '<option value="' . esc_attr($code) . '" ' . 
                                             selected($current_state, $code, false) . '>' . 
                                             esc_html($name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="postal_code">ZIP Code *</label>
                                <input type="text" id="postal_code" name="postal_code" 
                                       pattern="\d{5}(-\d{4})?" 
                                       title="5 or 9 digit ZIP code"
                                       value="<?php echo esc_attr($employee_data['postal_code'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="mobile">Mobile Number *</label>
                                <input type="tel" id="mobile" name="mobile" 
                                       pattern="\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}" 
                                       title="Please enter a valid 10-digit phone number"
                                       placeholder="123-456-7890"
                                       value="<?php echo esc_attr($employee_data['mobile'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-actions" style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                            <button type="submit" class="button button-primary">Update Information</button>
                            <a href="?" class="button button-secondary">Cancel</a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .employee-portal { max-width: 1200px; margin: 0 auto; }
        .portal-header { background: #f8f9fa; padding: 2rem; margin-bottom: 2rem; }
        .portal-sections { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; }
        .portal-section { border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; }
        .portal-section h3 { margin-top: 0; border-bottom: 2px solid #0073aa; padding-bottom: 0.5rem; }
        .portal-message { 
            padding: 1rem; 
            margin-bottom: 1.5rem; 
            border-radius: 4px; 
            border: 1px solid; 
        }
        .portal-message.success { 
            background-color: #d4edda; 
            border-color: #c3e6cb; 
            color: #155724; 
        }
        .portal-message.error { 
            background-color: #f8d7da; 
            border-color: #f5c6cb; 
            color: #721c24; 
        }
        .employee-update-form { margin-top: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .form-row .form-group { flex: 1; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-weight: 600; }
        .form-group input, .form-group select { 
            width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; 
        }
        .button { 
            display: inline-block; 
            padding: 0.5rem 1.5rem; 
            background: #0073aa; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            text-decoration: none; 
            font-size: 0.9em;
        }
        .button-small { padding: 0.35rem 1rem; font-size: 0.85em; }
        .button:hover { background: #005a87; }
        .button-primary { background: #2271b1; }
        .button-primary:hover { background: #135e96; }
        .button-secondary { background: #6c757d; }
        .button-secondary:hover { background: #545b62; }
        .employee-info-table { width: 100%; border-collapse: collapse; }
        .employee-info-table th, .employee-info-table td { 
            padding: 0.75rem; 
            border-bottom: 1px solid #eee; 
            text-align: left; 
            vertical-align: top; 
        }
        .employee-info-table th { 
            width: 35%; 
            font-weight: 600; 
            color: #555; 
            background: #f9f9f9; 
        }
        .employee-info-table tr:last-child th,
        .employee-info-table tr:last-child td { border-bottom: none; }
        .contact-info-display { margin-top: 0.5rem; }
        .form-actions { margin-top: 1.5rem; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Only run if in edit mode
            if ($('.employee-update-form').length) {
                // Mobile number formatting
                $('#mobile').on('input', function(e) {
                    var phone = $(this).val().replace(/\D/g, '');
                    if (phone.length >= 6) {
                        phone = phone.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
                    } else if (phone.length >= 3) {
                        phone = phone.replace(/(\d{3})(\d+)/, '$1-$2');
                    }
                    $(this).val(phone);
                });
                
                // ZIP code formatting
                $('#postal_code').on('input', function(e) {
                    var zip = $(this).val().replace(/[^0-9-]/g, '');
                    if (zip.length > 5 && !zip.includes('-')) {
                        zip = zip.substring(0, 5) + '-' + zip.substring(5, 9);
                    }
                    $(this).val(zip);
                });
                
                // Auto-format mobile on page load if needed
                var mobile = $('#mobile').val();
                if (mobile) {
                    var phoneDigits = mobile.replace(/\D/g, '');
                    if (phoneDigits.length === 10) {
                        $('#mobile').val(phoneDigits.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3'));
                    }
                }
                
                // Form validation
                $('.employee-update-form').on('submit', function(e) {
                    var valid = true;
                    var required = $(this).find('[required]');
                    
                    required.each(function() {
                        if ($(this).val().trim() === '') {
                            $(this).css('border-color', '#dc3232');
                            valid = false;
                        } else {
                            $(this).css('border-color', '#ddd');
                        }
                    });
                    
                    // Validate ZIP code
                    var zip = $('#postal_code').val();
                    if (zip && !/^\d{5}(-\d{4})?$/.test(zip)) {
                        $('#postal_code').css('border-color', '#dc3232');
                        valid = false;
                    }
                    
                    // Validate mobile number
                    var mobile = $('#mobile').val();
                    if (mobile && !/^\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}$/.test(mobile)) {
                        $('#mobile').css('border-color', '#dc3232');
                        valid = false;
                    }
                    
                    if (!valid) {
                        e.preventDefault();
                        // Remove existing messages
                        $('.portal-message').remove();
                        // Add error message
                        $(this).before('<div class="portal-message error">Please fill in all required fields with valid information.</div>');
                        // Scroll to message
                        $('html, body').animate({
                            scrollTop: $('.portal-message').offset().top - 100
                        }, 300);
                    }
                    
                    return valid;
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
/**
 * Process employee contact information update for wp_usermeta
 */
private function process_employee_update($employee_id, $user_id) {
    // First verify nonce - do this BEFORE accessing any $_POST data
    if (!isset($_POST['employee_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['employee_nonce'])), 'update_employee_contact_' . $employee_id)) {
        return '<p class="error">Security verification failed. Please try again.</p>';
    }
    
    // Now it's safe to access $_POST data
    $posted_employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
    
    // Verify the posted employee_id matches the user's employee_id
    $user_employee_id = $this->employee_handler->get_employee_id_by_user_id($user_id);
    
    if ($posted_employee_id !== $user_employee_id) {
        return '<p class="error">Invalid employee record. Please contact HR.</p>';
    }
    
    // Verify user permissions - must be an employee
    $current_user = wp_get_current_user();
    if (!in_array('employee', $current_user->roles)) {
        return '<p class="error">You do not have permission to update this information.</p>';
    }
    
    // Verify the user is updating their own information
    if ($current_user->ID !== $user_id) {
        return '<p class="error">You can only update your own information.</p>';
    }
    
    // Sanitize and validate inputs with wp_unslash
    $data = [
        'street_1' => isset($_POST['street_1']) ? sanitize_text_field(wp_unslash($_POST['street_1'])) : '',
        'street_2' => isset($_POST['street_2']) ? sanitize_text_field(wp_unslash($_POST['street_2'])) : '',
        'city' => isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '',
        'state' => isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '',
        'postal_code' => isset($_POST['postal_code']) ? sanitize_text_field(wp_unslash($_POST['postal_code'])) : '',
        'mobile' => isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : ''  // Only mobile, no phone
    ];
    
    // Required field validation
    $required_fields = ['street_1', 'city', 'state', 'postal_code', 'mobile'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return '<p class="error">' . ucfirst(str_replace('_', ' ', $field)) . ' is required.</p>';
        }
    }
    
    // ZIP code validation
    if (!empty($data['postal_code']) && !preg_match('/^\d{5}(-\d{4})?$/', $data['postal_code'])) {
        return '<p class="error">Please enter a valid ZIP code (5 or 9 digits).</p>';
    }
    
    // Mobile validation (required)
    if (!empty($data['mobile']) && !preg_match('/^\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}$/', $data['mobile'])) {
        return '<p class="error">Please enter a valid 10-digit mobile number.</p>';
    }
    
    // Update data in wp_usermeta
    $result = $this->employee_handler->update_employee_usermeta($user_id, $data);
    
    if ($result) {
        return '<p class="success">Your contact information has been updated successfully!</p>';
    } else {
        return '<p class="error">Failed to update information. Please try again or contact HR.</p>';
    }
}
    
    /**
     * Get US states array
     */
    private function get_us_states() {
        return [
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
            'DC' => 'District of Columbia',
            'AS' => 'American Samoa',
            'GU' => 'Guam',
            'MP' => 'Northern Mariana Islands',
            'PR' => 'Puerto Rico',
            'UM' => 'United States Minor Outlying Islands',
            'VI' => 'U.S. Virgin Islands'
        ];
    }
}