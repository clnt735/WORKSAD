<?php
/**
 * NEW JOB MATCHING LOGIC WITH SORTING + 100% ACCURATE MATCH %
 *
 * - Supports multi-skills, multi-education, multi-experience
 * - Uses resume_id correctly
 * - Returns correct match %
 * - Sorts job posts by highest match
 */

/**
 * Get applicant profile using resume_id
 */
function getApplicantProfile($conn, $user_id) {
    $profile = [
        'skills' => [],
        'education_level_id' => null,
        'experience_level_id' => null,
        'job_titles' => [],
        'location' => [
            'city_mun_id' => null,
            'barangay_id' => null
        ],
        'preferences' => [
            'job_type_id' => null,
            'industry_id' => null
        ]
    ];

    // Get resume ID
    $stmt = $conn->prepare("SELECT resume_id FROM resume WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $resume = $stmt->get_result()->fetch_assoc();

    if (!$resume) {
        return $profile; 
    }

    $resume_id = $resume['resume_id'];

    /** 1. SKILLS */
    $stmt = $conn->prepare("
        SELECT skill_id 
        FROM applicant_skills 
        WHERE resume_id = ?
    ");
    $stmt->bind_param('i', $resume_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $profile['skills'][] = $row['skill_id'];
    }

    /** 2. EDUCATION (HIGHEST) */
    $stmt = $conn->prepare("
        SELECT MAX(education_level_id) AS highest_education
        FROM applicant_education
        WHERE resume_id = ?
    ");
    $stmt->bind_param('i', $resume_id);
    $stmt->execute();
    $edu = $stmt->get_result()->fetch_assoc();
    if ($edu && $edu['highest_education']) {
        $profile['education_level_id'] = (int)$edu['highest_education'];
    }

    /** 3. EXPERIENCE (HIGHEST) */
    $stmt = $conn->prepare("
        SELECT MAX(experience_level_id) AS highest_experience
        FROM applicant_experience
        WHERE resume_id = ?
    ");
    $stmt->bind_param('i', $resume_id);
    $stmt->execute();
    $exp = $stmt->get_result()->fetch_assoc();
    if ($exp && $exp['highest_experience']) {
        $profile['experience_level_id'] = (int)$exp['highest_experience'];
    }

    /** 3b. JOB TITLES */
    $stmt = $conn->prepare("
        SELECT experience_name
        FROM applicant_experience
        WHERE resume_id = ? AND experience_name IS NOT NULL AND experience_name != ''
    ");
    $stmt->bind_param('i', $resume_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $profile['job_titles'][] = strtolower(trim($row['experience_name']));
    }

    /** 4. LOCATION */
    $stmt = $conn->prepare("
        SELECT city_mun_id, barangay_id
        FROM applicant_location
        WHERE resume_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $resume_id);
    $stmt->execute();
    $loc = $stmt->get_result()->fetch_assoc();
    if ($loc) {
        $profile['location'] = [
            'city_mun_id' => $loc['city_mun_id'],
            'barangay_id' => $loc['barangay_id']
        ];
    }

    /** 5. PREFERENCES */
    // First try to get from resume_preference table
    $stmt = $conn->prepare("
        SELECT job_type_id, industry_id
        FROM resume_preference
        WHERE resume_id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $resume_id);
        $stmt->execute();
        $pref = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($pref) {
            $profile['preferences']['job_type_id'] = $pref['job_type_id'] ? (int)$pref['job_type_id'] : null;
            $profile['preferences']['industry_id'] = $pref['industry_id'] ? (int)$pref['industry_id'] : null;
        }
    }
    
    // Fallback to preferences column in resume table (if no data from resume_preference)
    if (!isset($pref) || !$pref) {
        $stmt = $conn->prepare("SELECT preferences FROM resume WHERE resume_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $resume_id);
            $stmt->execute();
            $prefData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($prefData && !empty($prefData['preferences'])) {
                $prefJson = json_decode($prefData['preferences'], true);
                if (is_array($prefJson)) {
                    $profile['preferences']['job_type_id'] = isset($prefJson['job_type_id']) ? (int)$prefJson['job_type_id'] : null;
                    $profile['preferences']['industry_id'] = isset($prefJson['industry_id']) ? (int)$prefJson['industry_id'] : null;
                }
            }
        }
    }

    return $profile;
}

/**
 * Get job post requirements
 */
function getJobRequirements($conn, $job_post_id) {
    $job = [
        'skills' => [],
        'education_level_id' => null,
        'experience_level_id' => null,
        'job_title' => null,
        'location' => [
            'city_mun_id' => null,
            'barangay_id' => null
        ],
        'job_type_id' => null,
        'job_category_id' => null,
        'industry_id' => null
    ];

    // Base job post with job_category_id
    $stmt = $conn->prepare("
        SELECT jp.education_level_id, jp.experience_level_id, jp.job_post_name, 
               jp.job_type_id, jp.job_category_id, jc.industry_id
        FROM job_post jp
        LEFT JOIN job_category jc ON jp.job_category_id = jc.job_category_id
        WHERE jp.job_post_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param('i', $job_post_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $job['education_level_id'] = (int)$row['education_level_id'];
            $job['experience_level_id'] = (int)$row['experience_level_id'];
            $job['job_title'] = $row['job_post_name'] ? strtolower(trim($row['job_post_name'])) : null;
            $job['job_type_id'] = isset($row['job_type_id']) && $row['job_type_id'] ? (int)$row['job_type_id'] : null;
            $job['job_category_id'] = isset($row['job_category_id']) && $row['job_category_id'] ? (int)$row['job_category_id'] : null;
            $job['industry_id'] = isset($row['industry_id']) && $row['industry_id'] ? (int)$row['industry_id'] : null;
        }
    }

    // Job skills
    $stmt = $conn->prepare("
        SELECT skill_id
        FROM job_post_skills
        WHERE job_post_id = ?
    ");
    $stmt->bind_param('i', $job_post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($s = $result->fetch_assoc()) {
        $job['skills'][] = $s['skill_id'];
    }

    // Job location
    $stmt = $conn->prepare("
        SELECT city_mun_id, barangay_id
        FROM job_post_location
        WHERE job_post_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $job_post_id);
    $stmt->execute();
    $loc = $stmt->get_result()->fetch_assoc();
    if ($loc) {
        $job['location'] = [
            'city_mun_id' => $loc['city_mun_id'],
            'barangay_id' => $loc['barangay_id']
        ];
    }

    return $job;
}

/**
 * Compute match percentage
 */
function computeMatchScore($applicant, $job) {
    $score = 0;
    $total = 7; // skills, education, experience, location, job_title, job_type, industry

    /** SKILLS */
    if (!empty($job['skills'])) {
        $matching = array_intersect($applicant['skills'], $job['skills']);
        $skillScore = count($matching) / count($job['skills']);
        $score += $skillScore;
    } else {
        $total--;
    }

    /** EDUCATION */
    if ($job['education_level_id']) {
        if ($applicant['education_level_id'] >= $job['education_level_id']) {
            $score += 1;
        }
    } else {
        $total--;
    }

    /** EXPERIENCE */
    if ($job['experience_level_id']) {
        if ($applicant['experience_level_id'] >= $job['experience_level_id']) {
            $score += 1;
        }
    } else {
        $total--;
    }

    /** JOB TITLE */
    if ($job['job_title']) {
        if (!empty($applicant['job_titles'])) {
            $titleScore = 0;
            foreach ($applicant['job_titles'] as $appTitle) {
                // Exact match
                if ($appTitle === $job['job_title']) {
                    $titleScore = 1;
                    break;
                }
                // Partial match (either contains the other)
                if (strpos($appTitle, $job['job_title']) !== false || strpos($job['job_title'], $appTitle) !== false) {
                    $titleScore = max($titleScore, 0.7);
                }
                // Word overlap for partial credit
                $appWords = explode(' ', $appTitle);
                $jobWords = explode(' ', $job['job_title']);
                $commonWords = array_intersect($appWords, $jobWords);
                if (!empty($commonWords)) {
                    $overlapScore = count($commonWords) / max(count($appWords), count($jobWords));
                    $titleScore = max($titleScore, $overlapScore * 0.5);
                }
            }
            $score += $titleScore;
        }
    } else {
        $total--;
    }

    /** LOCATION */
    if ($job['location']['city_mun_id']) {
        if ($applicant['location']['city_mun_id'] == $job['location']['city_mun_id']) {
            $score += 1;
        }
    } else {
        $total--;
    }

    /** JOB TYPE PREFERENCE */
    if ($job['job_type_id']) {
        if ($applicant['preferences']['job_type_id'] == $job['job_type_id']) {
            $score += 1;
        }
    } else {
        $total--;
    }

    /** INDUSTRY PREFERENCE */
    if ($job['industry_id']) {
        if ($applicant['preferences']['industry_id'] == $job['industry_id']) {
            $score += 1;
        }
    } else {
        $total--;
    }

    return ($total > 0) ? round(($score / $total) * 100) : 0;
}

/**
 * Master function that:
 *  - Loads all job posts
 *  - Computes match %
 *  - Sorts them highest → lowest
 */
function getMatchedJobs($conn, $user_id) {
    $applicant = getApplicantProfile($conn, $user_id);

    $result = $conn->query("SELECT job_post_id FROM job_post ORDER BY created_at DESC");

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $job_req = getJobRequirements($conn, $row['job_post_id']);
        $match = computeMatchScore($applicant, $job_req);

        $jobs[] = [
            'job_post_id' => (int)$row['job_post_id'],
            'match_percentage' => $match
        ];
    }

    // Sort by match percentage DESC
    usort($jobs, function($a, $b) {
        return $b['match_percentage'] - $a['match_percentage'];
    });

    return [
        'jobs' => $jobs,                // <--- search_jobs.php needs this
        'total' => count($jobs),        // <--- search_jobs.php expects to have this available
        'applicant_profile' => $applicant
    ];
}

