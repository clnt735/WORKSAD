<?php
$password = "YourSecurePassword";
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>
