<?php 
include "post_job_data.php";

$activePage = 'post_job';

$job_category_groups = [];
if (!empty($job_categories)) {
	foreach ($job_categories as $category) {
		$industryKey = isset($category['industry_id']) ? (int)$category['industry_id'] : 0;
		if ($industryKey <= 0) {
			continue;
		}
		if (!array_key_exists($industryKey, $job_category_groups)) {
			$job_category_groups[$industryKey] = [];
		}
		$job_category_groups[$industryKey][] = [
			'id' => (int)$category['job_category_id'],
			'name' => $category['job_category_name']
		];
	}
}

$skill_groups = [];
if (!empty($skills)) {
	foreach ($skills as $skill) {
		$categoryKey = isset($skill['job_category_id']) ? (int)$skill['job_category_id'] : 0;
		if ($categoryKey <= 0) {
			continue;
		}
		if (!array_key_exists($categoryKey, $skill_groups)) {
			$skill_groups[$categoryKey] = [];
		}
		$skill_groups[$categoryKey][] = [
			'id' => (int)$skill['skill_id'],
			'name' => $skill['name']
		];
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Post a Job - WorkMuna</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<style>
		* {
			box-sizing: border-box;
		}
		
		body {
			margin: 0;
			padding: 0;
			background: #f5f7fb;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
		}

		/* Isolated styles for job wizard only */
		.job-wizard-container {
			--wizard-bg: #f5f7fb;
			--wizard-border: #e2e8f0;
			--wizard-primary: #1f7bff;
			--wizard-muted: #64748b;
			--wizard-surface: #fff;
		}

		.job-wizard-container .job-wizard {
			min-height: calc(100vh - 80px);
			background: var(--wizard-bg);
			padding: 1.5rem 1rem 3rem;
			font-family: "Roboto", "Segoe UI", Tahoma, sans-serif;
		}
		
		@media (min-width: 601px) {
			.job-wizard-container .job-wizard {
				padding: 1.5rem 2rem 3rem;
			}
		}
		
		@media (min-width: 1024px) {
			.job-wizard-container .job-wizard {
				padding: 1.5rem clamp(2rem, 6vw, 4rem) 3rem;
			}
		}

		.job-wizard-container .job-wizard > section {
			max-width: 1080px;
			margin-left: auto;
			margin-right: auto;
			width: 100%;
		}
		
		.job-wizard-container .job-wizard__header-container {
			background: var(--wizard-surface);
			padding: 1.25rem 1.25rem;
			border-radius: 0.875rem;
			box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
			display: flex;
			flex-direction: column;
			gap: 1.5rem;
		}
		
		@media (min-width: 769px) {
			.job-wizard-container .job-wizard__header-container {
				display: grid;
				grid-template-columns: 1fr 2fr;
				gap: 1.75rem;
				align-items: center;
				padding: 1.5rem 2rem;
				border-radius: 1rem;
			}
		}

		.job-wizard-container .job-wizard__intro {
			margin-bottom: 0;
		}
		
		@media (min-width: 769px) {
			.job-wizard-container .job-wizard__intro {
				padding-right: 1.25rem;
				border-right: 2px solid var(--wizard-border);
			}
		}

		.job-wizard-container .job-wizard__intro-text {
			max-width: 720px;
		}

		.job-wizard-container .job-wizard__intro-text .eyebrow {
			text-transform: uppercase;
			letter-spacing: 0.08em;
			font-size: 0.7rem;
			color: var(--wizard-muted);
			margin: 0 0 0.5rem;
		}

		.job-wizard-container .job-wizard__intro-text h1 {
			margin: 0;
			font-size: clamp(1.35rem, 2.2vw, 1.75rem);
			color: #0f172a;
			line-height: 1.2;
		}

		.job-wizard-container .job-wizard__intro-text .subtitle {
			margin: 0.35rem 0 0;
			color: #475569;
			font-size: 0.95rem;
			line-height: 1.4;
		}

		.job-wizard-container .job-wizard__progress {
			margin-top: 0;
			background: transparent;
			padding: 0;
			border-radius: 0;
			box-shadow: none;
		}
		
		@media (min-width: 601px) {
			.job-wizard-container .job-wizard__progress {
				margin-top: 0;
				padding: 0;
				border-radius: 0;
			}
		}

		.job-wizard-container .progress-steps {
			list-style: none;
			margin: 0;
			padding: 0;
			display: flex;
			flex-direction: row;
			gap: 0.5rem;
			width: 100%;
		}
		
		@media (min-width: 769px) {
			.job-wizard-container .progress-steps {
				gap: 0.65rem;
			}
		}

		.job-wizard-container .progress-step {
			display: flex;
			flex-direction: column;
			gap: 0.5rem;
			align-items: center;
			padding: 0.75rem 0.5rem;
			border-radius: 0.625rem;
			color: var(--wizard-muted);
			transition: background 0.2s ease;
			flex: 1;
			text-align: center;
		}
		
		@media (min-width: 769px) {
			.job-wizard-container .progress-step {
				padding: 0.75rem 1rem;
			}
		}

		.job-wizard-container .progress-step .step-index {
			width: 36px;
			height: 36px;
			border-radius: 999px;
			background: #e2e8f0;
			color: #0f172a;
			display: grid;
			place-items: center;
			font-weight: 600;
			font-size: 0.9rem;
			flex-shrink: 0;
		}

		.job-wizard-container .progress-step .step-title {
			margin: 0;
			font-weight: 600;
			color: #0f172a;
			font-size: 0.95rem;
			line-height: 1.3;
		}

		.job-wizard-container .progress-step .step-caption {
			margin: 0.15rem 0 0;
			font-size: 0.8rem;
			color: var(--wizard-muted);
			line-height: 1.3;
		}

		.job-wizard-container .progress-step.is-active {
			background: rgba(31, 123, 255, 0.08);
			color: var(--wizard-primary);
		}

		.job-wizard-container .progress-step.is-active .step-index {
			background: #2fbd67;
			color: #fff;
		}

		.job-wizard-container .job-wizard__form {
			margin-top: 1.25rem;
			background: var(--wizard-surface);
			padding: 1.5rem 1rem;
			border-radius: 0.875rem;
			box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
		}
		
		@media (min-width: 601px) {
			.job-wizard-container .job-wizard__form {
				margin-top: 1.5rem;
				padding: 1.75rem 2rem;
				border-radius: 1rem;
				box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
			}
		}

		.job-wizard-container .step-heading h3 {
			margin: 0;
			color: #0f172a;
			font-size: 1.25rem;
		}

		.job-wizard-container .step-heading p {
			margin: 0.35rem 0 0;
			color: #475569;
		}

		.job-wizard-container .wizard-step {
			display: none;
			flex-direction: column;
			gap: 1.75rem;
		}

		.job-wizard-container .wizard-step.is-active {
			display: flex;
		}

		.job-wizard-container .wizard-step.is-inline-edit {
			display: block;
			padding-top: 1rem;
		}

		.job-wizard-container .review-summary {
			display: grid;
			grid-template-columns: 1fr;
			gap: 1.25rem;
		}
		
		@media (min-width: 769px) {
			.job-wizard-container .review-summary {
				grid-template-columns: 1fr 1fr;
				gap: 1.5rem;
			}
		}

		.job-wizard-container .summary-section {
			border: 1px solid var(--wizard-border);
			border-radius: 0.875rem;
			padding: 1.25rem 1.25rem;
			background: #fff;
			box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
			transition: box-shadow 0.2s ease;
		}
		
		.job-wizard-container .summary-section:hover {
			box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
		}
		
		/* Mobile: Standard view without accordion */
		@media (max-width: 768px) {
			.job-wizard-container .summary-section {
				padding: 1.25rem 1rem;
			}
			
			.job-wizard-container .summary-header {
				padding-bottom: 0.75rem;
				margin-bottom: 1rem;
			}
			
			.job-wizard-container .summary-list {
				padding: 0;
			}
		}

		.job-wizard-container .summary-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			margin-bottom: 1.25rem;
			padding-bottom: 0.75rem;
			border-bottom: 2px solid var(--wizard-border);
		}

		.job-wizard-container .summary-header h4 {
			margin: 0;
			font-size: 1.1rem;
			font-weight: 700;
			color: #0f172a;
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}
		
		.job-wizard-container .summary-header h4::before {
			content: '';
			width: 4px;
			height: 20px;
			background: linear-gradient(135deg, var(--wizard-primary), #2fbd67);
			border-radius: 2px;
		}

		.job-wizard-container .summary-edit {
			border: 1px solid var(--wizard-border);
			background: transparent;
			color: #64748b;
			border-radius: 0.5rem;
			padding: 0.4rem 0.9rem;
			font-size: 0.875rem;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s ease;
			flex-shrink: 0;
		}

		.job-wizard-container .summary-edit:hover,
		.job-wizard-container .summary-edit:focus-visible {
			background: var(--wizard-primary);
			color: #fff;
			border-color: var(--wizard-primary);
			outline: none;
			transform: translateY(-1px);
		}

		.job-wizard-container .summary-list {
			display: flex;
			flex-direction: column;
			gap: 0.875rem;
			margin: 0;
			padding: 0;
		}

		.job-wizard-container .summary-list div {
			display: grid;
			grid-template-columns: 140px 1fr;
			gap: 1rem;
			align-items: start;
			padding: 0.625rem 0;
			border-bottom: 1px solid rgba(226, 232, 240, 0.6);
		}
		
		.job-wizard-container .summary-list div:last-child {
			border-bottom: none;
		}
		
		@media (max-width: 600px) {
			.job-wizard-container .summary-list {
				gap: 0.625rem;
			}
			
			.job-wizard-container .summary-list div {
				grid-template-columns: 1fr;
				gap: 0.25rem;
				padding: 0.5rem 0;
			}
		}

		.job-wizard-container .summary-list dt {
			font-size: 0.875rem;
			font-weight: 600;
			color: #64748b;
			margin: 0;
			text-align: left;
		}

		.job-wizard-container .summary-list dd {
			margin: 0;
			font-size: 0.95rem;
			line-height: 1.5;
			color: #0f172a;
			font-weight: 500;
			word-break: break-word;
		}

		/* Modal Overlay */
		.job-wizard-container .modal-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(15, 23, 42, 0.6);
			backdrop-filter: blur(4px);
			z-index: 9998;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 1rem;
			animation: fadeIn 0.2s ease;
		}

		.job-wizard-container .modal-overlay[hidden] {
			display: none;
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
			}
			to {
				opacity: 1;
			}
		}

		@keyframes slideUp {
			from {
				transform: translateY(20px);
				opacity: 0;
			}
			to {
				transform: translateY(0);
				opacity: 1;
			}
		}

		/* Modal Container */
		.job-wizard-container .inline-editor {
			background: #fff;
			border-radius: 1rem;
			box-shadow: 0 20px 60px rgba(15, 23, 42, 0.3);
			max-width: 900px;
			width: 100%;
			max-height: 90vh;
			display: flex;
			flex-direction: column;
			animation: slideUp 0.3s ease;
			position: relative;
			z-index: 9999;
		}

		@media (max-width: 768px) {
			.job-wizard-container .inline-editor {
				max-height: 95vh;
				border-radius: 1rem 1rem 0 0;
				margin: auto 0 0 0;
			}
			
			.job-wizard-container .modal-overlay {
				align-items: flex-end;
				padding: 0;
			}
		}

		.job-wizard-container .inline-editor__header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 1rem;
			padding: 1.5rem 1.5rem 1rem;
			border-bottom: 2px solid #e2e8f0;
			flex-shrink: 0;
		}

		.job-wizard-container .inline-editor__eyebrow {
			text-transform: uppercase;
			font-size: 0.75rem;
			letter-spacing: 0.1em;
			color: var(--wizard-muted);
			margin: 0 0 0.3rem;
		}

		.job-wizard-container .inline-editor__title {
			margin: 0;
			color: #0f172a;
			font-size: 1.35rem;
			font-weight: 600;
		}

		.job-wizard-container .inline-editor__body {
			padding: 1.5rem;
			overflow-y: auto;
			flex: 1;
		}

		.job-wizard-container .inline-editor__body .wizard-step {
			display: block;
			padding: 0;
		}

		.job-wizard-container .inline-editor__body .step-heading {
			display: none;
		}

		.job-wizard-container .inline-editor__footer {
			padding: 1rem 1.5rem;
			border-top: 2px solid #e2e8f0;
			display: flex;
			justify-content: flex-end;
			gap: 0.75rem;
			flex-shrink: 0;
			background: #f8f9fc;
		}

		.job-wizard-container .modal-close-btn {
			background: transparent;
			border: none;
			color: #64748b;
			font-size: 1.5rem;
			cursor: pointer;
			padding: 0.25rem;
			width: 2rem;
			height: 2rem;
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: 0.375rem;
			transition: all 0.2s ease;
		}

		.job-wizard-container .modal-close-btn:hover {
			background: #f1f5f9;
			color: #0f172a;
		}

		.job-wizard-container .step-placeholder {
			display: none;
		}

		.job-wizard-container .job-wizard__form header h2 {
			margin: 0;
			color: #0f172a;
		}

		.job-wizard-container .job-wizard__form header p {
			margin: 0.5rem 0 0;
			color: #475569;
		}

		.job-wizard-container .job-details-form {
			margin-top: 2rem;
			display: flex;
			flex-direction: column;
			gap: 1.75rem;
		}
		
		.job-wizard-container .form-section {
			display: flex;
			flex-direction: column;
			gap: 1.5rem;
		}
		
		.job-wizard-container .form-section + .form-section {
			padding-top: 2rem;
			border-top: 2px solid var(--wizard-border);
			margin-top: 1rem;
		}
		
		.job-wizard-container .form-section-header {
			display: flex;
			align-items: center;
			gap: 0.75rem;
			margin-bottom: 0.5rem;
		}
		
		.job-wizard-container .form-section-header::before {
			content: '';
			width: 4px;
			height: 24px;
			background: linear-gradient(135deg, var(--wizard-primary), #2fbd67);
			border-radius: 2px;
		}
		
		.job-wizard-container .form-section-title {
			margin: 0;
			font-size: 1.1rem;
			font-weight: 700;
			color: #0f172a;
			letter-spacing: -0.01em;
		}
		
		.job-wizard-container .form-section-description {
			margin: 0.25rem 0 0;
			font-size: 0.9rem;
			color: var(--wizard-muted);
			line-height: 1.5;
		}

		.job-wizard-container .form-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
			gap: 1.5rem;
		}

		.job-wizard-container .form-field {
			display: flex;
			flex-direction: column;
			gap: 0.6rem;
			font-size: 0.95rem;
			color: #0f172a;
		}

		.job-wizard-container .form-field > span {
			font-weight: 600;
		}

		.job-wizard-container .form-field--wide {
			grid-column: span 2;
		}

		.job-wizard-container .form-field input,
		.job-wizard-container .form-field select,
		.job-wizard-container .form-field textarea {
			border: 1.5px solid var(--wizard-border);
			border-radius: 0.75rem;
			padding: 0.875rem 1rem;
			font-size: 1rem;
			font-family: inherit;
			background: #fff;
			transition: border-color 0.2s ease, box-shadow 0.2s ease;
			width: 100%;
		}
		
		@media (max-width: 600px) {
			.job-wizard-container .form-field input,
			.job-wizard-container .form-field select,
			.job-wizard-container .form-field textarea {
				padding: 1rem;
				font-size: 16px;
				border-radius: 0.75rem;
			}
		}

		.job-wizard-container .form-field textarea {
			resize: vertical;
		}

		.job-wizard-container .form-field input:focus,
		.job-wizard-container .form-field select:focus,
		.job-wizard-container .form-field textarea:focus {
			outline: none;
			border-color: var(--wizard-primary);
			box-shadow: 0 0 0 3px rgba(31, 123, 255, 0.15);
		}

		.job-wizard-container .form-field--static input {
			background: rgba(100, 116, 139, 0.1);
			color: #0f172a;
			cursor: default;
			pointer-events: none;
			border-color: var(--wizard-border);
		}

		.job-wizard-container .form-field--static input:focus {
			outline: none;
			border-color: var(--wizard-border);
			box-shadow: none;
		}

		.job-wizard-container .select-wrapper {
			position: relative;
			/* Keep z-index minimal to avoid overlap issues */
			z-index: 1;
		}

		.job-wizard-container .select-wrapper.is-disabled {
			opacity: 0.7;
			pointer-events: none;
		}

		.job-wizard-container .select-wrapper.is-disabled select {
			background: #e2e8f0;
			color: #94a3b8;
			cursor: not-allowed;
		}

		.job-wizard-container .select-wrapper.is-disabled::after {
			opacity: 0.4;
		}

		.job-wizard-container .select-wrapper::after {
			content: "";
			position: absolute;
			right: 1.15rem;
			top: 50%;
			width: 0.55rem;
			height: 0.55rem;
			border-left: 2px solid #475569;
			border-bottom: 2px solid #475569;
			transform: translateY(-60%) rotate(-45deg);
			pointer-events: none;
		}

		.job-wizard-container .select-wrapper select {
			appearance: none;
			-webkit-appearance: none;
			-moz-appearance: none;
			width: 100%;
			/* Use size attribute in HTML instead of CSS for better cross-browser support */
		}

		/* Compact option styling */
		.job-wizard-container .select-wrapper select option {
			padding: 0.5rem 0.75rem;
			line-height: 1.4;
		}

		.job-wizard-container .required-indicator {
			color: #dc2626;
			font-weight: 600;
		}

		.job-wizard-container .form-field--stacked {
			gap: 0.4rem;
		}

		.job-wizard-container .helper-text {
			margin: 0;
			font-size: 0.85rem;
			color: var(--wizard-muted);
		}

		.job-wizard-container .skills-selector {
			border: 1px solid var(--wizard-border);
			border-radius: 0.85rem;
			padding: 0.75rem;
			max-height: 300px;
			overflow-y: auto;
			background: #fff;
		}

		.job-wizard-container .skills-selector.is-disabled {
			background: #e2e8f0;
			opacity: 0.7;
			pointer-events: none;
		}

		.job-wizard-container .skill-item {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			padding: 0.5rem;
			border-radius: 0.5rem;
			transition: background 0.2s ease;
		}

		.job-wizard-container .skill-item:hover {
			background: rgba(31, 123, 255, 0.05);
		}

		.job-wizard-container .skill-item input[type="checkbox"] {
			width: 18px;
			height: 18px;
			cursor: pointer;
			margin: 0;
		}

		.job-wizard-container .skill-item label {
			flex: 1;
			cursor: pointer;
			margin: 0;
			font-size: 0.95rem;
		}

		.job-wizard-container .form-actions {
			display: flex;
			justify-content: flex-end;
			gap: 1rem;
			flex-wrap: wrap;
		}

		.job-wizard-container .form-navigation {
			align-items: center;
			padding-top: 1.5rem;
			border-top: 1px solid var(--wizard-border);
		}

		.job-wizard-container .form-navigation .btn {
			min-width: 140px;
		}

		.job-wizard-container .btn {
			border: none;
			border-radius: 999px;
			padding: 0.85rem 1.75rem;
			font-size: 1rem;
			font-weight: 600;
			cursor: pointer;
			transition: transform 0.2s ease, box-shadow 0.2s ease;
		}

		.job-wizard-container .btn.primary {
			background: var(--wizard-primary);
			color: #fff;
			box-shadow: 0 10px 25px rgba(31, 123, 255, 0.3);
		}

		.job-wizard-container .btn.primary:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			pointer-events: none;
			box-shadow: 0 4px 12px rgba(31, 123, 255, 0.15);
		}

		.job-wizard-container .btn.ghost {
			background: transparent;
			color: #0f172a;
			border: 1px solid var(--wizard-border);
		}

		.job-wizard-container .btn:focus-visible {
			outline: 3px solid rgba(31, 123, 255, 0.3);
			outline-offset: 2px;
		}

		.job-wizard-container .btn:hover {
			transform: translateY(-1px);
		}

		/* Mobile Form Section Navigation */
		.job-wizard-container .mobile-form-progress {
			display: none;
			justify-content: space-between;
			align-items: center;
			padding: 0.85rem 1rem;
			background: #f8f9fc;
			border-radius: 0.75rem;
			margin-bottom: 1.25rem;
			border: 1px solid var(--wizard-border);
		}

		.job-wizard-container .progress-dots {
			display: flex;
			gap: 0.5rem;
			align-items: center;
		}

		.job-wizard-container .progress-dot {
			width: 8px;
			height: 8px;
			border-radius: 50%;
			background: var(--wizard-border);
			transition: all 0.3s ease;
			cursor: pointer;
		}

		.job-wizard-container .progress-dot.active {
			width: 24px;
			border-radius: 12px;
			background: var(--wizard-primary);
		}

		.job-wizard-container .progress-label {
			font-size: 0.85rem;
			font-weight: 600;
			color: var(--wizard-muted);
		}

		.job-wizard-container .progress-label .current-section {
			color: var(--wizard-primary);
			font-weight: 700;
		}

		.job-wizard-container .mobile-form-section {
			display: block;
		}

		.job-wizard-container .mobile-section-nav {
			display: none;
			flex-direction: row;
			gap: 0.75rem;
			margin-top: 1.5rem;
			padding-top: 1.25rem;
			border-top: 1px solid var(--wizard-border);
		}

		.job-wizard-container .btn-mobile-section {
			flex: 1;
			padding: 0.85rem 1.25rem;
			border: 1px solid var(--wizard-border);
			background: var(--wizard-surface);
			border-radius: 0.75rem;
			font-size: 0.95rem;
			font-weight: 600;
			color: #0f172a;
			cursor: pointer;
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 0.5rem;
			font-family: "Roboto", "Segoe UI", Tahoma, sans-serif;
		}

		.job-wizard-container .btn-mobile-section:hover {
			border-color: var(--wizard-primary);
			background: rgba(31, 123, 255, 0.05);
			color: var(--wizard-primary);
		}

		.job-wizard-container .btn-mobile-section:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			pointer-events: none;
		}

		.job-wizard-container .btn-mobile-section i {
			font-size: 0.85rem;
		}

		.job-wizard-container .btn-mobile-prev {
			background: #f8f9fc;
		}

		.job-wizard-container .btn-mobile-next {
			background: linear-gradient(135deg, var(--wizard-primary) 0%, #1565c0 100%);
			color: #ffffff;
			border-color: var(--wizard-primary);
		}

		.job-wizard-container .btn-mobile-next:hover {
			background: linear-gradient(135deg, #1565c0 0%, var(--wizard-primary) 100%);
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(31, 123, 255, 0.3);
		}

		.job-wizard-container .btn-mobile-continue {
			background: linear-gradient(135deg, var(--wizard-primary) 0%, #1565c0 100%);
			color: #ffffff;
			border-color: var(--wizard-primary);
			box-shadow: 0 8px 20px rgba(31, 123, 255, 0.3);
		}

		.job-wizard-container .btn-mobile-continue:hover {
			background: linear-gradient(135deg, #1565c0 0%, var(--wizard-primary) 100%);
			transform: translateY(-2px);
			box-shadow: 0 10px 25px rgba(31, 123, 255, 0.4);
		}

		.job-wizard-container .btn-mobile-continue:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			pointer-events: none;
			box-shadow: 0 4px 12px rgba(31, 123, 255, 0.15);
		}

		@media (max-width: 600px) {
			/* Reset wizard padding for mobile */
			.job-wizard-container .job-wizard {
				padding: 1rem 1rem 2.5rem;
				min-height: 100vh;
			}
			
			/* Ensure all sections have consistent spacing */
			.job-wizard-container .job-wizard > section {
				margin-left: 0;
				margin-right: 0;
				width: 100%;
			}
			
			/* Show mobile section navigation */
			.job-wizard-container .mobile-form-progress {
				display: flex;
			}
			
			.job-wizard-container .mobile-section-nav {
				display: flex;
			}
			
			/* Hide all mobile sections by default */
			.job-wizard-container .mobile-form-section {
				display: none;
			}
			
			/* Show only active section */
			.job-wizard-container .mobile-form-section.active {
				display: block;
			}
			
			/* Compact mobile header container */
			.job-wizard-container .job-wizard__header-container {
				padding: 1rem;
				gap: 1rem;
				border-radius: 0.75rem;
			}
			
			/* Mobile intro section */
			.job-wizard-container .job-wizard__intro {
				margin-bottom: 0;
			}
			
			.job-wizard-container .job-wizard__intro-text .eyebrow {
				font-size: 0.65rem;
				margin-bottom: 0.35rem;
			}
			
			.job-wizard-container .job-wizard__intro-text h1 {
				font-size: 1.25rem;
				line-height: 1.2;
			}
			
			.job-wizard-container .job-wizard__intro-text .subtitle {
				font-size: 0.85rem;
				margin-top: 0.25rem;
				line-height: 1.3;
			}
			
			/* Mobile progress bar - fixed, non-scrollable */
			.job-wizard-container .job-wizard__progress {
				padding: 0.75rem;
				margin-top: 0;
				margin-left: 0;
				margin-right: 0;
				border-radius: 0;
				overflow: visible;
				border-top: 1px solid var(--wizard-border);
				background: transparent;
			}
			
			/* Mobile form container */
			.job-wizard-container .job-wizard__form {
				padding: 1.25rem 1rem;
				margin-top: 1rem;
				margin-left: 0;
				margin-right: 0;
				border-radius: 0.875rem;
			}
			
			.job-wizard-container .job-wizard__form header h2 {
				font-size: 1.25rem;
			}
			
			.job-wizard-container .job-wizard__form header p {
				font-size: 0.9rem;
			}

			/* Mobile form fields - single column */
			.job-wizard-container .form-field--wide {
				grid-column: span 1;
			}

			.job-wizard-container .progress-steps {
				display: flex;
				flex-direction: row;
				gap: 0;
				width: 100%;
				padding: 0;
				position: relative;
				justify-content: space-between;
			}

			.job-wizard-container .progress-step {
				flex-direction: column;
				align-items: center;
				text-align: center;
				padding: 0;
				gap: 0.35rem;
				flex: 1;
				position: relative;
				background: transparent;
			}

			/* Connector line between steps */
			.job-wizard-container .progress-step:not(:last-child)::after {
				content: "";
				position: absolute;
				top: 16px;
				left: calc(50% + 16px);
				width: calc(100% - 32px);
				height: 2px;
				background: #e2e8f0;
				z-index: 0;
			}

			.job-wizard-container .progress-step.is-active:not(:last-child)::after {
				background: linear-gradient(to right, #2fbd67 0%, #e2e8f0 100%);
			}

			.job-wizard-container .progress-step.is-completed:not(:last-child)::after {
				background: #2fbd67;
			}

			.job-wizard-container .progress-step .step-index {
				width: 32px;
				height: 32px;
				border-radius: 50%;
				background: #e2e8f0;
				color: #94a3b8;
				display: flex;
				align-items: center;
				justify-content: center;
				font-weight: 700;
				font-size: 0.8rem;
				position: relative;
				z-index: 1;
				border: 2px solid #fff;
				box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
				transition: all 0.3s ease;
				flex-shrink: 0;
			}

			.job-wizard-container .progress-step.is-active .step-index {
				background: #2fbd67;
				color: #fff;
				transform: scale(1.05);
				box-shadow: 0 2px 8px rgba(47, 189, 103, 0.25);
			}

			.job-wizard-container .progress-step.is-completed .step-index {
				background: #2fbd67;
				color: #fff;
			}

			.job-wizard-container .progress-step .step-title {
				font-size: 0.7rem;
				font-weight: 600;
				color: #94a3b8;
				margin: 0;
				line-height: 1.2;
			}

			.job-wizard-container .progress-step.is-active .step-title {
				color: #0f172a;
				font-weight: 700;
			}

			.job-wizard-container .progress-step.is-completed .step-title {
				color: #2fbd67;
			}

			.job-wizard-container .progress-step .step-caption {
				display: none; /* Hide captions on mobile for cleaner look */
			}

			.job-wizard-container .form-actions {
				flex-direction: column;
				align-items: stretch;
			}

			.job-wizard-container .btn {
				width: 100%;
			}

			/* Mobile-specific select improvements */
			.job-wizard-container .select-wrapper select {
				font-size: 16px; /* Prevents zoom on iOS */
				max-height: 50vh; /* Limit dropdown height on mobile */
			}

			/* Mobile form grid - single column layout */
			.job-wizard-container .form-grid {
				grid-template-columns: 1fr;
				gap: 1.25rem;
			}
			
			.job-wizard-container .job-details-form {
				margin-top: 1.5rem;
				margin-left: 0;
				margin-right: 0;
				gap: 1.5rem;
			}
			
			/* Mobile summary sections */
			.job-wizard-container .review-summary {
				gap: 1.25rem;
			}
			
			.job-wizard-container .summary-section {
				margin-left: 0;
				margin-right: 0;
				padding: 1rem;
				border-radius: 0.75rem;
			}
			
			.job-wizard-container .summary-header h4 {
				font-size: 1rem;
			}
			
			.job-wizard-container .summary-list {
				grid-template-columns: 1fr;
				gap: 1rem;
			}
			
			/* Mobile inline editor */
			.job-wizard-container .inline-editor {
				margin-top: 1.5rem;
				margin-left: 0;
				margin-right: 0;
				padding: 1rem;
				border-radius: 0.75rem;
			}
			
			/* Mobile step heading */
			.job-wizard-container .step-heading h3 {
				font-size: 1.1rem;
			}
			
			.job-wizard-container .step-heading p {
				font-size: 0.9rem;
			}
			
			/* Mobile form sections */
			.job-wizard-container .form-section + .form-section {
				padding-top: 1.5rem;
				margin-top: 0.75rem;
			}
			
			.job-wizard-container .form-section-title {
				font-size: 1rem;
			}
			
			.job-wizard-container .form-section-description {
				font-size: 0.85rem;
			}
			
			/* Mobile skills selector */
			.job-wizard-container .skills-selector {
				max-height: 250px;
				padding: 0.5rem;
			}
			
			.job-wizard-container .skill-item {
				padding: 0.65rem 0.5rem;
			}
			
			.job-wizard-container .skill-item label {
				font-size: 0.95rem;
			}
			
			/* Hide main form navigation on mobile for Step 1 only */
			.job-wizard-container .wizard-step[data-step="1"].is-active ~ .form-actions.form-navigation {
				display: none;
			}
			
			/* Show form navigation for Steps 2 and 3 on mobile */
			.job-wizard-container .wizard-step[data-step="2"].is-active ~ .form-actions.form-navigation,
			.job-wizard-container .wizard-step[data-step="3"].is-active ~ .form-actions.form-navigation {
				display: flex;
			}
		}

		.job-wizard-container .wizard-hidden {
			display: none !important;
		}
	</style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="job-wizard-container">
<main class="job-wizard">
	<section class="job-wizard__header-container">
		<div class="job-wizard__intro">
			<div class="job-wizard__intro-text">
				<p class="eyebrow">Employer Workspace</p>
				<h1>Post a New Job</h1>
				<p class="subtitle">Fill in the details to create your job posting</p>
			</div>
		</div>

		<div class="job-wizard__progress" aria-label="Job posting progress">
			<ol class="progress-steps">
				<li class="progress-step is-active">
					<span class="step-index">1</span>
					<div>
						<p class="step-title">Job Details</p>
						<p class="step-caption">Basic information about the position</p>
					</div>
				</li>
				<li class="progress-step">
					<span class="step-index">2</span>
					<div>
						<p class="step-title">Requirements</p>
						<p class="step-caption">Qualifications and compensation</p>
					</div>
				</li>
				<li class="progress-step">
					<span class="step-index">3</span>
					<div>
						<p class="step-title">Review &amp; Submit</p>
						<p class="step-caption">Double-check and finalize</p>
					</div>
				</li>
			</ol>
		</div>
	</section>

	<section class="job-wizard__form" aria-labelledby="job-details-heading">
		<header>
			<h2 id="job-details-heading">Complete Your Job Posting</h2>
			<p>Work through the three steps below. Fields with <span class="required-indicator" aria-hidden="true">*</span> are required.</p>
		</header>
		<form class="job-details-form" method="POST" action="submit_job_post.php" novalidate>
            <input type="hidden" name="current_step" id="current_step" value="<?php echo isset($draft['current_step']) ? (int)$draft['current_step'] : 0; ?>">
			<div class="wizard-step is-active" data-step="1" aria-labelledby="step-1-heading">
				<div class="step-heading">
					<h3 id="step-1-heading">Step 1 · Job Details</h3>
					<p>Help candidates understand the core information about the role.</p>
				</div>
				
				<!-- Mobile Section Progress -->
				<div class="mobile-form-progress">
					<div class="progress-dots">
						<span class="progress-dot active" data-mobile-section="1"></span>
						<span class="progress-dot" data-mobile-section="2"></span>
						<span class="progress-dot" data-mobile-section="3"></span>
					</div>
					<span class="progress-label">Section <span class="current-section">1</span> of 3</span>
				</div>
				
				<!-- Section 1: Job Overview -->
				<div class="mobile-form-section active" data-mobile-section="1">
					<div class="form-section">
					<div class="form-section-header">
						<h4 class="form-section-title">Job Overview</h4>
					</div>
					<p class="form-section-description">Provide the basic information about the position</p>
					
					<div class="form-grid">
						<label class="form-field">
							<span>Job Title <span class="required-indicator" aria-hidden="true">*</span></span>
							<input type="text" name="job_post_name" placeholder="e.g., Rice Farm Manager" aria-required="true" required value="<?php echo htmlspecialchars($draft['job_post_name'] ?? ''); ?>" />
						</label>

						<label class="form-field form-field--wide">
							<span>Description <span class="required-indicator" aria-hidden="true">*</span></span>
							<textarea name="job_description" rows="3" placeholder="Describe the role at a glance." aria-required="true" required><?php echo htmlspecialchars($draft['job_description'] ?? ''); ?></textarea>
						</label>

						<label class="form-field">
							<span>Industry <span class="required-indicator" aria-hidden="true">*</span></span>
							<div class="select-wrapper">
								<select name="industry_id" aria-required="true" required>
									<option value="" <?php echo empty($draft['industry_id']) ? 'selected' : ''; ?>>Select an industry</option>
									<?php if (!empty($industries)): ?>
										<?php foreach ($industries as $industry): ?>
											<option value="<?php echo (int)$industry['industry_id']; ?>" <?php echo (isset($draft['industry_id']) && (int)$draft['industry_id'] === (int)$industry['industry_id']) ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars($industry['industry_name']); ?>
											</option>
										<?php endforeach; ?>
									<?php else: ?>
										<option value="" disabled>No industries found</option>
									<?php endif; ?>
								</select>
							</div>
						</label>

						<label class="form-field">
							<span>Job Category <span class="required-indicator" aria-hidden="true">*</span></span>
							<div class="select-wrapper is-disabled" data-category-wrapper>
								<select
									name="job_category_id"
									aria-required="true"
									required
									disabled
									data-category-select
									data-default-label="Select a category"
									data-initial-value="<?php echo isset($draft['job_category_id']) ? (int)$draft['job_category_id'] : ''; ?>"
								>
									<option value="">Select an industry first</option>
								</select>
							</div>
							<p class="helper-text" data-category-helper>Select an industry first to reveal relevant categories.</p>
						</label>

						<label class="form-field">
							<span>Job Type <span class="required-indicator" aria-hidden="true">*</span></span>
							<div class="select-wrapper">
								<select name="job_type_id" aria-required="true" required>
									<option value="" <?php echo empty($draft['job_type_id']) ? 'selected' : ''; ?>>Select job type</option>
									<?php if (!empty($job_types)): ?>
										<?php foreach ($job_types as $job_type): ?>
											<option value="<?php echo (int)$job_type['job_type_id']; ?>" <?php echo (isset($draft['job_type_id']) && (int)$draft['job_type_id'] === (int)$job_type['job_type_id']) ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars($job_type['job_type_name']); ?>
											</option>
										<?php endforeach; ?>
									<?php else: ?>
										<option value="" disabled>No job types found</option>
									<?php endif; ?>
								</select>
							</div>
							<p class="helper-text">Select the employment type for this position.</p>
						</label>

						<label class="form-field">
							<span>Work Setup <span class="required-indicator" aria-hidden="true">*</span></span>
							<div class="select-wrapper">
								<select name="work_setup_id" aria-required="true" required>
									<option value="" <?php echo empty($draft['work_setup_id']) ? 'selected' : ''; ?>>Select work setup</option>
									<?php if (!empty($work_setups)): ?>
										<?php foreach ($work_setups as $work_setup): ?>
											<option value="<?php echo (int)$work_setup['work_setup_id']; ?>" <?php echo (isset($draft['work_setup_id']) && (int)$draft['work_setup_id'] === (int)$work_setup['work_setup_id']) ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars($work_setup['work_setup_name']); ?>
											</option>
										<?php endforeach; ?>
									<?php else: ?>
										<option value="" disabled>No work setups found</option>
									<?php endif; ?>
								</select>
							</div>
							<p class="helper-text">Select the work arrangement for this position.</p>
						</label>
					</div>
				</div>
			</div>
			
			<!-- Section 2: Hiring & Compensation -->
			<div class="mobile-form-section" data-mobile-section="2">
				<div class="form-section">
					<div class="form-section-header">
						<h4 class="form-section-title">Hiring &amp; Compensation</h4>
					</div>
					<p class="form-section-description">Define vacancy count, salary, and benefits</p>
					
					<div class="form-grid">
						<label class="form-field">
							<span>Number of Vacancies <span class="required-indicator" aria-hidden="true">*</span></span>
							<input type="number" name="vacancies" min="1" step="1" value="<?php echo htmlspecialchars($draft['vacancies'] ?? '1'); ?>" aria-required="true" required />
							<p class="helper-text">Specify how many candidates you plan to hire.</p>
						</label>

						<label class="form-field">
							<span>Salary / Budget (₱)</span>
							<input type="number" name="budget" min="0" step="500" placeholder="e.g., 30000" value="<?php echo htmlspecialchars($draft['budget'] ?? ''); ?>" />
							<p class="helper-text">Provide a range or maximum budget for transparency.</p>
						</label>

						<label class="form-field form-field--wide">
							<span>Benefits</span>
							<textarea name="benefits" rows="3" placeholder="Health coverage, allowances, bonuses, training..."><?php echo htmlspecialchars($draft['benefits'] ?? ''); ?></textarea>
						</label>
					</div>
				</div>
			</div>
			
			<!-- Section 3: Job Location -->
			<div class="mobile-form-section" data-mobile-section="3">
				<div class="form-section">
					<div class="form-section-header">
						<h4 class="form-section-title">Job Location</h4>
					</div>
					<p class="form-section-description">Specify where the job will be based</p>
					
					<div class="form-grid">
						<label class="form-field">
							<span>City / Municipality <span class="required-indicator" aria-hidden="true">*</span></span>
							<div class="select-wrapper">
								<select name="city_mun_id" id="city_mun_id" aria-required="true" required size="1">
									<option value="">Loading cities…</option>
								</select>
							</div>
							<p class="helper-text">Select the city or municipality for this job.</p>
						</label>

						<label class="form-field">
							<span>Barangay</span>
							<div class="select-wrapper">
								<select name="barangay_id" id="barangay_id" size="1">
									<option value="">Select a city/municipality first</option>
								</select>
							</div>
							<p class="helper-text">Optional — choose a barangay if applicable.</p>
						</label>

						<label class="form-field form-field--wide">
							<span>Location (street / building)</span>
							<input type="text" name="location" placeholder="Street, building, unit (optional)" value="<?php echo htmlspecialchars($draft['location'] ?? ''); ?>" />
						</label>
					</div>
				</div>
			</div>
			
			<!-- Mobile Navigation Buttons -->
			<div class="mobile-section-nav">
				<button type="button" class="btn-mobile-section btn-mobile-prev">
					<i class="fa-solid fa-chevron-left"></i> Previous
				</button>
				<button type="button" class="btn-mobile-section btn-mobile-next">
					Next <i class="fa-solid fa-chevron-right"></i>
				</button>
				<button type="button" class="btn-mobile-section btn-mobile-continue btn primary" style="display: none;">
					Continue
				</button>
			</div>
		</div>

			<div class="wizard-step" data-step="2" aria-labelledby="step-2-heading">
				<div class="step-heading">
					<h3 id="step-2-heading">Step 2 · Requirements</h3>
					<p>Select the skills required for this position.</p>
				</div>
				<label class="form-field">
					<span>Skills Needed <span class="required-indicator" aria-hidden="true">*</span></span>
					<div class="skills-selector is-disabled" id="skills-selector" data-skills-selector>
						<p style="color: var(--wizard-muted); text-align: center; padding: 1rem;">Please select a Job Category in Step 1 first</p>
					</div>
					<p class="helper-text">Select all skills required for this position. Choose a job category in Step 1 to see available skills.</p>
				</label>

				<div class="form-grid">
					<label class="form-field">
						<span>Education Level <span class="required-indicator" aria-hidden="true">*</span></span>
						<div class="select-wrapper">
							<select name="education_level_id" aria-required="true" required>
								<option value="" <?php echo empty($draft['education_level_id']) ? 'selected' : ''; ?>>Select education level</option>
								<?php if (!empty($education_levels)): ?>
									<?php foreach ($education_levels as $edu_level): ?>
										<option value="<?php echo (int)$edu_level['education_level_id']; ?>" <?php echo (isset($draft['education_level_id']) && (int)$draft['education_level_id'] === (int)$edu_level['education_level_id']) ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars($edu_level['education_level_name']); ?>
										</option>
									<?php endforeach; ?>
								<?php else: ?>
									<option value="" disabled>No education levels found</option>
								<?php endif; ?>
							</select>
						</div>
						<p class="helper-text">Minimum education level required for this position.</p>
					</label>

					<label class="form-field">
						<span>Experience Level <span class="required-indicator" aria-hidden="true">*</span></span>
						<div class="select-wrapper">
							<select name="experience_level_id" aria-required="true" required>
								<option value="" <?php echo empty($draft['experience_level_id']) ? 'selected' : ''; ?>>Select experience level</option>
								<?php if (!empty($experience_levels)): ?>
									<?php foreach ($experience_levels as $exp_level): ?>
										<option value="<?php echo (int)$exp_level['experience_level_id']; ?>" <?php echo (isset($draft['experience_level_id']) && (int)$draft['experience_level_id'] === (int)$exp_level['experience_level_id']) ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars($exp_level['experience_level_name']); ?>
										</option>
									<?php endforeach; ?>
								<?php else: ?>
									<option value="" disabled>No experience levels found</option>
								<?php endif; ?>
							</select>
						</div>
						<p class="helper-text">Minimum experience level required for this position.</p>
					</label>
				</div>
			</div>

			<div class="wizard-step" data-step="3" aria-labelledby="step-3-heading">
				<div class="step-heading">
					<h3 id="step-3-heading">Step 3 · Review &amp; Submit</h3>
					<p>Review your job posting details below and submit when ready.</p>
				</div>
				<div class="review-summary" aria-live="polite">
					<div class="summary-section">
						<div class="summary-header">
							<h4>Job Details</h4>
							<button type="button" class="summary-edit" data-target-step="0">Edit</button>
						</div>
						<dl class="summary-list">
							<div>
								<dt>Job Title:</dt>
								<dd data-summary-field="job_post_name"></dd>
							</div>
							<div>
								<dt>Category:</dt>
								<dd data-summary-field="job_category_id"></dd>
							</div>
							<div>
								<dt>Industry:</dt>
								<dd data-summary-field="industry_id"></dd>
							</div>
							<div>
								<dt>Job Type:</dt>
								<dd data-summary-field="job_type_id"></dd>
							</div>
							<div>
								<dt>Work Setup:</dt>
								<dd data-summary-field="work_setup"></dd>
							</div>
							<div>
								<dt>Vacancies:</dt>
								<dd data-summary-field="vacancies"></dd>
							</div>
							<div>
								<dt>Location:</dt>
								<dd data-summary-field="location"></dd>
							</div>
							<div>
								<dt>Description:</dt>
								<dd data-summary-field="job_description"></dd>
							</div>
						</dl>
					</div>

					<div class="summary-section">
						<div class="summary-header">
							<h4>Requirements</h4>
							<button type="button" class="summary-edit" data-target-step="1">Edit</button>
						</div>
						<dl class="summary-list">
							<div>
								<dt>Education:</dt>
								<dd data-summary-field="education_level_id"></dd>
							</div>
							<div>
								<dt>Experience:</dt>
								<dd data-summary-field="experience_level_id"></dd>
							</div>
							<div>
								<dt>Skills:</dt>
								<dd data-summary-field="skill_ids"></dd>
							</div>
						</dl>
					</div>
				</div>

				<div class="modal-overlay" hidden>
					<div class="inline-editor">
						<div class="inline-editor__header">
							<div>
								<p class="inline-editor__eyebrow">Editing</p>
								<h4 class="inline-editor__title">Section</h4>
							</div>
							<button type="button" class="modal-close-btn inline-editor__close" aria-label="Close">
								<i class="fa-solid fa-xmark"></i>
							</button>
						</div>
						<div class="inline-editor__body"></div>
						<div class="inline-editor__footer">
							<button type="button" class="btn ghost inline-editor__cancel">Cancel</button>
							<button type="button" class="btn primary inline-editor__done">Save Changes</button>
						</div>
					</div>
				</div>
			</div>

			<div class="form-actions form-navigation">
				<button type="button" class="btn ghost btn-prev wizard-hidden">Back</button>
				<button type="button" class="btn primary btn-next">Continue</button>
				<button type="submit" class="btn primary btn-submit wizard-hidden">Submit Job Post</button>
			</div>
		</form>
	</section>
</main>	
</div>
<script>
(function () {
	const industryCategoryMap = <?php echo json_encode($job_category_groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};
	const form = document.querySelector('.job-details-form');
	if (!form) {
		return;
	}
	const steps = Array.from(form.querySelectorAll('.wizard-step'));
	const progressSteps = Array.from(document.querySelectorAll('.progress-step'));
	const prevBtn = form.querySelector('.btn-prev');
	const nextBtn = form.querySelector('.btn-next');
	const submitBtn = form.querySelector('.btn-submit');
	const summaryElements = Array.from(form.querySelectorAll('[data-summary-field]'));
	const summaryEditButtons = Array.from(form.querySelectorAll('.summary-edit'));
	const summaryMap = summaryElements.reduce(function (acc, el) {
		acc[el.dataset.summaryField] = el;
		return acc;
	}, {});
	const modalOverlay = form.querySelector('.modal-overlay');
	const inlineEditor = form.querySelector('.inline-editor');
	const inlineEditorBody = inlineEditor ? inlineEditor.querySelector('.inline-editor__body') : null;
	const inlineEditorTitle = inlineEditor ? inlineEditor.querySelector('.inline-editor__title') : null;
	const inlineEditorClose = inlineEditor ? inlineEditor.querySelector('.inline-editor__close') : null;
	const inlineEditorDone = inlineEditor ? inlineEditor.querySelector('.inline-editor__done') : null;
	const inlineEditorCancel = inlineEditor ? inlineEditor.querySelector('.inline-editor__cancel') : null;
	const stepPlaceholders = steps.map(function (step) {
		const placeholder = document.createElement('div');
		placeholder.className = 'step-placeholder';
		step.parentNode.insertBefore(placeholder, step);
		return placeholder;
	});
	const industrySelect = form.querySelector('select[name="industry_id"]');
	const categorySelect = form.querySelector('[data-category-select]');
	const categoryWrapper = form.querySelector('[data-category-wrapper]');
	const categoryHelper = form.querySelector('[data-category-helper]');
	if (categorySelect) {
		categorySelect.dataset.currentValue = categorySelect.dataset.initialValue || '';
		categorySelect.addEventListener('change', function () {
			categorySelect.dataset.currentValue = categorySelect.value;
		});
	}
	let currentStep = parseInt('<?php echo isset($draft["current_step"]) ? (int)$draft["current_step"] : 0; ?>') || 0;
    let formSubmitted = false;

	const summaryFallback = 'Not specified';
	let inlineEditIndex = null;
	const categoryLockedMessage = 'Select an industry first to see available categories.';
	const categoryEmptyMessage = 'No categories are available for this industry yet.';
	let lastIndustryValue = industrySelect ? industrySelect.value : '';

	function lockCategorySelect(message) {
		if (!categorySelect) {
			return;
		}
		categorySelect.disabled = true;
		categorySelect.innerHTML = '';
		const option = document.createElement('option');
		option.value = '';
		option.textContent = message || categoryLockedMessage;
		categorySelect.appendChild(option);
		categorySelect.value = '';
		if (categoryWrapper) {
			categoryWrapper.classList.add('is-disabled');
		}
		if (categoryHelper) {
			categoryHelper.textContent = message || categoryLockedMessage;
		}
	}

	function populateCategoryOptions(industryValue) {
		if (!categorySelect) {
			return;
		}
		if (!industryValue) {
			lockCategorySelect(categoryLockedMessage);
			return;
		}
		const categories = industryCategoryMap[String(industryValue)] || [];
		if (!categories.length) {
			lockCategorySelect(categoryEmptyMessage);
			return;
		}
		const desiredValue = categorySelect.dataset.currentValue || '';
		const defaultLabel = categorySelect.dataset.defaultLabel || 'Select a category';
		const fragment = document.createDocumentFragment();
		const placeholder = document.createElement('option');
		placeholder.value = '';
		placeholder.textContent = defaultLabel;
		fragment.appendChild(placeholder);
		categories.forEach(function (category) {
			const option = document.createElement('option');
			option.value = String(category.id);
			option.textContent = category.name;
			if (desiredValue && option.value === desiredValue) {
				option.selected = true;
			}
			fragment.appendChild(option);
		});
		categorySelect.innerHTML = '';
		categorySelect.appendChild(fragment);
		categorySelect.disabled = false;
		if (categoryWrapper) {
			categoryWrapper.classList.remove('is-disabled');
		}
		if (categoryHelper) {
			categoryHelper.textContent = 'Choose the most relevant specialization for this role.';
		}
	}

	function handleIndustryChange() {
		if (!industrySelect || !categorySelect) {
			return;
		}
		const currentValue = industrySelect.value;
		if (currentValue !== lastIndustryValue) {
			categorySelect.dataset.currentValue = '';
		}
		lastIndustryValue = currentValue;
		populateCategoryOptions(currentValue);
	}

	if (industrySelect && categorySelect) {
		handleIndustryChange();
		industrySelect.addEventListener('change', handleIndustryChange);
	} else if (categorySelect) {
		lockCategorySelect(categoryLockedMessage);
	}

	// --- Skills selector management ---
	const skillGroupsMap = <?php echo json_encode($skill_groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};
	const skillsSelector = document.getElementById('skills-selector');
	
	// Load selected skills from PHP draft
	const draftSkills = <?php echo json_encode($draft['skill_ids'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
	let selectedSkillIds = Array.isArray(draftSkills) ? draftSkills.map(function(id) { return parseInt(id); }) : [];

	function populateSkills(categoryId) {
		if (!skillsSelector) return;
		
		if (!categoryId) {
			skillsSelector.classList.add('is-disabled');
			skillsSelector.innerHTML = '<p style="color: var(--wizard-muted); text-align: center; padding: 1rem;">Please select a Job Category in Step 1 first</p>';
			return;
		}
		
		const skills = skillGroupsMap[String(categoryId)] || [];
		
		if (!skills.length) {
			skillsSelector.classList.add('is-disabled');
			skillsSelector.innerHTML = '<p style="color: var(--wizard-muted); text-align: center; padding: 1rem;">No skills available for this category</p>';
			return;
		}
		
		skillsSelector.classList.remove('is-disabled');
		skillsSelector.innerHTML = '';
		
		skills.forEach(function(skill) {
			const skillItem = document.createElement('div');
			skillItem.className = 'skill-item';
			
			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.name = 'skill_ids[]';
			checkbox.value = skill.id;
			checkbox.id = 'skill_' + skill.id;
			
			// Check if this skill was previously selected (from PHP draft or user selection)
			if (selectedSkillIds.includes(parseInt(skill.id))) {
				checkbox.checked = true;
			}
			
			const label = document.createElement('label');
			label.setAttribute('for', 'skill_' + skill.id);
			label.textContent = skill.name;
			
			skillItem.appendChild(checkbox);
			skillItem.appendChild(label);
			skillsSelector.appendChild(skillItem);
			
			// Update selected skills when checkbox changes
			checkbox.addEventListener('change', function() {
				updateSelectedSkills();
			});
			
			// Prevent clicks on the container from bubbling to other elements
			skillItem.addEventListener('click', function(e) {
				// Only allow clicks directly on checkbox or label
				if (e.target !== checkbox && e.target !== label) {
					e.stopPropagation();
					e.preventDefault();
				}
			});
		});
	}
	
	function updateSelectedSkills() {
		const checkboxes = skillsSelector.querySelectorAll('input[type="checkbox"]:checked');
		selectedSkillIds = Array.from(checkboxes).map(function(cb) {
			return parseInt(cb.value);
		});
	}
	
	// Listen to category changes
	if (categorySelect) {
		categorySelect.addEventListener('change', function() {
			const categoryId = this.value;
			populateSkills(categoryId);
		});
		
		// Initialize skills if category is already selected (from draft)
		const initialCategory = categorySelect.value || '<?php echo isset($draft["job_category_id"]) ? (int)$draft["job_category_id"] : ""; ?>';
		if (initialCategory) {
			// Small delay to ensure category select is fully initialized
			setTimeout(function() {
				populateSkills(initialCategory);
				// Update summary after skills are populated
				setTimeout(function() {
					updateSummary();
				}, 50);
			}, 100);
		}
	}

	// --- Cascading city / barangay selects (page-level initializer) ---
	function fetchJSON(url) {
		return fetch(url, { cache: 'no-store' }).then(function (res) {
			if (!res.ok) throw new Error('Network error');
			return res.json();
		});
	}

	async function loadCities() {
		const citySelect = document.getElementById('city_mun_id');
		if (!citySelect) return;
		citySelect.innerHTML = '<option value="">Loading cities…</option>';
		try {
			const data = await fetchJSON('/WORKSAD/api/get_cities.php');
			citySelect.innerHTML = '<option value="">Select city/municipality</option>';
			data.forEach(function (c) {
				const opt = document.createElement('option');
				opt.value = c.city_mun_id;
				opt.textContent = c.city_mun_name;
				citySelect.appendChild(opt);
			});
			const initialCity = '<?php echo isset($draft["city_mun_id"]) ? (int)$draft["city_mun_id"] : ""; ?>';
			if (initialCity) {
				citySelect.value = initialCity;
			}
		} catch (err) {
			citySelect.innerHTML = '<option value="">Failed to load cities</option>';
			console.error(err);
		}
	}	async function loadBarangays(cityId) {
		const brgySelect = document.getElementById('barangay_id');
		if (!brgySelect) return;
		if (!cityId) {
			brgySelect.innerHTML = '<option value="">Select a city/municipality first</option>';
			return;
		}
		brgySelect.innerHTML = '<option value="">Loading barangays…</option>';
		try {
			const data = await fetchJSON('/WORKSAD/api/get_barangays.php?city_mun_id=' + encodeURIComponent(cityId));
			if (!data.length) {
				brgySelect.innerHTML = '<option value="">No barangays found</option>';
				return;
			}
			brgySelect.innerHTML = '<option value="">Select barangay</option>';
			data.forEach(function (b) {
				const opt = document.createElement('option');
				opt.value = b.barangay_id;
				opt.textContent = b.barangay_name;
				brgySelect.appendChild(opt);
			});
			const initialBrgy = '<?php echo isset($draft["barangay_id"]) ? (int)$draft["barangay_id"] : ""; ?>';
			if (initialBrgy) brgySelect.value = initialBrgy;
		} catch (err) {
			brgySelect.innerHTML = '<option value="">Failed to load barangays</option>';
			console.error(err);
		}
	}

	// Initialize city/barangay selects on page load
	(function () {
		loadCities().then(function () {
			const citySelect = document.getElementById('city_mun_id');
			if (citySelect) {
				if (citySelect.value) {
					loadBarangays(citySelect.value);
				}
				citySelect.addEventListener('change', function () {
					loadBarangays(this.value);
				});
			}
		});
	})();

	function formatCurrency(value) {
		if (!value) {
			return summaryFallback;
		}
		const numeric = Number(value.replace(/,/g, ''));
		if (Number.isNaN(numeric)) {
			return value;
		}
		return `₱ ${numeric.toLocaleString('en-PH')}`;
	}

	function getFieldDisplay(name) {
        const field = form.elements[name];
        
        // Special handling for location - concatenate city + barangay names
        if (name === 'location') {
            const citySelect = document.getElementById('city_mun_id');
            const barangaySelect = document.getElementById('barangay_id');
            const locationInput = form.elements['location'];
            
            const locationParts = [];
            
            // Add city name (required field, so it should always exist)
            if (citySelect && citySelect.value) {
                const cityOption = citySelect.options[citySelect.selectedIndex];
                if (cityOption && cityOption.textContent.trim()) {
                    locationParts.push(cityOption.textContent.trim());
                }
            }
            
            // Add barangay name (optional)
            if (barangaySelect && barangaySelect.value) {
                const barangayOption = barangaySelect.options[barangaySelect.selectedIndex];
                if (barangayOption && barangayOption.textContent.trim()) {
                    locationParts.push(barangayOption.textContent.trim());
                }
            }
            
            // Add street/building if provided (optional)
            if (locationInput && locationInput.value.trim()) {
                locationParts.push(locationInput.value.trim());
            }
            
            // Since city is required, this will always have at least one element
            return locationParts.join(', ');
        }
        
        // Special handling for work_setup - map to work_setup_id field
        if (name === 'work_setup') {
            const workSetupField = form.elements['work_setup_id'];
            if (workSetupField && workSetupField.value) {
                const option = workSetupField.options[workSetupField.selectedIndex];
                return option && option.textContent.trim() ? option.textContent.trim() : summaryFallback;
            }
            return summaryFallback;
        }
        
        // Special handling for skills
        if (name === 'skill_ids') {
            const checkboxes = skillsSelector.querySelectorAll('input[type="checkbox"]:checked');
            if (checkboxes.length === 0) {
                return summaryFallback;
            }
            const skillNames = Array.from(checkboxes).map(function(cb) {
                const label = cb.nextElementSibling;
                return label ? label.textContent : '';
            }).filter(Boolean);
            return skillNames.join(', ');
        }
        
        if (!field) {
            return summaryFallback;
        }
        if (field.tagName === 'SELECT') {
            const option = field.options[field.selectedIndex];
            return option && option.textContent.trim() ? option.textContent.trim() : summaryFallback;
        }
        if (field.type === 'checkbox') {
            return field.checked ? 'Enabled' : 'Disabled';
        }
        const value = field.value.trim();
        if (!value) {
            return summaryFallback;
        }
        switch (name) {
            case 'budget':
                return formatCurrency(value);
            default:
                return value;
        }
    }

	function updateSummary() {
		Object.keys(summaryMap).forEach(function (fieldName) {
			const target = summaryMap[fieldName];
			if (!target) {
				return;
			}
			target.textContent = getFieldDisplay(fieldName);
		});
	}

	function setStep(index) {
		currentStep = index; // Update the currentStep variable
		
		// Update hidden field for auto-save
		const currentStepField = document.getElementById('current_step');
		if (currentStepField) {
			currentStepField.value = index;
		}
		
		steps.forEach(function (step, stepIndex) {
			step.classList.toggle('is-active', stepIndex === index);
		});
		progressSteps.forEach(function (step, stepIndex) {
			step.classList.toggle('is-active', stepIndex === index);
			// Mark previous steps as completed
			step.classList.toggle('is-completed', stepIndex < index);
		});
		if (prevBtn) {
			const isFirst = index === 0;
			prevBtn.disabled = isFirst;
			prevBtn.classList.toggle('wizard-hidden', isFirst);
		}
		const isLast = index === steps.length - 1;
		if (nextBtn) {
			nextBtn.classList.toggle('wizard-hidden', isLast);
		}
		if (submitBtn) {
			submitBtn.classList.toggle('wizard-hidden', !isLast);
		}
		if (!isLast) {
			closeInlineEditor();
		}
		if (isLast) {
			updateSummary();
		}
	}
	
	function validateStep(index) {
		const fields = steps[index].querySelectorAll('input, select, textarea');
		for (let i = 0; i < fields.length; i += 1) {
			const field = fields[i];
			if (!field.checkValidity()) {
				field.reportValidity();
				return false;
			}
		}
		
		// Additional validation for Step 2: Check if at least one skill is selected
		if (index === 1) {
			const selectedSkills = skillsSelector.querySelectorAll('input[type="checkbox"]:checked');
			if (selectedSkills.length === 0) {
				alert('Please select at least one skill for this position.');
				return false;
			}
		}
		
		return true;
	}

	nextBtn.addEventListener('click', function () {
		if (!validateStep(currentStep)) {
			return;
		}
		if (currentStep < steps.length - 1) {
			currentStep += 1;
			setStep(currentStep);
		}
	});
	
	if (prevBtn) {
		prevBtn.addEventListener('click', function () {
			if (currentStep > 0) {
				currentStep -= 1;
				setStep(currentStep);
			}
		});
	}
	
	// Auto-save functionality - FIXED VERSION
    let autoSaveTimeout = null;
    let lastSavedData = '';

    function autoSave() {
        if (formSubmitted) {
            return;
        }
        if (autoSaveTimeout) {
            clearTimeout(autoSaveTimeout);
        }
        
        autoSaveTimeout = setTimeout(function () {
            // Serialize form data to check if it changed
            const formData = new FormData(form);
            const currentData = new URLSearchParams(formData).toString();
            
            // Only save if data actually changed
            if (currentData === lastSavedData) {
                return;
            }
            
            lastSavedData = currentData;
            
            fetch('save_draft.php', {
                method: 'POST',
                body: formData
            })
            .then(function (response) { 
                if (!response.ok) throw new Error('Save failed');
                return response.json(); 
            })
            .then(function (data) {
                if (data.success) {
                    console.log('✓ Draft auto-saved');
                }
            })
            .catch(function (error) {
                console.error('Auto-save failed:', error);
            });
        }, 2000); // Wait 2 seconds after user stops typing
    }

    // Listen to all form changes for auto-save
    form.addEventListener('input', autoSave);
    form.addEventListener('change', autoSave);

    // Save draft when user leaves the page
    window.addEventListener('beforeunload', function() {
        if (formSubmitted) {
            return;
        }
        const formData = new FormData(form);
        navigator.sendBeacon('save_draft.php', formData);
    });

	summaryEditButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			const targetIndex = Number(button.dataset.targetStep);
			if (Number.isNaN(targetIndex)) {
				return;
			}
			openInlineEditor(targetIndex);
		});
	});

	if (inlineEditorClose) {
		inlineEditorClose.addEventListener('click', function () {
			closeInlineEditor();
		});
	}
	
	if (inlineEditorDone) {
		inlineEditorDone.addEventListener('click', function () {
			closeInlineEditor();
		});
	}
	
	if (inlineEditorCancel) {
		inlineEditorCancel.addEventListener('click', function () {
			closeInlineEditor();
		});
	}
	
	// Close modal when clicking overlay
	if (modalOverlay) {
		modalOverlay.addEventListener('click', function (e) {
			if (e.target === modalOverlay) {
				closeInlineEditor();
			}
		});
	}
	
	// Close modal with Escape key
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && modalOverlay && !modalOverlay.hidden) {
			closeInlineEditor();
		}
	});

	form.addEventListener('submit', function (event) {
		event.preventDefault();
		if (!form.checkValidity()) {
			form.reportValidity();
			return;
		}
		
		// Submit form via AJAX
		const formData = new FormData(form);
		submitBtn.disabled = true;
		submitBtn.textContent = 'Submitting...';
		
		fetch('submit_job_post.php', {
			method: 'POST',
			body: formData
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			if (data.success) {
				formSubmitted = true;
                lastSavedData = '';
                form.reset();
                selectedSkillIds = [];
                if (categorySelect) {
                    categorySelect.dataset.currentValue = '';
                    lockCategorySelect(categoryLockedMessage);
                }
                if (skillsSelector) {
                    skillsSelector.classList.add('is-disabled');
                    skillsSelector.innerHTML = '<p style="color: var(--wizard-muted); text-align: center; padding: 1rem;">Please select a Job Category in Step 1 first</p>';
                }
                const currentStepField = document.getElementById('current_step');
                if (currentStepField) {
                    currentStepField.value = 0;
                }
                setStep(0);
                alert(data.message);
                window.location.href = 'jobs_posted.php';
            } else {
                alert(data.message || 'Failed to submit job post.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Job Post';
            }
		})
		.catch(function(error) {
			console.error('Error:', error);
			alert('An error occurred while submitting the job post.');
			submitBtn.disabled = false;
			submitBtn.textContent = 'Submit Job Post';
		});
	});

	form.addEventListener('input', handleLiveSummaryUpdate, true);
	form.addEventListener('change', handleLiveSummaryUpdate, true);

	function handleLiveSummaryUpdate() {
		if (currentStep === steps.length - 1) {
			updateSummary();
		}
	}

	function openInlineEditor(stepIndex) {
		if (!inlineEditor || !inlineEditorBody) {
			return;
		}
		const step = steps[stepIndex];
		const placeholder = stepPlaceholders[stepIndex];
		if (!step || !placeholder) {
			return;
		}
		if (inlineEditIndex !== null && inlineEditIndex !== stepIndex) {
			closeInlineEditor();
		}
		inlineEditIndex = stepIndex;
		step.classList.add('is-inline-edit');
		inlineEditorBody.innerHTML = '';
		inlineEditorBody.appendChild(step);
		if (inlineEditorTitle) {
			const heading = step.querySelector('.step-heading h3');
			inlineEditorTitle.textContent = heading ? heading.textContent : 'Edit Details';
		}
		if (modalOverlay) {
			modalOverlay.hidden = false;
			document.body.style.overflow = 'hidden';
		}
	}

	function closeInlineEditor() {
		if (inlineEditIndex === null || !inlineEditorBody) {
			return;
		}
		const step = steps[inlineEditIndex];
		const placeholder = stepPlaceholders[inlineEditIndex];
		if (step && placeholder) {
			placeholder.insertAdjacentElement('afterend', step);
			step.classList.remove('is-inline-edit');
		}
		inlineEditorBody.innerHTML = '';
		if (modalOverlay) {
			modalOverlay.hidden = true;
			document.body.style.overflow = '';
		}
		inlineEditIndex = null;
	}

	// Mobile Section Navigation for Step 1
	function initMobileSectionNavigation() {
		const step1 = form.querySelector('.wizard-step[data-step="1"]');
		if (!step1) return;
		
		const mobileSections = step1.querySelectorAll('.mobile-form-section');
		const progressDots = step1.querySelectorAll('.progress-dot');
		const btnPrev = step1.querySelector('.btn-mobile-prev');
		const btnNext = step1.querySelector('.btn-mobile-next');
		const btnMobileContinue = step1.querySelector('.btn-mobile-continue');
		const currentSectionLabel = step1.querySelector('.current-section');
		
		let currentMobileSection = 1;
		const totalMobileSections = mobileSections.length;
		
		// Function to check if current mobile section has all required fields filled
		function validateCurrentMobileSection() {
			const currentSection = mobileSections[currentMobileSection - 1];
			if (!currentSection) return false;
			
			const requiredFields = currentSection.querySelectorAll('[required]');
			let allFilled = true;
			
			requiredFields.forEach(function(field) {
				const value = field.value.trim();
				if (!value || value === '') {
					allFilled = false;
				}
			});
			
			return allFilled;
		}
		
		// Function to update Next button state
		function updateNextButtonState() {
			if (!btnNext) return;
			
			const isValid = validateCurrentMobileSection();
			btnNext.disabled = !isValid;
			
			if (isValid) {
				btnNext.style.opacity = '1';
				btnNext.style.cursor = 'pointer';
			} else {
				btnNext.style.opacity = '0.5';
				btnNext.style.cursor = 'not-allowed';
			}
		}
		
		// Function to check if all required fields in Step 1 are filled
		function validateAllStep1Fields() {
			const requiredFields = step1.querySelectorAll('[required]');
			let allFilled = true;
			
			requiredFields.forEach(function(field) {
				const value = field.value.trim();
				if (!value || value === '') {
					allFilled = false;
				}
			});
			
			return allFilled;
		}
		
		// Function to update mobile Continue button state
		function updateMobileContinueButtonState() {
			if (!btnMobileContinue) return;
			
			const isAllValid = validateAllStep1Fields();
			btnMobileContinue.disabled = !isAllValid;
			
			if (isAllValid) {
				btnMobileContinue.style.opacity = '1';
				btnMobileContinue.style.cursor = 'pointer';
			} else {
				btnMobileContinue.style.opacity = '0.5';
				btnMobileContinue.style.cursor = 'not-allowed';
			}
		}
		
		function updateMobileSection(sectionNum) {
			// Update sections visibility
			mobileSections.forEach(function(section, index) {
				if (index + 1 === sectionNum) {
					section.classList.add('active');
				} else {
					section.classList.remove('active');
				}
			});
			
			// Update dots
			progressDots.forEach(function(dot, index) {
				if (index + 1 === sectionNum) {
					dot.classList.add('active');
				} else {
					dot.classList.remove('active');
				}
			});
			
			// Update label
			if (currentSectionLabel) {
				currentSectionLabel.textContent = sectionNum;
			}
			
			// Update buttons visibility
			if (btnPrev && btnNext && btnMobileContinue) {
				if (sectionNum === 1) {
					// Section 1: Only Next button
					btnPrev.style.display = 'none';
					btnNext.style.display = 'flex';
					btnMobileContinue.style.display = 'none';
				} else if (sectionNum === totalMobileSections) {
					// Section 3 (last): Previous and Continue button
					btnPrev.style.display = 'flex';
					btnNext.style.display = 'none';
					btnMobileContinue.style.display = 'flex';
				} else {
					// Section 2 (middle): Previous and Next button
					btnPrev.style.display = 'flex';
					btnNext.style.display = 'flex';
					btnMobileContinue.style.display = 'none';
				}
			}
			
			currentMobileSection = sectionNum;
			
			// Update validation states
			updateNextButtonState();
			updateMobileContinueButtonState();
			
			// Scroll to top of step on mobile
			if (window.innerWidth <= 600) {
				step1.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}
		
		// Next button
		if (btnNext) {
			btnNext.addEventListener('click', function() {
				if (currentMobileSection < totalMobileSections && validateCurrentMobileSection()) {
					updateMobileSection(currentMobileSection + 1);
				}
			});
		}
		
		// Previous button
		if (btnPrev) {
			btnPrev.addEventListener('click', function() {
				if (currentMobileSection > 1) {
					updateMobileSection(currentMobileSection - 1);
				}
			});
		}
		
		// Mobile Continue button (advances to Step 2)
		if (btnMobileContinue) {
			btnMobileContinue.addEventListener('click', function() {
				if (validateAllStep1Fields()) {
					setStep(2);
				}
			});
		}
		
		// Dot navigation
		progressDots.forEach(function(dot) {
			dot.addEventListener('click', function() {
				const sectionNum = parseInt(dot.dataset.mobileSection);
				updateMobileSection(sectionNum);
			});
		});
		
		// Add input listeners to all required fields in Step 1
		const allRequiredFields = step1.querySelectorAll('[required]');
		allRequiredFields.forEach(function(field) {
			field.addEventListener('input', function() {
				updateNextButtonState();
				updateContinueButtonState();
			});
			field.addEventListener('change', function() {
				updateNextButtonState();
				updateContinueButtonState();
			});
		});
		
		// Special validation for vacancies field - prevent zero and negative numbers
		const vacanciesField = form.querySelector('input[name="vacancies"]');
		if (vacanciesField) {
			vacanciesField.addEventListener('input', function() {
				const value = parseInt(this.value);
				if (value <= 0 || isNaN(value)) {
					this.value = '';
				}
			});
			vacanciesField.addEventListener('blur', function() {
				const value = parseInt(this.value);
				if (value <= 0 || isNaN(value) || this.value === '') {
					this.value = '';
					this.focus();
				}
			});
		}
		
		// Initialize first section
		updateMobileSection(1);
		
		// Initial validation check
		updateNextButtonState();
		updateMobileContinueButtonState();
		
		// Update on window resize
		window.addEventListener('resize', function() {
			updateMobileContinueButtonState();
		});
	}

	// Initialize the wizard on page load
	setStep(currentStep);
	initMobileSectionNavigation();
	
	// Update summary on page load to populate all fields
	setTimeout(function() {
		updateSummary();
	}, 200);
})();
</script>

</body>
</html>