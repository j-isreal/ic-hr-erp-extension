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
                            <label for="phone">Phone *</label>
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
                                <option value="AL">Alabama</option>
                                <option value="AK">Alaska</option>
                                <option value="AZ">Arizona</option>
                                <option value="AR">Arkansas</option>
                                <option value="CA">California</option>
                                <option value="CO">Colorado</option>
                                <option value="CT">Connecticut</option>
                                <option value="DE">Delaware</option>
                                <option value="FL">Florida</option>
                                <option value="GA">Georgia</option>
                                <option value="HI">Hawaii</option>
                                <option value="ID">Idaho</option>
                                <option value="IL">Illinois</option>
                                <option value="IN">Indiana</option>
                                <option value="IA">Iowa</option>
                                <option value="KS">Kansas</option>
                                <option value="KY">Kentucky</option>
                                <option value="LA">Louisiana</option>
                                <option value="ME">Maine</option>
                                <option value="MD">Maryland</option>
                                <option value="MA">Massachusetts</option>
                                <option value="MI">Michigan</option>
                                <option value="MN">Minnesota</option>
                                <option value="MS">Mississippi</option>
                                <option value="MO">Missouri</option>
                                <option value="MT">Montana</option>
                                <option value="NE">Nebraska</option>
                                <option value="NV">Nevada</option>
                                <option value="NH">New Hampshire</option>
                                <option value="NJ">New Jersey</option>
                                <option value="NM">New Mexico</option>
                                <option value="NY">New York</option>
                                <option value="NC">North Carolina</option>
                                <option value="ND">North Dakota</option>
                                <option value="OH">Ohio</option>
                                <option value="OK">Oklahoma</option>
                                <option value="OR">Oregon</option>
                                <option value="PA">Pennsylvania</option>
                                <option value="RI">Rhode Island</option>
                                <option value="SC">South Carolina</option>
                                <option value="SD">South Dakota</option>
                                <option value="TN">Tennessee</option>
                                <option value="TX">Texas</option>
                                <option value="UT">Utah</option>
                                <option value="VT">Vermont</option>
                                <option value="VA">Virginia</option>
                                <option value="WA">Washington</option>
                                <option value="WV">West Virginia</option>
                                <option value="WI">Wisconsin</option>
                                <option value="WY">Wyoming</option>
                                <option value="DC">District of Columbia</option>
                                <option value="AS">American Samoa</option>
                                <option value="GU">Guam</option>
                                <option value="MP">Northern Mariana Islands</option>
                                <option value="PR">Puerto Rico</option>
                                <option value="UM">United States Minor Outlying Islands</option>
                                <option value="VI">U.S. Virgin Islands</option>
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
        
        // Check if user has employee role
        if (!in_array('employee', $current_user->roles)) {
            return '<p>Access denied. This portal is for employees only.</p>';
        }
        
        ob_start();
        ?>
        <div class="employee-portal">
            <header class="portal-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2>HR Portal</h2>
                        <p>Welcome, <?php echo esc_html($current_user->display_name); ?>!</p>
                    </div>
                    <div>
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="button" style="text-decoration: none;">
                            Log Out
                        </a>
                    </div>
                </div>
            </header>
            
            <div class="portal-sections">
                <div class="portal-section">
                    <h3>Your Paystubs</h3>
                    <div class="paystub-list">
                        <?php $this->employee_handler->display_employee_paystubs($current_user->ID); ?>
                    </div>
                </div>
                
                <div class="portal-section">
                    <h3>Documents to Sign</h3>
                    <div class="documents-list">
                        <?php $this->employee_handler->display_pending_documents($current_user->ID); ?>
                    </div>
                </div>
                
                <div class="portal-section">
                    <h3>Your Information</h3>
                    <div class="employee-info">
                        <?php $this->employee_handler->display_employee_info($current_user->ID); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .employee-portal { max-width: 1200px; margin: 0 auto; }
        .portal-header { background: #f8f9fa; padding: 2rem; margin-bottom: 2rem; }
        .portal-sections { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .portal-section { border: 1px solid #ddd; padding: 1.5rem; }
        .portal-section h3 { margin-top: 0; border-bottom: 2px solid #0073aa; padding-bottom: 0.5rem; }
        </style>
        <?php
        return ob_get_clean();
    }
}