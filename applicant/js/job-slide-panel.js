/**
 * Job Slide Panel - Shared functionality for viewing job details
 * Used by: application.php, interactions.php, search_jobs.php
 */

let jobSlidePanel = null;
let jobSlideOverlay = null;

/**
 * Initialize the job slide panel
 */
function initJobSlidePanel() {
    jobSlideOverlay = document.getElementById('jobSlideOverlay');
    jobSlidePanel = document.getElementById('jobSlidePanel');

    if (!jobSlideOverlay || !jobSlidePanel) {
        console.error('Job slide panel elements not found');
        return false;
    }

    // Close panel when clicking overlay
    jobSlideOverlay.addEventListener('click', closeJobSlidePanel);

    // Close panel when clicking close button
    const closeBtn = document.querySelector('.slide-panel-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeJobSlidePanel);
    }

    // Close panel with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && jobSlidePanel.classList.contains('active')) {
            closeJobSlidePanel();
        }
    });

    // Prevent panel from closing when clicking inside
    jobSlidePanel.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    return true;
}

/**
 * Open job slide panel with job details
 * @param {number} jobId - The job post ID
 */
function openJobSlidePanel(jobId) {
    if (!jobSlidePanel || !jobSlideOverlay) {
        console.error('Job slide panel not initialized');
        return;
    }

    // Show loading state
    showLoadingInPanel();
    
    // Show panel with animation
    jobSlideOverlay.classList.add('active');
    jobSlidePanel.classList.add('active');
    
    // Prevent body scrolling
    document.body.style.overflow = 'hidden';

    // Fetch job data from database
    fetch(`get_job_details.php?job_post_id=${jobId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateJobSlidePanel(data.job);
            } else {
                showErrorInPanel(data.error || 'Failed to load job details');
            }
        })
        .catch(error => {
            console.error('Error fetching job details:', error);
            showErrorInPanel('Network error occurred');
        });
}

/**
 * Close job slide panel
 */
function closeJobSlidePanel() {
    if (!jobSlidePanel || !jobSlideOverlay) return;

    jobSlideOverlay.classList.remove('active');
    jobSlidePanel.classList.remove('active');
    
    // Restore body scrolling
    document.body.style.overflow = '';
}

/**
 * Show loading state in panel
 */
function showLoadingInPanel() {
    const content = jobSlidePanel.querySelector('.slide-panel-content');
    if (content) {
        content.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 300px; flex-direction: column; gap: 16px;">
                <div style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <p style="color: #6b7280; font-size: 14px;">Loading job details...</p>
            </div>
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        `;
    }
}

/**
 * Show error message in panel
 */
function showErrorInPanel(message) {
    const content = jobSlidePanel.querySelector('.slide-panel-content');
    if (content) {
        content.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 300px; flex-direction: column; gap: 16px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444;"></i>
                <p style="color: #374151; font-size: 16px; text-align: center;">${message}</p>
                <button class="slide-btn secondary" onclick="closeJobSlidePanel()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        `;
    }
}

/**
 * Populate slide panel with job data
 */
function populateJobSlidePanel(jobData) {
    try {
        // Update header title
        const headerTitle = jobSlidePanel.querySelector('.slide-panel-header h2');
        if (headerTitle) {
            headerTitle.textContent = jobData.job_post_name || 'Job Details';
        }

        // Generate job details content
        const content = jobSlidePanel.querySelector('.slide-panel-content');
        if (content) {
            content.innerHTML = generateJobDetailsHTML(jobData);
        }
    } catch (error) {
        console.error('Error populating slide panel:', error);
        showErrorInPanel('Error displaying job details');
    }
}

/**
 * Generate HTML for job details
 */
function generateJobDetailsHTML(job) {
    const formatSalary = (budget) => {
        if (!budget) return 'Salary negotiable';
        return `₱${parseFloat(budget).toLocaleString()}`;
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    const formatRequirements = (requirements) => {
        if (!requirements) return '<li>No specific requirements listed</li>';
        const reqList = requirements.split('\n').filter(req => req.trim());
        return reqList.map(req => `<li>${req.trim()}</li>`).join('');
    };

    const formatSkills = (skills) => {
        if (!skills) return '<span class="slide-skill-tag">Not specified</span>';
        const skillList = skills.split(',').map(s => s.trim()).filter(s => s);
        return skillList.map(skill => `<span class="slide-skill-tag">${skill}</span>`).join('');
    };

    // Handle company logo path - remove leading slash if present and prepend ../
    const logoSrc = job.company_logo ? 
        (job.company_logo.startsWith('http') ? job.company_logo : `../${job.company_logo.replace(/^\/+/, '')}`) :
        '../assets/company-placeholder.png';

    return `
        <!-- Job Header -->
        <div class="slide-job-header">
            <img src="${logoSrc}" alt="${job.company_name || 'Company'} logo" class="slide-company-logo" 
                 onerror="this.src='../images/default-company.png'">
            <div class="slide-job-info">
                <h3>${job.job_post_name || 'Job Position'}</h3>
                <span class="slide-company-name">${job.company_name || 'Company Name'}</span>
                ${job.match_score > 0 ? `
                <div class="slide-match-badge">
                    <i class="fas fa-star"></i>${Math.round(job.match_score)}% Match
                </div>` : ''}
            </div>
        </div>

        <!-- Quick Info Grid -->
        <div class="slide-job-details">
            <div class="slide-detail-grid">
                <div class="slide-detail-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>${job.job_location_name || job.city || 'Location TBD'}</span>
                </div>
                <div class="slide-detail-item">
                    <i class="fas fa-peso-sign"></i>
                    <span>${formatSalary(job.budget)}</span>
                </div>
                <div class="slide-detail-item">
                    <i class="fas fa-briefcase"></i>
                    <span>${job.job_category_name || 'General'}</span>
                </div>
                <div class="slide-detail-item">
                    <i class="fas fa-clock"></i>
                    <span>${job.job_type_name || 'Full-time'}</span>
                </div>
                <div class="slide-detail-item">
                    <i class="fas fa-users"></i>
                    <span>${job.vacancies || 1} Position(s)</span>
                </div>
                <div class="slide-detail-item">
                    <i class="fas fa-laptop"></i>
                    <span>${job.work_setup_name || 'On-site'}</span>
                </div>
            </div>
        </div>

        <!-- Job Description -->
        <div class="slide-job-description">
            <h4><i class="fas fa-file-alt"></i> Job Description</h4>
            <p>${job.job_description || 'No description provided.'}</p>
        </div>

        <!-- Requirements -->
        <div class="slide-job-requirements">
            <h4><i class="fas fa-list-check"></i> Requirements</h4>
            <ul>${formatRequirements(job.requirements)}</ul>
        </div>

        <!-- Required Skills -->
        <div class="slide-skills-section">
            <h4><i class="fas fa-cogs"></i> Required Skills</h4>
            <div class="slide-skills-tags">
                ${formatSkills(job.required_skills)}
            </div>
        </div>

        <!-- Benefits -->
        ${job.benefits ? `
        <div class="slide-benefits-section">
            <h4><i class="fas fa-gift"></i> Benefits</h4>
            <p>${job.benefits}</p>
        </div>` : ''}

        <!-- Actions -->
        <div class="slide-panel-actions">
            <button class="slide-btn secondary" onclick="closeJobSlidePanel()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    `;
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initJobSlidePanel);
} else {
    initJobSlidePanel();
}
