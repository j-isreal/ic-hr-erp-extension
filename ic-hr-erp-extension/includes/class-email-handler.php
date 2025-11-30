<?php

class ICLLC_HR_Email_Handler {
    
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function init() {
        // Email related hooks can go here if needed
    }
    
    public function send_application_notification($application_data) {
        $settings = $this->config::get_settings();
        $urls = $this->config::get_urls();
        
        $to = $settings['admin_email'];
        $subject = 'New Job Application Received';
        
        $message = "<h2>A new job application has been submitted</h2>";
        $message .= "Name: {$application_data['first_name']} {$application_data['last_name']}<br>";
        $message .= "Email: {$application_data['email']}<br>";
        $message .= "Phone: {$application_data['phone']}<br>";
        $message .= "Position: {$application_data['position']}<br><br><hr>";

        $message .= "<b>Visit Applicant Management:</b> <a href='" . esc_url($urls['admin_applicants']) . "'>click here</a><br>";
        $message .= "<small>&copy; " . esc_html($settings['company_name']) . ". All rights reserved.</small><br>";

        // Set HTML content type and custom headers
        $reply_to = !empty($settings['reply_to_email']) ? $settings['reply_to_email'] : $settings['from_email'];
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
            'Reply-To: ' . $reply_to
        );
        
        wp_mail($to, $subject, $message, $headers);
    }
    
    public function send_welcome_email($user_id, $email) {
        $settings = $this->config::get_settings();
        $urls = $this->config::get_urls();
        
        $user = get_userdata($user_id);
        $to = $email;
        $subject = 'HR Portal Access / Acceso al Portal de RH';
        
        // HTML message with proper formatting
        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
                .content { padding: 20px; }
                .spanish-content { padding: 20px; font-size: 0.9em; color: #666; border-top: 1px solid #eee; margin-top: 20px; }
                .button { display: inline-block; padding: 12px 24px; background: #007cba; color: white; 
                         text-decoration: none; border-radius: 4px; margin: 15px 0; }
                .footer { margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 5px; 
                         font-size: 14px; color: #666; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; 
                          padding: 12px; margin: 15px 0; color: #856404; }
                .link-box { background: #f1f1f1; padding: 15px; border-radius: 4px; 
                           margin: 15px 0; word-break: break-all; font-family: monospace; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Welcome to HR Portal</h2>
                </div>
                <div class="content">
                    <p>Hello <strong>' . esc_html($user->first_name) . '</strong>,</p>
                    
                    <p>Your HR Portal account has been successfully created. You will use this portal to sign important documents, view your paystubs, and keep your personal information up to date.</p>
     
                    <p>Use your applicant email, ' . esc_html($email) . ' to login. You will need to set your password - see below.</p>

                    
                    <p style="text-align: center;">
                        <a href="' . esc_url($urls['hr_portal']) . '" class="button">Access Your HR Portal</a>
                    </p>
                    
                    <p><strong>Portal URL:</strong> ' . esc_url($urls['hr_portal']) . '</p>
                    
                    <p><b>To set your password,</b> please click the <em><b><a href="' . esc_url($urls['lost_password']) . '">Lost your password?</a></b></em> link under the login box when you first access the portal. Enter the email you used during your application (shown above) and a secure link will be sent to you to set your password.</p>
                    
                    <p>If you have any questions or need assistance, please contact the HR department.</p>
                </div>
                
                <!-- Spanish Version -->
                <div class="spanish-content">
                    <p>Hola <strong>' . esc_html($user->first_name) . '</strong>,</p>
                    
                    <p>Su cuenta del Portal de RH ha sido creada exitosamente. Utilizará este portal para firmar documentos importantes, ver sus talones de pago y mantener su información personal actualizada.</p>
     
                    <p>Use su correo electrónico de solicitante, ' . esc_html($email) . ' para iniciar sesión. Necesitará establecer su contraseña; consulte a continuación.</p>

                    
                    <p style="text-align: center;">
                        <a href="' . esc_url($urls['hr_portal']) . '" class="button">Acceder a Su Portal de RH</a>
                    </p>
                    
                    <p><strong>URL del Portal:</strong> ' . esc_url($urls['hr_portal']) . '</p>
                    
                    <p><b>Para establecer su contraseña,</b> haga clic en el enlace <em><b><a href="' . esc_url($urls['lost_password']) . '">¿Perdió su contraseña?</a></b></em> debajo del cuadro de inicio de sesión cuando acceda al portal por primera vez. Ingrese el correo electrónico que utilizó durante su solicitud (mostrado arriba) y se le enviará un enlace seguro para establecer su contraseña.</p>
                    
                    <p>Si tiene alguna pregunta o necesita asistencia, por favor contacte al departamento de RH.</p>
                </div>
                
                <div class="footer">
                    <p><b>Best regards / Atentamente,</b><br>
                    Your HR Team</p><p>&nbsp;</p>
                    <p><small>Email sent from <a href="' . esc_url(home_url()) . '" target="_blank">' . esc_html($settings['company_name']) . '</a>. &nbsp;|&nbsp; <a href="' . esc_url(home_url('/privacy')) . '" target="_blank">Privacy Policy</a></small></p>
                </div>
            </div>
        </body>
        </html>';
        
        // Set HTML content type and custom headers
        $reply_to = !empty($settings['reply_to_email']) ? $settings['reply_to_email'] : $settings['from_email'];
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
            'Reply-To: ' . $reply_to
        );
        
        // Send the email
        wp_mail($to, $subject, $message, $headers);
    }
    
    public function send_applicant_confirmation_email($application_data) {
        $settings = $this->config::get_settings();
        
        $to = $application_data['email'];
        $subject = 'Application Received / Solicitud Recibida';
        
        $message = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
                .content { padding: 20px; }
                .spanish-content { padding: 20px; font-size: 0.9em; color: #666; border-top: 1px solid #eee; margin-top: 20px; }
                .button { display: inline-block; padding: 12px 24px; background: #007cba; color: white; 
                         text-decoration: none; border-radius: 4px; margin: 15px 0; }
                .footer { margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 5px; 
                         font-size: 14px; color: #666; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; 
                          padding: 12px; margin: 15px 0; color: #856404; }
                .link-box { background: #f1f1f1; padding: 15px; border-radius: 4px; 
                           margin: 15px 0; word-break: break-all; font-family: monospace; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Thanks for Applying!</h2>
                </div>
                
                <div class="content">
                    <p><b>Hello, ' . esc_html($application_data['first_name']) . ' ' . esc_html($application_data['last_name']) . '!</b></p>
                    
                    <p>We\'ve received your application for Work-at-Home opportunities with ' . esc_html($settings['company_name']) . '. Congratulations on taking the first step in partnering with us for your future!</p>
                    <p>We will review your application and contact you shortly with the next steps in our onboarding process.<br><br><em>Please read any future emails in their entirety.</em> They will contain very important information regarding our requirements and the next steps in the onboarding process.<br></p>
                </div>
                
                <!-- Spanish Version -->
                <div class="spanish-content">
                    <p><b>¡Hola, ' . esc_html($application_data['first_name']) . ' ' . esc_html($application_data['last_name']) . '!</b></p>
                    
                    <p>Hemos recibido su solicitud para oportunidades de Trabajo desde el Hogar con ' . esc_html($settings['company_name']) . '. ¡Felicidades por dar el primer paso para asociarse con nosotros para su futuro!</p>
                    <p>Revisaremos su solicitud y nos pondremos en contacto con usted pronto con los siguientes pasos en nuestro proceso de incorporación.<br><br><em>Por favor, lea cualquier correo electrónico futuro en su totalidad.</em> Contendrán información muy importante sobre nuestros requisitos y los próximos pasos en el proceso de incorporación.<br></p>
                </div>
                
                <div class="footer">
                    <p><b>Best regards (Atentamente),</b><br>
                    Your HR Team</p><p>&nbsp;</p>
                    <p><small>Email sent from <a href="' . esc_url(home_url()) . '" target="_blank">' . esc_html($settings['company_name']) . '</a>. &nbsp;|&nbsp; <a href="' . esc_url(home_url('/privacy')) . '" target="_blank">Privacy Policy</a></small></p>
                </div>
            </div>
        </body>
        </html>';
        
        // Set HTML content type and custom headers
        $reply_to = !empty($settings['reply_to_email']) ? $settings['reply_to_email'] : $settings['from_email'];
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
            'Reply-To: ' . $reply_to
        );
        
        // Send the email
        $result = wp_mail($to, $subject, $message, $headers);
        
        // Log the result for debugging
        //if ($result) {
        //    error_log('ICLLC HR: Confirmation email sent successfully to: ' . $application_data['email']);
        //} else {
        //    error_log('ICLLC HR: Failed to send confirmation email to: ' . $application_data['email']);
        //}
        
        return $result;
    }
}