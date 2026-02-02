<?php
session_start();
include '../database.php';

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["success"=>false, "msg"=>"Not logged in"]);
    exit;
}

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $uid = $_SESSION['user_id'];
    $section = $data['section'] ?? '';
    
    // Get or create resume record
    $resume_stmt = $conn->prepare("SELECT resume_id FROM resume WHERE user_id = ?");
    $resume_stmt->bind_param("i", $uid);
    $resume_stmt->execute();
    $resume_result = $resume_stmt->get_result()->fetch_assoc();
    
    if (!$resume_result) {
        // Create new resume record
        $create_resume = $conn->prepare("INSERT INTO resume (user_id) VALUES (?)");
        $create_resume->bind_param("i", $uid);
        $create_resume->execute();
        $resume_id = $conn->insert_id;
        $create_resume->close();
    } else {
        $resume_id = $resume_result['resume_id'];
    }
    $resume_stmt->close();
    
    switch($section) {
        case 'work':
            // Insert into applicant_experience
            $job_title = $data['job_title'] ?? '';
            $company = $data['company'] ?? null; // Optional
            $start_date = $data['start_date'] ?? null;
            $end_date = $data['end_date'] ?? null;
            $description = $data['description'] ?? null;
            
            if (empty($job_title)) {
                echo json_encode(["success" => false, "msg" => "Job title is required"]);
                exit;
            }
            
            // Check if updating existing experience
            if (!empty($data['experience_id'])) {
                $update_exp = $conn->prepare("
                    UPDATE applicant_experience 
                    SET experience_name = ?, experience_company = ?, start_date = ?, end_date = ?, experience_description = ?
                    WHERE experience_id = ?
                ");
                $update_exp->bind_param("sssssi", $job_title, $company, $start_date, $end_date, $description, $data['experience_id']);
                $update_exp->execute();
                $experience_id = $data['experience_id'];
                $update_exp->close();
            } else {
                // Insert new experience
                $insert_exp = $conn->prepare("INSERT INTO applicant_experience (experience_name, experience_company, start_date, end_date, experience_description) VALUES (?, ?, ?, ?, ?)");
                $insert_exp->bind_param("sssss", $job_title, $company, $start_date, $end_date, $description);
                $insert_exp->execute();
                $experience_id = $conn->insert_id;
                $insert_exp->close();
                
                // Link to resume
                $link_exp = $conn->prepare("UPDATE resume SET experience_id = ? WHERE resume_id = ?");
                $link_exp->bind_param("ii", $experience_id, $resume_id);
                $link_exp->execute();
                $link_exp->close();
            }
            
            echo json_encode(["success" => true, "experience_id" => $experience_id]);
            break;
            
        case 'education':
            // Insert into applicant_education
            $education_level_id = $data['education_level_id'] ?? null;
            $school_name = $data['school_name'] ?? '';
            $start_year = $data['start_year'] ?? null;
            $end_year = $data['end_year'] ?? null;
            
            if (empty($education_level_id) || empty($school_name)) {
                echo json_encode(["success" => false, "msg" => "Education level and institution are required"]);
                exit;
            }
            
            // Check if updating
            if (!empty($data['applicant_education_id'])) {
                $update_edu = $conn->prepare("
                    UPDATE applicant_education 
                    SET education_level_id = ?, school_name = ?, start_date = ?, end_date = ?
                    WHERE applicant_education_id = ?
                ");
                $update_edu->bind_param("isssi", $education_level_id, $school_name, $start_year, $end_year, $data['applicant_education_id']);
                $update_edu->execute();
                $edu_id = $data['applicant_education_id'];
                $update_edu->close();
            } else {
                // Insert new education
                $insert_edu = $conn->prepare("
                    INSERT INTO applicant_education (education_level_id, school_name, start_date, end_date) 
                    VALUES (?, ?, ?, ?)
                ");
                $insert_edu->bind_param("isss", $education_level_id, $school_name, $start_year, $end_year);
                $insert_edu->execute();
                $edu_id = $conn->insert_id;
                $insert_edu->close();
                
                // Link to resume
                $link_edu = $conn->prepare("UPDATE resume SET applicant_education_id = ? WHERE resume_id = ?");
                $link_edu->bind_param("ii", $edu_id, $resume_id);
                $link_edu->execute();
                $link_edu->close();
            }
            
            echo json_encode(["success" => true, "applicant_education_id" => $edu_id]);
            break;
            
        case 'skill':
            // Insert into applicant_skills
            $industry_id = $data['industry_id'] ?? null;
            $job_category_id = $data['job_category_id'] ?? null;
            $skill_id = $data['skill_id'] ?? null;
            
            if (empty($skill_id)) {
                echo json_encode(["success" => false, "msg" => "Skill is required"]);
                exit;
            }
            
            // Check if skill already exists for this user
            $check_skill = $conn->prepare("
                SELECT applicant_skills_id FROM applicant_skills 
                WHERE skill_id = ? AND applicant_skills_id IN (
                    SELECT applicant_skills_id FROM resume WHERE user_id = ?
                )
            ");
            $check_skill->bind_param("ii", $skill_id, $uid);
            $check_skill->execute();
            $existing = $check_skill->get_result()->fetch_assoc();
            $check_skill->close();
            
            if ($existing) {
                echo json_encode(["success" => false, "msg" => "Skill already added"]);
                exit;
            }
            
            // Insert new skill
            $insert_skill = $conn->prepare("
                INSERT INTO applicant_skills (industry_id, job_category_id, skill_id) 
                VALUES (?, ?, ?)
            ");
            $insert_skill->bind_param("iii", $industry_id, $job_category_id, $skill_id);
            $insert_skill->execute();
            $applicant_skills_id = $conn->insert_id;
            $insert_skill->close();
            
            // Link to resume
            $link_skill = $conn->prepare("UPDATE resume SET applicant_skills_id = ? WHERE resume_id = ?");
            $link_skill->bind_param("ii", $applicant_skills_id, $resume_id);
            $link_skill->execute();
            $link_skill->close();
            
            echo json_encode(["success" => true, "applicant_skills_id" => $applicant_skills_id]);
            break;
            
        case 'achievement':
            // Insert into applicant_achievements
            $achievement_name = $data['achievement_name'] ?? '';
            $organization = $data['achievement_organization'] ?? '';
            $date_received = $data['date_received'] ?? null;
            
            if (empty($achievement_name) || empty($organization)) {
                echo json_encode(["success" => false, "msg" => "Title and organization are required"]);
                exit;
            }
            
            // Check if updating
            if (!empty($data['achievement_id'])) {
                $update_ach = $conn->prepare("
                    UPDATE applicant_achievements 
                    SET achievement_name = ?, achievement_organization = ?, date_received = ?
                    WHERE achievement_id = ?
                ");
                $update_ach->bind_param("sssi", $achievement_name, $organization, $date_received, $data['achievement_id']);
                $update_ach->execute();
                $achievement_id = $data['achievement_id'];
                $update_ach->close();
            } else {
                // Insert new achievement
                $insert_ach = $conn->prepare("
                    INSERT INTO applicant_achievements (achievement_name, achievement_organization, date_received) 
                    VALUES (?, ?, ?)
                ");
                $insert_ach->bind_param("sss", $achievement_name, $organization, $date_received);
                $insert_ach->execute();
                $achievement_id = $conn->insert_id;
                $insert_ach->close();
                
                // Link to resume
                $link_ach = $conn->prepare("UPDATE resume SET achievement_id = ? WHERE resume_id = ?");
                $link_ach->bind_param("ii", $achievement_id, $resume_id);
                $link_ach->execute();
                $link_ach->close();
            }
            
            echo json_encode(["success" => true, "achievement_id" => $achievement_id]);
            break;
            
        default:
            echo json_encode(["success" => false, "msg" => "Invalid section"]);
    }
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

exit;
