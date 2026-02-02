<?php

require 'database_live.php'; // root path
var_dump($_GET);
if (!isset($_GET['verify'])) {
    exit("Invalid activation link.");
}

$token = $_GET['verify'];

// Check if token exists
$stmt = $conn->prepare("SELECT user_id FROM user WHERE activation_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    exit("Invalid or expired activation link.");
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Email</title>
</head>
<body>
    <h2>Your Email Verification</h2>
    <p>Click the button below to verify your account.</p>

    <form action="activate_confirm.php" method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <button type="submit" style="
            background:#4CAF50;
            padding:12px 20px;
            border:none;
            color:white;
            font-size:18px;
            cursor:pointer;
        ">✅ Verify Email</button>
    </form>
</body>
</html>
