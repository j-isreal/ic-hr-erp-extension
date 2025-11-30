jQuery(document).ready(function($) {
    'use strict';
    
    // Debug logging
    console.log('=== ICLLC HR ADMIN JS LOADED ===');
    console.log('icllc_hr_ajax object:', window.icllc_hr_ajax);

    // Initialize all event handlers
    initCheckboxHandlers();
    initApplicantDetailHandlers();
    initResumeDownloadHandler();
    initEmployeeCreationHandler();
    
    /**
     * Checkbox selection handlers
     */
    function initCheckboxHandlers() {
        // Select all checkboxes
        $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('tbody .check-column input[type="checkbox"]').prop('checked', isChecked);
        });
        
        // Individual checkbox - update "select all" state
        $('tbody .check-column input[type="checkbox"]').on('change', function() {
            var totalCheckboxes = $('tbody .check-column input[type="checkbox"]').length;
            var checkedCheckboxes = $('tbody .check-column input[type="checkbox"]:checked').length;
            var allChecked = totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes;
            
            $('#cb-select-all-1, #cb-select-all-2').prop('checked', allChecked);
        });
    }
    
    /**
     * Applicant detail modal handlers
     */
    function initApplicantDetailHandlers() {
        // View applicant details
        $(document).on('click', '.view-application', function(e) {
            e.preventDefault();
            showApplicantDetails($(this).data('applicant-data'));
        });
        
        // Close modal handlers
        $(document).on('click', '#close-modal, #close-modal-btn', function() {
            $('#applicant-modal').hide();
        });
        
        // Close modal when clicking outside
        $(document).on('click', '#applicant-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
    }
    
    /**
     * Show applicant details in modal
     */
    function showApplicantDetails(applicantData) {
        console.log('View application clicked', applicantData);
        
        if (!applicantData) {
            alert('Error loading applicant data');
            return;
        }
        
        // Parse JSON string if needed
        if (typeof applicantData === 'string') {
            try {
                applicantData = JSON.parse(applicantData);
            } catch (e) {
                console.error('Error parsing applicant data:', e);
                alert('Error loading applicant data');
                return;
            }
        }
        
        const detailsHtml = generateApplicantDetailsHtml(applicantData);
        $('#applicant-details').html(detailsHtml);
        $('#applicant-modal').show();
    }
    
    /**
     * Generate HTML for applicant details
     */
    function generateApplicantDetailsHtml(applicant) {
        const resumeSection = applicant.resume_filename ? 
            `<p><a href="#" class="download-resume-btn" data-applicant-id="${applicant.id}" class="button">Download Resume (PDF)</a></p>
             <p><small>File: ${applicant.resume_filename}</small></p>` : 
            '<p>No resume uploaded</p>';
        
        return `
            <div class="applicant-detail-section">
                <h3>Personal Information</h3>
                <p><strong>Name:</strong> ${applicant.first_name || 'Not provided'} ${applicant.last_name || ''}</p>
                <p><strong>Email:</strong> ${applicant.email || 'Not provided'}</p>
                <p><strong>Phone:</strong> ${applicant.phone || 'Not provided'}</p>
                <p><strong>State:</strong> ${applicant.state || 'Not provided'}</p>
                <p><strong>Date of Birth:</strong> ${applicant.date_of_birth || 'Not provided'}</p>
                <p><strong>Position:</strong> ${applicant.position || 'Not provided'}</p>
                <p><strong>Applied:</strong> ${applicant.created_at || 'Not provided'}</p>
            </div>
            
            <div class="applicant-detail-section">
                <h3>Cover Letter</h3>
                <div class="cover-letter-content">
                    ${applicant.cover_letter || 'No cover letter provided'}
                </div>
            </div>
            
            <div class="applicant-detail-section">
                <h3>Resume</h3>
                ${resumeSection}
            </div>
            
            <div class="applicant-detail-section">
                <h3>Application Status</h3>
                <p><strong>Current Status:</strong> <span style="text-transform: capitalize;">${applicant.status || 'pending'}</span></p>
            </div>
        `;
    }
    
    /**
     * Resume download handler
     */
    function initResumeDownloadHandler() {
        $(document).on('click', '.download-resume-btn', function(e) {
            e.preventDefault();
            downloadResume($(this).data('applicant-id'));
        });
    }
    
    /**
     * Download resume file
     */
    function downloadResume(applicantId) {
        if (typeof icllc_hr_ajax === 'undefined') {
            alert('AJAX not properly initialized. Please refresh the page.');
            return;
        }
        
        const nonce = icllc_hr_ajax.nonces.download_resume;
        const downloadUrl = `${icllc_hr_ajax.ajax_url}?action=download_resume&applicant_id=${applicantId}&nonce=${nonce}`;
        
        window.open(downloadUrl, '_blank');
    }
    
    /**
     * Employee creation handler
     */
    function initEmployeeCreationHandler() {
        $(document).on('click', '.create-employee', function(e) {
            e.preventDefault();
            createEmployeeFromApplicant($(this));
        });
    }
    
    /**
     * Create employee from approved applicant
     */
    function createEmployeeFromApplicant($button) {
        const applicantId = $button.data('applicant-id');
        
        console.log('Create employee clicked:', applicantId);
        
        if (typeof icllc_hr_ajax === 'undefined') {
            alert('AJAX not properly initialized. Please refresh the page.');
            return;
        }
        
        if (!confirm('Are you sure you want to create an employee account for this applicant?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Creating...');
        
        $.ajax({
            url: icllc_hr_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'create_employee_from_applicant',
                applicant_id: applicantId,
                nonce: icllc_hr_ajax.nonces.create_employee
            },
            success: function(response) {
                console.log('Create employee response:', response);
                handleEmployeeCreationResponse(response, $button);
            },
            error: function(xhr, status, error) {
                console.error('Create employee error:', error);
                handleEmployeeCreationError($button);
            }
        });
    }
    
    /**
     * Handle successful employee creation
     */
    function handleEmployeeCreationResponse(response, $button) {
        if (response.success) {
            alert('Employee created successfully! ' + response.data);
            location.reload();
        } else {
            alert('Error: ' + response.data);
            $button.prop('disabled', false).text('Create Employee');
        }
    }
    
    /**
     * Handle employee creation error
     */
    function handleEmployeeCreationError($button) {
        alert('AJAX error creating employee. Please check console.');
        $button.prop('disabled', false).text('Create Employee');
    }
});