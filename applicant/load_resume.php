<?php
session_start();
include '../database.php';

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["success"=>false, "msg"=>"Not logged in"]);
    exit;
}

try {
    $uid = $_SESSION['user_id'];
    
    // Helper function to check if table has column
    function table_has_column($conn, $table, $column) {
        $safeTable = $conn->real_escape_string($table);
        $safeCol = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeCol'");
        return ($res && $res->num_rows > 0);
    }
    
    // Get resume_id if exists
    $resume_id = null;
    $resume_query = $conn->prepare("SELECT resume_id FROM resume WHERE user_id = ?");
    if ($resume_query) {
        $resume_query->bind_param("i", $uid);
        $resume_query->execute();
        $resume_result = $resume_query->get_result();
        if ($resume_row = $resume_result->fetch_assoc()) {
            $resume_id = $resume_row['resume_id'];
        }
        $resume_query->close();
    }
    
    // Detect which anchor columns exist
    $exp_has_resume = table_has_column($conn, 'applicant_experience', 'resume_id');
    $exp_has_user = table_has_column($conn, 'applicant_experience', 'user_id');
    
    $edu_has_resume = table_has_column($conn, 'applicant_education', 'resume_id');
    $edu_has_user = table_has_column($conn, 'applicant_education', 'user_id');
    
    $skills_has_resume = table_has_column($conn, 'applicant_skills', 'resume_id');
    $skills_has_user = table_has_column($conn, 'applicant_skills', 'user_id');
    
    $ach_has_resume = table_has_column($conn, 'applicant_achievements', 'resume_id');
    $ach_has_user = table_has_column($conn, 'applicant_achievements', 'user_id');
    
    // Load all resume data for the user
    $data = [
        'work' => [],
        'education' => [],
        'skills' => [],
        'achievements' => []
    ];
    
    // Get work experience
    if ($exp_has_resume && $resume_id) {
        $work_query = $conn->prepare("
            SELECT ae.*, el.experience_level_name FROM applicant_experience ae
            LEFT JOIN experience_level el ON ae.experience_level_id = el.experience_level_id
            WHERE ae.resume_id = ?
            ORDER BY ae.start_date DESC
        ");
        $work_query->bind_param("i", $resume_id);
        $work_query->execute();
        $work_result = $work_query->get_result();
        while ($row = $work_result->fetch_assoc()) {
            $data['work'][] = $row;
        }
        $work_query->close();
    } elseif ($exp_has_user) {
        $work_query = $conn->prepare("
            SELECT ae.*, el.experience_level_name FROM applicant_experience ae
            LEFT JOIN experience_level el ON ae.experience_level_id = el.experience_level_id
            WHERE ae.user_id = ?
            ORDER BY ae.start_date DESC
        ");
        $work_query->bind_param("i", $uid);
        $work_query->execute();
        $work_result = $work_query->get_result();
        while ($row = $work_result->fetch_assoc()) {
            $data['work'][] = $row;
        }
        $work_query->close();
    }
    
    // Get education
    if ($edu_has_resume && $resume_id) {
        $edu_query = $conn->prepare("
            SELECT ae.*, el.education_level_name 
            FROM applicant_education ae
            LEFT JOIN education_level el ON ae.education_level_id = el.education_level_id
            WHERE ae.resume_id = ?
            ORDER BY ae.start_date DESC
        ");
        $edu_query->bind_param("i", $resume_id);
        $edu_query->execute();
        $edu_result = $edu_query->get_result();
        while ($row = $edu_result->fetch_assoc()) {
            $data['education'][] = $row;
        }
        $edu_query->close();
    } elseif ($edu_has_user) {
        $edu_query = $conn->prepare("
            SELECT ae.*, el.education_level_name 
            FROM applicant_education ae
            LEFT JOIN education_level el ON ae.education_level_id = el.education_level_id
            WHERE ae.user_id = ?
            ORDER BY ae.start_date DESC
        ");
        $edu_query->bind_param("i", $uid);
        $edu_query->execute();
        $edu_result = $edu_query->get_result();
        while ($row = $edu_result->fetch_assoc()) {
            $data['education'][] = $row;
        }
        $edu_query->close();
    }
    
    // Get skills
    if ($skills_has_resume && $resume_id) {
        $skill_query = $conn->prepare("
            SELECT aps.*, s.name AS skill_name, jc.job_category_name, i.industry_name
            FROM applicant_skills aps
            LEFT JOIN skills s ON aps.skill_id = s.skill_id
            LEFT JOIN job_category jc ON aps.job_category_id = jc.job_category_id
            LEFT JOIN industry i ON jc.industry_id = i.industry_id
            WHERE aps.resume_id = ?
        ");
        $skill_query->bind_param("i", $resume_id);
        $skill_query->execute();
        $skill_result = $skill_query->get_result();
        while ($row = $skill_result->fetch_assoc()) {
            $data['skills'][] = $row;
        }
        $skill_query->close();
    } elseif ($skills_has_user) {
        $skill_query = $conn->prepare("
            SELECT aps.*, s.name AS skill_name, jc.job_category_name, i.industry_name
            FROM applicant_skills aps
            LEFT JOIN skills s ON aps.skill_id = s.skill_id
            LEFT JOIN job_category jc ON aps.job_category_id = jc.job_category_id
            LEFT JOIN industry i ON jc.industry_id = i.industry_id
            WHERE aps.user_id = ?
        ");
        $skill_query->bind_param("i", $uid);
        $skill_query->execute();
        $skill_result = $skill_query->get_result();
        while ($row = $skill_result->fetch_assoc()) {
            $data['skills'][] = $row;
        }
        $skill_query->close();
    }
    
    // Get achievements  
    if ($ach_has_user) {
        // Use user_id (preferred for achievements)
        $ach_query = $conn->prepare("
            SELECT aa.* FROM applicant_achievements aa
            WHERE aa.user_id = ?
            ORDER BY aa.date_received DESC
        ");
        $ach_query->bind_param("i", $uid);
        $ach_query->execute();
        $ach_result = $ach_query->get_result();
        while ($row = $ach_result->fetch_assoc()) {
            $data['achievements'][] = $row;
        }
        $ach_query->close();
    } elseif ($ach_has_resume && $resume_id) {
        // Fallback to resume_id
        $ach_query = $conn->prepare("
            SELECT aa.* FROM applicant_achievements aa
            WHERE aa.resume_id = ?
            ORDER BY aa.date_received DESC
        ");
        $ach_query->bind_param("i", $resume_id);
        $ach_query->execute();
        $ach_result = $ach_query->get_result();
        while ($row = $ach_result->fetch_assoc()) {
            $data['achievements'][] = $row;
        }
        $ach_query->close();
    }
    
    echo json_encode(["success" => true, "data" => $data]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

exit;
