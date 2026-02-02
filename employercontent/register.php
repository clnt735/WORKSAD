<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create New Account - WorkMuna</title>
  <style>
    :root {
        --base-bg: #f1f4fa;
        --card-bg: #fff;
        --text-dark: #101828;
        --muted: #667085;
        --accent: #1f7bff;
        --accent-soft: #dbe7ff;
        --border: #e4e7ec;
        --radius-lg: 24px;
        --radius-md: 14px;
        font-family: "Poppins", "Segoe UI", Tahoma, sans-serif;
    }

    * {
        box-sizing: border-box;
    }

    body.register-page {
        margin: 0;
        min-height: 100vh;
        background: radial-gradient(circle at top, rgba(31, 123, 255, 0.08), transparent 50%), var(--base-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        color: var(--text-dark);
    }

    .register-box {
        width: min(520px, 100%);
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        box-shadow: 0 25px 70px rgba(15, 23, 42, 0.12);
        padding: clamp(1.25rem, 3vw, 2rem);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    h2 {
        margin: 0;
        font-size: clamp(1.4rem, 2vw, 1.75rem);
        text-align: center;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        position: relative;
    }

    .step-indicator {
        display: flex;
        justify-content: center;
        gap: 2rem;
        margin-bottom: 0.75rem;
    }

    .step-indicator div {
        display: flex;
        flex-direction: column;
        align-items: center;
        font-weight: 600;
        color: var(--muted);
        font-size: 0.8rem;
    }

    .step-dot {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.35rem;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }

    .step-indicator .active {
        color: var(--accent);
    }

    .step-indicator .active .step-dot {
        border-color: var(--accent);
        background: var(--accent);
        color: #fff;
        box-shadow: 0 10px 20px rgba(31, 123, 255, 0.3);
    }

    .form-step {
        display: none;
        flex-direction: column;
        gap: 0.75rem;
    }

    .form-step.active {
        display: flex;
    }

    .field-error {
      color: #ff4444;
      font-size: 11px;
      margin-top: 4px;
      display: none;
    }
    .input-error {
      border: 1px solid #ff4444 !important;
    }
    .input-valid {
      border: 1px solid #4CAF50 !important;
    }
    .password-wrapper {
      position: relative;
    }
    .password-wrapper input {
      width: 100%;
    }
    .password-strength {
      height: 3px;
      background: #e4e7ec;
      border-radius: 2px;
      margin-top: 6px;
      overflow: hidden;
      transition: all 0.3s ease;
    }
    .password-strength-bar {
      height: 100%;
      width: 0%;
      transition: all 0.3s ease;
      border-radius: 2px;
    }
    .strength-weak { width: 33%; background: #ff4444; }
    .strength-medium { width: 66%; background: #ff9800; }
    .strength-strong { width: 100%; background: #4CAF50; }
    .password-hint {
      font-size: 10px;
      color: #667085;
      margin-top: 5px;
      opacity: 0;
      transform: translateY(-5px);
      transition: all 0.2s ease;
    }
    .password-wrapper:focus-within .password-hint {
      opacity: 1;
      transform: translateY(0);
    }
    .show-password {
      position: absolute;
      right: 10px;
      top: 11px;
      background: none;
      border: none;
      color: #667085;
      cursor: pointer;
      font-size: 13px;
      padding: 5px;
      border-radius: 4px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .show-password:hover {
      background: #f1f4fa;
      color: #1f7bff;
    }
    .show-password svg {
      width: 16px;
      height: 16px;
    }
    .name-fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.6rem;
    }
    .name-fields > div {
        display: flex;
        flex-direction: column;
    }

    input,
    select,
    textarea {
        width: 100%;
        padding: 0.7rem 0.85rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        background: #fff;
        font: inherit;
        font-size: 0.95rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    input:focus,
    select:focus,
    textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(31, 123, 255, 0.2);
    }

    .label-small {
        display: block;
        font-size: 0.8rem;
        margin-bottom: 0.3rem;
        color: var(--muted);
    }

    .dob-selection {
        display: grid;
        grid-template-columns: minmax(140px, 1fr) repeat(2, minmax(100px, 1fr));
        gap: 0.8rem;
    }

    .gender-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
    }

    .gender-box {
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 0.65rem 0.85rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 600;
        background: #fff;
    }

    .gender-box input {
        width: auto;
        margin: 0;
    }

    .company-section {
        margin-top: 0.5rem;
        padding: 1rem;
        border-radius: var(--radius-lg);
        border: 1px solid var(--accent-soft);
        background: rgba(219, 231, 255, 0.35);
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .company-section h3 {
        margin: 0;
        font-size: 1.1rem;
    }

    textarea {
        resize: vertical;
    }

    .step-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.6rem;
        flex-wrap: wrap;
        margin-top: 0.5rem;
    }

    .nav-btn,
    .signup-btn {
        border: none;
        padding: 0.75rem 2rem;
        font-size: 0.95rem;
        font-weight: 600;
        border-radius: 999px;
        background: linear-gradient(135deg, #1f7bff, #1f7bff);
        color: #fff;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .nav-btn:hover,
    .signup-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(34, 197, 94, 0.35);
    }


    .login-link {
        text-align: center;
        margin: 0.5rem 0 0 0;
        font-size: 0.9rem;
    }

    .login-link a {
        color: var(--accent);
        font-weight: 600;
        text-decoration: none;
    }

    @media (max-width: 640px) {
        .register-box {
            border-radius: 18px;
            padding: 1.25rem;
        }

        .dob-selection {
            grid-template-columns: repeat(3, minmax(90px, 1fr));
        }
    }

    /* Verification Step Styles */
    .verification-section {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .verification-section h3 {
        margin: 0;
        font-size: 1.15rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .verification-icon {
        font-style: normal;
    }

    .verification-note {
        font-size: 0.9rem;
        color: var(--muted);
        margin: 0;
        line-height: 1.5;
    }

    .document-type-selection {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
    }

    .document-option {
        cursor: pointer;
    }

    .document-option input {
        display: none;
    }

    .option-card {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        border: 2px solid var(--border);
        border-radius: var(--radius-md);
        background: #fff;
        transition: all 0.2s ease;
    }

    .document-option input:checked + .option-card {
        border-color: var(--accent);
        background: var(--accent-soft);
    }

    .option-card:hover {
        border-color: var(--accent);
    }

    .option-icon {
        font-size: 1.5rem;
    }

    .option-text {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .option-text strong {
        font-size: 0.95rem;
    }

    .option-text small {
        font-size: 0.75rem;
        color: var(--muted);
    }

    .file-upload-area {
        position: relative;
        border: 2px dashed var(--border);
        border-radius: var(--radius-md);
        background: #fafbfc;
        transition: all 0.2s ease;
        min-height: 120px;
    }

    .file-upload-area:hover,
    .file-upload-area.dragover {
        border-color: var(--accent);
        background: var(--accent-soft);
    }

    .file-upload-area input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
        z-index: 2;
    }

    .upload-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        text-align: center;
        pointer-events: none;
    }

    .upload-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: var(--muted);
    }

    .upload-placeholder p {
        margin: 0;
        font-weight: 600;
        color: var(--text-dark);
    }

    .upload-placeholder small {
        color: var(--muted);
        font-size: 0.8rem;
    }

    .upload-preview {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding: 1rem;
        background: #f9fafb;
        border-radius: var(--radius-md);
        position: relative;
        z-index: 3;
    }

    .preview-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .preview-info {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .preview-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e5e7eb;
        border-radius: 8px;
        flex-shrink: 0;
    }

    .preview-icon svg {
        width: 24px;
        height: 24px;
        color: #6b7280;
    }

    .preview-details {
        flex: 1;
        min-width: 0;
    }

    .preview-name {
        font-weight: 600;
        font-size: 0.9rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--text-dark);
    }

    .preview-size {
        font-size: 0.75rem;
        color: var(--muted);
        margin-top: 2px;
    }

    .preview-image-container {
        width: 100%;
        max-height: 300px;
        overflow: hidden;
        border-radius: var(--radius-md);
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .preview-image {
        max-width: 100%;
        max-height: 300px;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: var(--radius-md);
    }

    .remove-file {
        background: #ff4444;
        color: #fff;
        border: none;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1rem;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .remove-file:hover {
        background: #cc0000;
        transform: scale(1.1);
    }

    .verification-info {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: var(--radius-md);
        padding: 1rem;
        font-size: 0.85rem;
    }

    .verification-info p {
        margin: 0 0 0.5rem 0;
    }

    .verification-info ul {
        margin: 0;
        padding-left: 1.25rem;
        color: var(--muted);
    }

    .verification-info li {
        margin-bottom: 0.25rem;
    }
  </style>
</head>

<body class="register-page">

<div class="register-box">

    <h2>Create a new account</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="background:#ffdddd; padding:10px; border-left:4px solid #ff4444; margin-bottom:15px;">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']); 
                ?>
            </div>
        <?php endif; ?>

    <form action="register_process.php" method="POST" enctype="multipart/form-data" id="employer-register-form">

        <div class="step-indicator">
            <div class="active" data-step="0">
                <span class="step-dot">1</span>
                Account Details
            </div>
            <div data-step="1">
                <span class="step-dot">2</span>
                Personal & Company Info
            </div>
            <div data-step="2">
                <span class="step-dot">3</span>
                Verification
            </div>
        </div>

        <div class="form-step active" data-step="0">
            <input type="email" name="email" id="email" placeholder="Email Address" required>
            <span class="field-error" id="email-error">Please enter a valid email address</span>

            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Password" required style="padding-right: 50px;">
                <button type="button" class="show-password" id="toggle-password" title="Show password">
                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg id="eye-slash-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                </button>
                <div class="password-strength">
                    <div class="password-strength-bar" id="strength-bar"></div>
                </div>
                <div class="password-hint" id="password-hint">
                    Use 8+ characters with uppercase, lowercase, number & symbol
                </div>
            </div>

            <div style="position: relative;">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>
                <span class="field-error" id="confirm-error">Passwords do not match</span>
            </div>
        </div>

        <div class="form-step" data-step="1">
            <div class="name-fields">
                <div>
                    <input type="text" name="fname" id="fname" placeholder="First name" required pattern="[A-Za-z\s]+" title="Only letters are allowed">
                    <span class="field-error" id="fname-error">Only letters allowed</span>
                </div>
                <div>
                    <input type="text" name="mname" id="mname" placeholder="Middle name (optional)" pattern="[A-Za-z\s]*" title="Only letters are allowed">
                    <span class="field-error" id="mname-error">Only letters allowed</span>
                </div>
                <div>
                    <input type="text" name="lname" id="lname" placeholder="Last name" required pattern="[A-Za-z\s]+" title="Only letters are allowed">
                    <span class="field-error" id="lname-error">Only letters allowed</span>
                </div>
            </div>

            <label>
                <span class="label-small">Business Name <strong>*</strong></span>
                <input type="text" name="company_name" placeholder="e.g., WorkMuna Farms" required>
            </label>
        </div>

        <div class="form-step" data-step="2">
            <div class="verification-section">
                <h3>Document Verification</h3>
                <p class="verification-note">To verify your employer account, please upload one of the following documents. Your registration will be reviewed by our team.</p>
                
                <div class="document-type-selection">
                    <label class="document-option">
                        <input type="radio" name="document_type" value="national_id">
                        <span class="option-card">
                            <span class="option-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:24px;height:24px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                </svg>
                            </span>
                            <span class="option-text">
                                <strong>National ID</strong>
                                <small>Valid government-issued ID</small>
                            </span>
                        </span>
                    </label>
                    <label class="document-option">
                        <input type="radio" name="document_type" value="business_permit">
                        <span class="option-card">
                            <span class="option-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:24px;height:24px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </span>
                            <span class="option-text">
                                <strong>Business Permit</strong>
                                <small>DTI/SEC registration or Mayor's permit</small>
                            </span>
                        </span>
                    </label>
                </div>

                <div class="file-upload-area" id="file-upload-area">
                    <input type="file" name="verification_document" id="verification_document" accept=".jpg,.jpeg,.png,.pdf">
                    <div class="upload-placeholder" id="upload-placeholder">
                        <span class="upload-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:48px;height:48px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </span>
                        <p>Click to upload or drag and drop</p>
                        <small>JPG, PNG, or PDF (max 5MB)</small>
                    </div>
                    <div class="upload-preview" id="upload-preview" style="display:none;">
                        <div class="preview-header">
                            <div class="preview-info">
                                <div class="preview-icon" id="preview-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div class="preview-details">
                                    <div class="preview-name" id="preview-name"></div>
                                    <div class="preview-size" id="preview-size"></div>
                                </div>
                            </div>
                            <button type="button" class="remove-file" id="remove-file">&times;</button>
                        </div>
                        <div class="preview-image-container" id="preview-image-container" style="display:none;">
                            <img class="preview-image" id="preview-image" alt="Document preview">
                        </div>
                    </div>
                </div>
                <span class="field-error" id="document-error">Please upload a valid document (JPG, PNG, or PDF, max 5MB)</span>

                <div class="verification-info">
                    <p><strong>What happens next?</strong></p>
                    <ul>
                        <li>Your account will be created with pending status</li>
                        <li>An administrator will review your document</li>
                        <li>You'll receive an email once approved</li>
                        <li>Review typically takes 1-2 business days</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="step-actions">
            <button type="button" class="nav-btn" id="step-next">Next</button>
            <button type="submit" class="signup-btn" id="final-submit" style="display:none;">Sign Up</button>
        </div>

    </form>

    <p class="login-link"><a href="login.php">Already have an account?</a></p>

</div>

<script>
    (function() {
        const steps = document.querySelectorAll('.form-step');
        const indicators = document.querySelectorAll('.step-indicator div');
        const nextBtn = document.getElementById('step-next');
        const submitBtn = document.getElementById('final-submit');
        let currentStep = 0;

        // File upload handling
        const fileInput = document.getElementById('verification_document');
        const uploadArea = document.getElementById('file-upload-area');
        const uploadPlaceholder = document.getElementById('upload-placeholder');
        const uploadPreview = document.getElementById('upload-preview');
        const previewName = document.getElementById('preview-name');
        const previewIcon = document.getElementById('preview-icon');
        const previewSize = document.getElementById('preview-size');
        const previewImageContainer = document.getElementById('preview-image-container');
        const previewImage = document.getElementById('preview-image');
        const removeFileBtn = document.getElementById('remove-file');
        const documentError = document.getElementById('document-error');

        const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function validateFile(file) {
            if (!file) return false;
            if (!allowedTypes.includes(file.type)) {
                documentError.textContent = 'Please upload a JPG, PNG, or PDF file';
                documentError.style.display = 'block';
                return false;
            }
            if (file.size > maxSize) {
                documentError.textContent = 'File size must be 5MB or less';
                documentError.style.display = 'block';
                return false;
            }
            documentError.style.display = 'none';
            return true;
        }

        function showFilePreview(file) {
            if (!validateFile(file)) {
                clearFileInput();
                return;
            }
            
            uploadPlaceholder.style.display = 'none';
            uploadPreview.style.display = 'flex';
            previewName.textContent = file.name;
            previewSize.textContent = formatFileSize(file.size);
            
            if (file.type === 'application/pdf') {
                // PDF file - show document icon
                previewIcon.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                `;
                previewImageContainer.style.display = 'none';
            } else {
                // Image file - show actual preview
                previewIcon.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                `;
                
                // Create image preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewImageContainer.style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        }

        function clearFileInput() {
            fileInput.value = '';
            uploadPlaceholder.style.display = 'flex';
            uploadPreview.style.display = 'none';
            previewName.textContent = '';
            previewSize.textContent = '';
            previewImageContainer.style.display = 'none';
            previewImage.src = '';
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    showFilePreview(this.files[0]);
                }
            });
        }

        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                clearFileInput();
            });
        }

        // Drag and drop
        if (uploadArea) {
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    fileInput.files = e.dataTransfer.files;
                    showFilePreview(e.dataTransfer.files[0]);
                }
            });
        }

        // Real-time validation for name fields (letters only)
        function validateNameField(input, errorId) {
            const errorSpan = document.getElementById(errorId);
            const regex = /^[A-Za-z\s]*$/;
            
            input.addEventListener('input', function() {
                if (!regex.test(this.value)) {
                    this.value = this.value.replace(/[^A-Za-z\s]/g, '');
                    errorSpan.style.display = 'block';
                    this.classList.add('input-error');
                } else {
                    errorSpan.style.display = 'none';
                    this.classList.remove('input-error');
                }
            });
        }

        // Password strength validation
        function validatePassword() {
            const password = document.getElementById('password').value;
            const passwordInput = document.getElementById('password');
            const strengthBar = document.getElementById('strength-bar');
            
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
            };
            
            const metCount = Object.values(requirements).filter(val => val === true).length;
            const isValid = metCount === 5;
            
            // Update strength bar
            strengthBar.className = 'password-strength-bar';
            if (password.length === 0) {
                strengthBar.className = 'password-strength-bar';
            } else if (metCount <= 2) {
                strengthBar.className = 'password-strength-bar strength-weak';
            } else if (metCount <= 4) {
                strengthBar.className = 'password-strength-bar strength-medium';
            } else {
                strengthBar.className = 'password-strength-bar strength-strong';
            }
            
            // Update input border
            if (password.length > 0) {
                if (isValid) {
                    passwordInput.classList.remove('input-error');
                    passwordInput.classList.add('input-valid');
                } else {
                    passwordInput.classList.remove('input-valid');
                    passwordInput.classList.add('input-error');
                }
            } else {
                passwordInput.classList.remove('input-error', 'input-valid');
            }
            
            return isValid;
        }
        
        // Toggle password visibility
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeSlashIcon = document.getElementById('eye-slash-icon');
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            
            if (type === 'password') {
                eyeIcon.style.display = 'block';
                eyeSlashIcon.style.display = 'none';
                this.setAttribute('title', 'Show password');
            } else {
                eyeIcon.style.display = 'none';
                eyeSlashIcon.style.display = 'block';
                this.setAttribute('title', 'Hide password');
            }
        });

        // Confirm password validation
        function validateConfirmPassword() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const errorSpan = document.getElementById('confirm-error');
            const confirmInput = document.getElementById('confirm_password');
            
            if (confirmPassword && password !== confirmPassword) {
                errorSpan.style.display = 'block';
                confirmInput.classList.add('input-error');
                return false;
            } else {
                errorSpan.style.display = 'none';
                confirmInput.classList.remove('input-error');
                return true;
            }
        }

        const showStep = (index) => {
            steps.forEach((step, idx) => {
                step.classList.toggle('active', idx === index);
            });
            indicators.forEach((indicator, idx) => {
                indicator.classList.toggle('active', idx <= index);
            });
            nextBtn.style.display = index === steps.length - 1 ? 'none' : 'inline-flex';
            submitBtn.style.display = index === steps.length - 1 ? 'inline-flex' : 'none';
        };

        const validateStep = (index) => {
            if (index === 0) {
                // Step 1 validation (Account Details)
                const isPasswordValid = validatePassword();
                const isConfirmValid = validateConfirmPassword();
                
                if (!isPasswordValid) {
                    alert('Password does not meet the requirements. Please check all password criteria.');
                    return false;
                }
                
                if (!isConfirmValid) {
                    alert('Passwords do not match. Please confirm your password.');
                    return false;
                }
            }
            
            if (index === 2) {
                // Step 3 validation (Verification)
                const documentType = document.querySelector('input[name="document_type"]:checked');
                if (!documentType) {
                    alert('Please select a document type.');
                    return false;
                }
                
                const file = fileInput.files[0];
                if (!file) {
                    alert('Please upload a verification document.');
                    return false;
                }
                
                if (!validateFile(file)) {
                    return false;
                }
            }
            
            const fields = steps[index].querySelectorAll('input[required], select[required], textarea[required]');
            for (const field of fields) {
                // Skip file input validation here as we handle it separately
                if (field.type === 'file') continue;
                if (!field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }
            return true;
        };

        nextBtn.addEventListener('click', () => {
            if (!validateStep(currentStep)) {
                return;
            }
            if (currentStep < steps.length - 1) {
                currentStep += 1;
                showStep(currentStep);
            }
        });

        // Initialize validation
        validateNameField(document.getElementById('fname'), 'fname-error');
        validateNameField(document.getElementById('mname'), 'mname-error');
        validateNameField(document.getElementById('lname'), 'lname-error');
        
        document.getElementById('password').addEventListener('input', validatePassword);
        document.getElementById('confirm_password').addEventListener('input', validateConfirmPassword);
        
        // Form submission validation
        document.getElementById('employer-register-form').addEventListener('submit', function(e) {
            const isPasswordValid = validatePassword();
            const isConfirmValid = validateConfirmPassword();
            
            if (!isPasswordValid) {
                e.preventDefault();
                alert('Password does not meet the requirements. Please check all password criteria.');
                return false;
            }
            
            if (!isConfirmValid) {
                e.preventDefault();
                alert('Passwords do not match. Please confirm your password.');
                return false;
            }
            
            // Validate verification document
            const documentType = document.querySelector('input[name="document_type"]:checked');
            if (!documentType) {
                e.preventDefault();
                alert('Please select a document type.');
                return false;
            }
            
            const file = fileInput.files[0];
            if (!file) {
                e.preventDefault();
                alert('Please upload a verification document.');
                return false;
            }
            
            if (!validateFile(file)) {
                e.preventDefault();
                return false;
            }
        });

        showStep(currentStep);
    })();
</script>

</body>
</html>
