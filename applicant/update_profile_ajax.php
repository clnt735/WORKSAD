<?php
// Profile update handler with file upload support
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
include '../database.php';

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["success"=>false, "msg"=>"Not logged in"]);
    exit;
}

try {
    $uid = $_SESSION['user_id'];
    
    // Check if this is a FormData request (with file) or JSON request
    $is_form_data = !empty($_FILES) || !empty($_POST);
    
    if ($is_form_data) {
        // FormData request - get data from $_POST
        $first = $_POST['first_name'] ?? '';
        $middle = $_POST['middle_name'] ?? '';
        $last = $_POST['last_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $municipality = !empty($_POST['municipality']) ? intval($_POST['municipality']) : null;
        $barangay = !empty($_POST['barangay']) ? intval($_POST['barangay']) : null;
        $street = $_POST['street'] ?? '';
        $facebook = $_POST['facebook'] ?? '';
        $linkedin = $_POST['linkedin'] ?? '';
        $bio = $_POST['bio'] ?? '';
    } else {
        // JSON request - decode JSON body
        $data = json_decode(file_get_contents("php://input"), true);
        $first = $data['first_name'] ?? '';
        $middle = $data['middle_name'] ?? '';
        $last = $data['last_name'] ?? '';
        $phone = $data['phone'] ?? '';
        $municipality = !empty($data['municipality']) ? intval($data['municipality']) : null;
        $barangay = !empty($data['barangay']) ? intval($data['barangay']) : null;
        $street = $data['street'] ?? '';
        $facebook = $data['facebook'] ?? '';
        $linkedin = $data['linkedin'] ?? '';
        $bio = $data['bio'] ?? '';
    }
    
    // Handle profile photo upload if present
    $photoFileName = null;
    if (!empty($_FILES['profile_pic']['name'])) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $fileName = $_FILES['profile_pic']['name'];
        $fileTmp = $_FILES['profile_pic']['tmp_name'];
        $fileSize = $_FILES['profile_pic']['size'];
        
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate extension
        if (!in_array($ext, $allowedExt)) {
            throw new Exception("Invalid image file type. Only JPG, PNG, and WEBP allowed.");
        }
        
        // Validate size (5MB max)
        if ($fileSize > 5242880) {
            throw new Exception("File size must be less than 5MB.");
        }
        
        // Generate unique filename
        $photoFileName = "PIC_" . $uid . "_" . time() . "." . $ext;
        
        // Upload folder
        $uploadPath = "../uploads/profile_pics/" . $photoFileName;
        
        // Create directory if it doesn't exist
        if (!file_exists("../uploads/profile_pics/")) {
            mkdir("../uploads/profile_pics/", 0755, true);
        }
        
        if (!move_uploaded_file($fileTmp, $uploadPath)) {
            throw new Exception("Error uploading image.");
        }
    }

    
    // Update user_profile (including social links and photo if columns exist)
    // Check if columns exist first
    $profile_columns_check = $conn->query("SHOW COLUMNS FROM user_profile LIKE 'facebook'");
    $has_social_columns = ($profile_columns_check && $profile_columns_check->num_rows > 0);
    
    if ($has_social_columns && $photoFileName) {
        // Update with photo and social links
        $stmt = $conn->prepare("
            UPDATE user_profile
            SET user_profile_first_name = ?, 
                user_profile_middle_name = ?, 
                user_profile_last_name = ?, 
                user_profile_contact_no = ?,
                user_profile_photo = ?,
                facebook = ?,
                linkedin = ?,
                user_profile_updated_at = NOW()
            WHERE user_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssssi", $first, $middle, $last, $phone, $photoFileName, $facebook, $linkedin, $uid);
    } elseif ($has_social_columns) {
        // Update with social links only (no photo)
        $stmt = $conn->prepare("
            UPDATE user_profile
            SET user_profile_first_name = ?, 
                user_profile_middle_name = ?, 
                user_profile_last_name = ?, 
                user_profile_contact_no = ?,
                facebook = ?,
                linkedin = ?,
                user_profile_updated_at = NOW()
            WHERE user_id = ?
        ");
    if (!$stmt->execute()) {
        throw new Exception("Profile update failed: " . $stmt->error);
    }
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssi", $first, $middle, $last, $phone, $facebook, $linkedin, $uid);
    } elseif ($photoFileName) {
        // Update with photo only (no social links columns)
        $stmt = $conn->prepare("
            UPDATE user_profile
            SET user_profile_first_name = ?, 
                user_profile_middle_name = ?, 
                user_profile_last_name = ?, 
                user_profile_contact_no = ?,
                user_profile_photo = ?,
                user_profile_updated_at = NOW()
            WHERE user_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssi", $first, $middle, $last, $phone, $photoFileName, $uid);
    } else {
        // Update without photo or social links
        $stmt = $conn->prepare("
            UPDATE user_profile
            SET user_profile_first_name = ?, 
                user_profile_middle_name = ?, 
                user_profile_last_name = ?, 
                user_profile_contact_no = ?,
                user_profile_updated_at = NOW()
            WHERE user_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssi", $first, $middle, $last, $phone, $uid);
    }
    
    $ok = $stmt->execute();
    $stmt->close();

    // Update location via resume table relationship
    if ($municipality !== null || $barangay !== null || !empty($street)) {
        // First, get or create resume record for this user
        $resume_check = $conn->prepare("SELECT resume_id FROM resume WHERE user_id = ?");
        if (!$resume_check) {
            throw new Exception("Resume check failed: " . $conn->error);
        }
        
        $resume_check->bind_param("i", $uid);
        $resume_check->execute();
        $resume_result = $resume_check->get_result();
        $resume_row = $resume_result->fetch_assoc();
        $resume_check->close();
        
        $resume_id = null;
        
        if ($resume_row) {
            $resume_id = $resume_row['resume_id'];
        } else {
            // Create new resume record first
            $insert_resume = $conn->prepare("INSERT INTO resume (user_id) VALUES (?)");
            if (!$insert_resume) {
                throw new Exception("Resume creation failed: " . $conn->error);
            }
            $insert_resume->bind_param("i", $uid);
            $insert_resume->execute();
            $resume_id = $conn->insert_id;
            $insert_resume->close();
        }
        
        // Build address_line (concatenate street, barangay, municipality)
        $address_parts = [];
        if (!empty($street)) $address_parts[] = $street;
        
        // Get barangay name
        if ($barangay) {
            $brgy_query = $conn->prepare("SELECT barangay_name FROM barangay WHERE barangay_id = ?");
            $brgy_query->bind_param("i", $barangay);
            $brgy_query->execute();
            $brgy_result = $brgy_query->get_result();
            if ($brgy_row = $brgy_result->fetch_assoc()) {
                $address_parts[] = $brgy_row['barangay_name'];
            }
            $brgy_query->close();
        }
        
        // Get municipality name
        if ($municipality) {
            $mun_query = $conn->prepare("SELECT city_mun_name FROM city_mun WHERE city_mun_id = ?");
            $mun_query->bind_param("i", $municipality);
            $mun_query->execute();
            $mun_result = $mun_query->get_result();
            if ($mun_row = $mun_result->fetch_assoc()) {
                $address_parts[] = $mun_row['city_mun_name'];
            }
            $mun_query->close();
        }
        
        $address_line = implode(', ', $address_parts);
        
        // Now check if location exists for this resume_id
        $loc_check = $conn->prepare("SELECT applicant_location_id FROM applicant_location WHERE resume_id = ?");
        if (!$loc_check) {
            throw new Exception("Location check failed: " . $conn->error);
        }
        
        $loc_check->bind_param("i", $resume_id);
        $loc_check->execute();
        $loc_result = $loc_check->get_result();
        $loc_row = $loc_result->fetch_assoc();
        $loc_check->close();
        
        if ($loc_row) {
            // Update existing location
            $update_loc = $conn->prepare("
                UPDATE applicant_location 
                SET city_mun_id = ?, barangay_id = ?, street = ?, address_line = ?
                WHERE resume_id = ?
            ");
            
            if (!$update_loc) {
                throw new Exception("Location update failed: " . $conn->error);
            }
            
            $update_loc->bind_param("iissi", $municipality, $barangay, $street, $address_line, $resume_id);
            $update_loc->execute();
            $update_loc->close();
        } else {
            // Create new location record linked to resume_id
            $insert_loc = $conn->prepare("
                INSERT INTO applicant_location (resume_id, city_mun_id, barangay_id, street, address_line, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            if (!$insert_loc) {
                throw new Exception("Location insert failed: " . $conn->error);
            }
            
            $insert_loc->bind_param("iisss", $resume_id, $municipality, $barangay, $street, $address_line);
            $insert_loc->execute();
            $insert_loc->close();
        }
    }
    
    // Update bio in resume table
    if (!empty($bio)) {
        // Ensure resume exists
        $resume_check = $conn->prepare("SELECT resume_id FROM resume WHERE user_id = ?");
        $resume_check->bind_param("i", $uid);
        $resume_check->execute();
        $resume_result = $resume_check->get_result();
        $resume_row = $resume_result->fetch_assoc();
        $resume_check->close();
        
        if ($resume_row) {
            $resume_id = $resume_row['resume_id'];
            $update_bio = $conn->prepare("UPDATE resume SET bio = ? WHERE resume_id = ?");
            $update_bio->bind_param("si", $bio, $resume_id);
            $update_bio->execute();
            $update_bio->close();
        } else {
            // Create resume first, then update bio
            $insert_resume = $conn->prepare("INSERT INTO resume (user_id, bio) VALUES (?, ?)");
            $insert_resume->bind_param("is", $uid, $bio);
            $insert_resume->execute();
            $insert_resume->close();
        }
    }

    echo json_encode(["success" => $ok]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

exit;