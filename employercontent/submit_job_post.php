<?php
// Suppress HTML error output - we need clean JSON
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once '../database.php';

header('Content-Type: application/json');

// Wrap everything in try-catch to ensure JSON output
try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Get company_id from company table where user_id matches
    $companyCheckSql = "SELECT company_id FROM company WHERE user_id = ?";
    $stmt = $conn->prepare($companyCheckSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare company check: ' . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $company_id = null;
    if ($result->num_rows > 0) {
        $company = $result->fetch_assoc();
        $company_id = $company['company_id'] ? (int)$company['company_id'] : null;
    }
    $stmt->close();

    // Validate required fields (budget is optional)
    $required_fields = ['job_post_name', 'job_description', 'job_category_id', 'city_mun_id', 'vacancies', 'job_type_id', 'education_level_id', 'experience_level_id', 'work_setup_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]);
            exit;
        }
    }

    // Validate at least one skill is selected
    if (empty($_POST['skill_ids']) || !is_array($_POST['skill_ids'])) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one skill']);
        exit;
    }

    // Get form data
    $job_post_name = trim($_POST['job_post_name']);
    $job_description = trim($_POST['job_description']);
    $job_category_id = (int)$_POST['job_category_id'];
    $city_mun_id = (int)$_POST['city_mun_id'];
    $barangay_id = !empty($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;
    $location_input = $_POST['location_street'] ?? $_POST['location'] ?? '';
    $location_street = trim($location_input);
    $vacancies = (int)$_POST['vacancies'];
    $budget = !empty($_POST['budget']) ? trim($_POST['budget']) : null;
    $benefits = trim($_POST['benefits'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $job_type_id = (int)$_POST['job_type_id'];
    $work_setup_id = (int)$_POST['work_setup_id'];
    $education_level_id = (int)$_POST['education_level_id'];
    $experience_level_id = (int)$_POST['experience_level_id'];
    $skill_ids = array_map('intval', $_POST['skill_ids']);

    // Validate city exists and get city name
    $cityCheckSql = "SELECT city_mun_name FROM city_mun WHERE city_mun_id = ?";
    $stmt = $conn->prepare($cityCheckSql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $stmt->bind_param("i", $city_mun_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid city/municipality']);
        exit;
    }
    $city_data = $result->fetch_assoc();
    $city_name = $city_data['city_mun_name'];
    $stmt->close();

    // Validate barangay belongs to city if provided and get barangay name
    $barangay_name = null;
    if ($barangay_id) {
        $barangayCheckSql = "SELECT barangay_name FROM barangay WHERE barangay_id = ? AND city_mun_id = ?";
        $stmt = $conn->prepare($barangayCheckSql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        $stmt->bind_param("ii", $barangay_id, $city_mun_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid barangay for selected city']);
            exit;
        }
        $barangay_data = $result->fetch_assoc();
        $barangay_name = $barangay_data['barangay_name'];
        $stmt->close();
    }

    // Build address_line by concatenating city and barangay
    $address_line = $city_name;
    if ($barangay_name) {
        $address_line .= ', ' . $barangay_name;
    }
    if ($location_street !== '') {
        $address_line .= ', ' . $location_street;
    }

    $requirementsValue = $requirements !== '' ? $requirements : null;
    $locationStreetValue = $location_street !== '' ? $location_street : null;

    // Start transaction
    $conn->begin_transaction();

    // Set job_status_id to 1 (assuming 1 = 'Open' in your job_status table)
    $job_status_id = 1;

    // 1. Insert job_post (now includes company_id)
    $insertJobSql = "INSERT INTO job_post 
        (user_id, company_id, job_post_name, job_description, requirements, vacancies, budget, benefits, job_type_id, work_setup_id, education_level_id, experience_level_id, job_status_id, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($insertJobSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare job post insert: ' . $conn->error);
    }
    
    // Type string: i=int, s=string
    // user_id(i), company_id(i), job_post_name(s), job_description(s), requirements(s), vacancies(i), budget(s), benefits(s), job_type_id(i), work_setup_id(i), education_level_id(i), experience_level_id(i), job_status_id(i)
    $stmt->bind_param("iisssissiiiii", 
        $user_id,            // i - integer
        $company_id,         // i - integer (nullable, from company table)
        $job_post_name,      // s - string
        $job_description,    // s - string
        $requirementsValue,  // s - string (nullable)
        $vacancies,          // i - integer
        $budget,             // s - string (nullable)
        $benefits,           // s - string
        $job_type_id,        // i - integer
        $work_setup_id,      // i - integer
        $education_level_id, // i - integer
        $experience_level_id,// i - integer
        $job_status_id       // i - integer
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to insert job post: ' . $stmt->error);
    }

    $job_post_id = $stmt->insert_id;
    $stmt->close();

    // 2. Insert job_post_location
    $insertLocationSql = "INSERT INTO job_post_location 
        (job_post_id, city_mun_id, barangay_id, location_street, address_line, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($insertLocationSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare location insert: ' . $conn->error);
    }
    $stmt->bind_param("iiiss", $job_post_id, $city_mun_id, $barangay_id, $locationStreetValue, $address_line);

    if (!$stmt->execute()) {
        throw new Exception('Failed to insert job location: ' . $stmt->error);
    }

    $job_location_id = $stmt->insert_id;
    $stmt->close();

    // 3. Update job_post with job_location_id
    $updateJobSql = "UPDATE job_post SET job_location_id = ? WHERE job_post_id = ?";
    $stmt = $conn->prepare($updateJobSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare job post update: ' . $conn->error);
    }
    $stmt->bind_param("ii", $job_location_id, $job_post_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update job post with location: ' . $stmt->error);
    }
    $stmt->close();

    // 5. Insert skills into job_post_skills (multiple rows)
    $insertSkillSql = "INSERT INTO job_post_skills (job_post_id, job_category_id, skill_id) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insertSkillSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare skills insert: ' . $conn->error);
    }

    foreach ($skill_ids as $skill_id) {
        $stmt->bind_param("iii", $job_post_id, $job_category_id, $skill_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert skill: ' . $stmt->error);
        }
    }
    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Clear draft from session
    unset($_SESSION['job_post_draft']);

    echo json_encode([
        'success' => true, 
        'message' => 'Job posted successfully!',
        'job_post_id' => $job_post_id,
        'company_id' => $company_id
    ]);

} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>
