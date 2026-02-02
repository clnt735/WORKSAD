<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume - <?php echo htmlspecialchars($data['full_name'] ?? 'Applicant'); ?></title>
    <style>
        @page {
            margin: 0.5in;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #333;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* Header Section */
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 3px solid #2563eb;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8px;
        }
        
        .header .contact-info {
            font-size: 9pt;
            color: #666;
            margin-top: 8px;
        }
        
        .header .contact-info span {
            display: inline-block;
            margin: 0 10px;
        }
        
        /* Section Headers */
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: #1e40af;
            border-bottom: 2px solid #93c5fd;
            padding-bottom: 5px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Professional Summary */
        .summary-text {
            text-align: justify;
            color: #555;
            padding: 10px;
            background-color: #f8fafc;
            border-left: 4px solid #2563eb;
            margin-bottom: 10px;
        }
        
        /* Experience & Education Items */
        .item {
            margin-bottom: 15px;
            padding-left: 10px;
            border-left: 3px solid #e5e7eb;
        }
        
        .item-header {
            margin-bottom: 5px;
        }
        
        .item-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1f2937;
        }
        
        .item-subtitle {
            font-size: 10pt;
            color: #6b7280;
            font-style: italic;
        }
        
        .item-date {
            font-size: 9pt;
            color: #9ca3af;
            margin-top: 2px;
        }
        
        .item-description {
            font-size: 10pt;
            color: #555;
            margin-top: 5px;
            text-align: justify;
        }
        
        /* Skills Section */
        .skills-container {
            display: block;
        }
        
        .skill-category {
            margin-bottom: 12px;
            padding: 8px;
            background-color: #f8fafc;
            border-left: 3px solid #2563eb;
        }
        
        .skill-category-name {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
            font-size: 10pt;
        }
        
        .skills-list {
            color: #555;
            font-size: 9pt;
        }
        
        .skill-item {
            display: inline-block;
            padding: 3px 8px;
            margin: 2px;
            background-color: #e0f2fe;
            border-radius: 3px;
            color: #0369a1;
        }
        
        /* Achievements */
        .achievement-item {
            margin-bottom: 10px;
            padding: 8px;
            background-color: #fefce8;
            border-left: 3px solid #eab308;
        }
        
        .achievement-title {
            font-weight: bold;
            color: #854d0e;
            font-size: 10pt;
        }
        
        .achievement-org {
            color: #a16207;
            font-size: 9pt;
            font-style: italic;
        }
        
        .achievement-date {
            color: #ca8a04;
            font-size: 8pt;
            margin-top: 2px;
        }
        
        .achievement-desc {
            color: #555;
            font-size: 9pt;
            margin-top: 4px;
        }
        
        /* Preferences Section */
        .preferences-grid {
            display: block;
        }
        
        .preference-item {
            display: inline-block;
            width: 48%;
            padding: 8px;
            margin-bottom: 8px;
            background-color: #f0fdf4;
            border-left: 3px solid #16a34a;
        }
        
        .preference-label {
            font-weight: bold;
            color: #15803d;
            font-size: 9pt;
        }
        
        .preference-value {
            color: #555;
            font-size: 9pt;
            margin-top: 2px;
        }
        
        /* Location */
        .location-text {
            padding: 8px;
            background-color: #fef3c7;
            border-left: 3px solid #f59e0b;
            color: #92400e;
            font-size: 10pt;
        }
        
        /* Empty State */
        .empty-message {
            color: #9ca3af;
            font-style: italic;
            padding: 10px;
            text-align: center;
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8pt;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><?php echo htmlspecialchars($data['full_name'] ?? 'Applicant Name'); ?></h1>
            <div class="contact-info">
                <?php if (!empty($data['email'])): ?>
                    <span>📧 <?php echo htmlspecialchars($data['email']); ?></span>
                <?php endif; ?>
                <?php if (!empty($data['phone'])): ?>
                    <span>📱 <?php echo htmlspecialchars($data['phone']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Professional Summary -->
        <?php if (!empty($data['professional_summary'])): ?>
        <div class="section">
            <div class="section-title">Professional Summary</div>
            <div class="summary-text">
                <?php echo nl2br(htmlspecialchars($data['professional_summary'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Location -->
        <?php if (!empty($data['location'])): ?>
        <div class="section">
            <div class="section-title">Location</div>
            <div class="location-text">
                📍 <?php echo htmlspecialchars($data['location']); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Work Experience -->
        <?php if (!empty($data['work_experience']) && count($data['work_experience']) > 0): ?>
        <div class="section">
            <div class="section-title">Work Experience</div>
            <?php foreach ($data['work_experience'] as $exp): ?>
            <div class="item">
                <div class="item-header">
                    <div class="item-title"><?php echo htmlspecialchars($exp['experience_name'] ?? 'Position'); ?></div>
                    <div class="item-subtitle"><?php echo htmlspecialchars($exp['experience_company'] ?? 'Company'); ?></div>
                    <div class="item-date">
                        <?php 
                        $start = $exp['start_date'] ?? '';
                        $end = $exp['end_date'] ?? 'Present';
                        if ($start) {
                            echo htmlspecialchars(date('M Y', strtotime($start)));
                            echo ' - ';
                            echo $end === 'Present' ? 'Present' : htmlspecialchars(date('M Y', strtotime($end)));
                        }
                        ?>
                    </div>
                </div>
                <?php if (!empty($exp['experience_description'])): ?>
                <div class="item-description">
                    <?php echo nl2br(htmlspecialchars($exp['experience_description'])); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Education -->
        <?php if (!empty($data['education']) && count($data['education']) > 0): ?>
        <div class="section">
            <div class="section-title">Education</div>
            <?php foreach ($data['education'] as $edu): ?>
            <div class="item">
                <div class="item-header">
                    <div class="item-title"><?php echo htmlspecialchars($edu['education_level'] ?? 'Degree'); ?></div>
                    <div class="item-subtitle"><?php echo htmlspecialchars($edu['school_name'] ?? 'School'); ?></div>
                    <div class="item-date">
                        <?php 
                        $start = $edu['start_date'] ?? '';
                        $end = $edu['end_date'] ?? '';
                        if ($start) {
                            echo htmlspecialchars(date('Y', strtotime($start)));
                            if ($end) echo ' - ' . htmlspecialchars(date('Y', strtotime($end)));
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Skills -->
        <?php if (!empty($data['skills']) && count($data['skills']) > 0): ?>
        <div class="section">
            <div class="section-title">Skills</div>
            <div class="skills-container">
                <?php 
                // Group skills by category
                $grouped_skills = [];
                foreach ($data['skills'] as $skill) {
                    $category = $skill['category'] ?? 'Other';
                    if (!isset($grouped_skills[$category])) {
                        $grouped_skills[$category] = [];
                    }
                    $grouped_skills[$category][] = $skill['skill_name'] ?? '';
                }
                
                foreach ($grouped_skills as $category => $skills): 
                ?>
                <div class="skill-category">
                    <div class="skill-category-name"><?php echo htmlspecialchars($category); ?></div>
                    <div class="skills-list">
                        <?php foreach ($skills as $skill): ?>
                            <span class="skill-item"><?php echo htmlspecialchars($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Achievements -->
        <?php if (!empty($data['achievements']) && count($data['achievements']) > 0): ?>
        <div class="section">
            <div class="section-title">Achievements & Certifications</div>
            <?php foreach ($data['achievements'] as $ach): ?>
            <div class="achievement-item">
                <div class="achievement-title"><?php echo htmlspecialchars($ach['achievement_name'] ?? 'Achievement'); ?></div>
                <?php if (!empty($ach['achievement_organization'])): ?>
                <div class="achievement-org"><?php echo htmlspecialchars($ach['achievement_organization']); ?></div>
                <?php endif; ?>
                <?php if (!empty($ach['date_received'])): ?>
                <div class="achievement-date">
                    <?php echo htmlspecialchars(date('F Y', strtotime($ach['date_received']))); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($ach['description'])): ?>
                <div class="achievement-desc"><?php echo nl2br(htmlspecialchars($ach['description'])); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Preferences -->
        <?php if (!empty($data['preferences'])): ?>
        <div class="section">
            <div class="section-title">Job Preferences</div>
            <div class="preferences-grid">
                <?php if (!empty($data['preferences']['job_type'])): ?>
                <div class="preference-item">
                    <div class="preference-label">Preferred Job Type:</div>
                    <div class="preference-value"><?php echo htmlspecialchars($data['preferences']['job_type']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($data['preferences']['industry'])): ?>
                <div class="preference-item">
                    <div class="preference-label">Preferred Industry:</div>
                    <div class="preference-value"><?php echo htmlspecialchars($data['preferences']['industry']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            Generated on <?php echo date('F d, Y'); ?> | WorkMuna Resume
        </div>
    </div>
</body>
</html>
