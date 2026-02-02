<?php
session_start();
include '../database.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Sanitize + capitalize names
$first  = ucwords(strtolower(trim($_POST['first_name'] ?? '')));
$last   = ucwords(strtolower(trim($_POST['last_name'] ?? '')));
// Middle name (optional)
$middle = ucwords(strtolower(trim($_POST['middle_name'] ?? '')));

// Other fields
$email  = trim($_POST['email'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$dob    = trim($_POST['dob'] ?? '');
$municipality = !empty($_POST['municipality']) ? intval($_POST['municipality']) : null;
$barangay = !empty($_POST['barangay']) ? intval($_POST['barangay']) : null;

// -------- VALIDATION --------

// Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email format.");
}

// Phone number must be digits only
if (!preg_match("/^[0-9]{10,15}$/", $phone)) {
    die("Phone number must contain only digits (10–15 digits).");
}

// -------- PROFILE PICTURE UPLOAD --------

$photoFileName = null;

if (!empty($_FILES['profile_pic']['name'])) {

    $allowedExt = ['jpg', 'jpeg', 'png'];
    $fileName = $_FILES['profile_pic']['name'];
    $fileTmp = $_FILES['profile_pic']['tmp_name'];

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt)) {
        die("Invalid image file type. Only JPG and PNG allowed.");
    }

    // Unique file name
    $photoFileName = "user_" . $user_id . "_" . time() . "." . $ext;

    // Upload folder
    $uploadPath = "../uploads/profile_pics/" . $photoFileName;

    if (!move_uploaded_file($fileTmp, $uploadPath)) {
        die("Error uploading image.");
    }
}

// -------- CHECK IF LOCATION COLUMNS EXIST --------
$has_location_columns = false;
$col_check = $conn->query("SHOW COLUMNS FROM user_profile LIKE 'city_mun_id'");
if ($col_check && $col_check->num_rows > 0) {
    $has_location_columns = true;
}

// -------- UPDATE PROFILE --------

if ($photoFileName) {
    // Update with new picture
    if ($has_location_columns) {
        $sql = "
        UPDATE user_profile SET
            user_profile_first_name = ?,
            user_profile_middle_name = ?,
            user_profile_last_name = ?,
            user_profile_email_address = ?,
            user_profile_contact_no = ?,
            user_profile_gender = ?,
            user_profile_dob = ?,
            user_profile_photo = ?,
            city_mun_id = ?,
            barangay_id = ?,
            user_profile_updated_at = NOW()
        WHERE user_id = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssiii",
            $first, $middle, $last, 
            $email, $phone, $gender, $dob,
            $photoFileName,
            $municipality, $barangay,
            $user_id
        );
    } else {
        $sql = "
        UPDATE user_profile SET
            user_profile_first_name = ?,
            user_profile_middle_name = ?,
            user_profile_last_name = ?,
            user_profile_email_address = ?,
            user_profile_contact_no = ?,
            user_profile_gender = ?,
            user_profile_dob = ?,
            user_profile_photo = ?,
            user_profile_updated_at = NOW()
        WHERE user_id = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi",
            $first, $middle, $last, 
            $email, $phone, $gender, $dob,
            $photoFileName,
            $user_id
        );
    }

} else {
    // Update without picture change
    if ($has_location_columns) {
        $sql = "
        UPDATE user_profile SET
            user_profile_first_name = ?,
            user_profile_middle_name = ?,
            user_profile_last_name = ?,
            user_profile_email_address = ?,
            user_profile_contact_no = ?,
            user_profile_gender = ?,
            user_profile_dob = ?,
            city_mun_id = ?,
            barangay_id = ?,
            user_profile_updated_at = NOW()
        WHERE user_id = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssiii",
            $first, $middle, $last, 
            $email, $phone, $gender, $dob,
            $municipality, $barangay,
            $user_id
        );
    } else {
        $sql = "
        UPDATE user_profile SET
            user_profile_first_name = ?,
            user_profile_middle_name = ?,
            user_profile_last_name = ?,
            user_profile_email_address = ?,
            user_profile_contact_no = ?,
            user_profile_gender = ?,
            user_profile_dob = ?,
            user_profile_updated_at = NOW()
        WHERE user_id = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi",
            $first, $middle, $last, 
            $email, $phone, $gender, $dob,
            $user_id
        );
    }
}

$stmt->execute();

// Redirect
header("Location: profile.php?updated=1");
exit();
?>
