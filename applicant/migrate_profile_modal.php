<?php
// Migration script to add new columns for profile modal
require_once '../database.php';

echo "<h2>Profile Modal Migration</h2>";
echo "<pre>";

// Add social media columns to user_profile
$add_facebook = "ALTER TABLE user_profile ADD COLUMN facebook VARCHAR(255) NULL DEFAULT NULL";
$add_linkedin = "ALTER TABLE user_profile ADD COLUMN linkedin VARCHAR(255) NULL DEFAULT NULL";

try {
    if ($conn->query($add_facebook)) {
        echo "✓ Added 'facebook' column to user_profile\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "⚠ 'facebook' column already exists in user_profile\n";
        } else {
            echo "✗ Error adding 'facebook': " . $conn->error . "\n";
        }
    }
} catch (Exception $e) {
    echo "⚠ facebook column might already exist\n";
}

try {
    if ($conn->query($add_linkedin)) {
        echo "✓ Added 'linkedin' column to user_profile\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "⚠ 'linkedin' column already exists in user_profile\n";
        } else {
            echo "✗ Error adding 'linkedin': " . $conn->error . "\n";
        }
    }
} catch (Exception $e) {
    echo "⚠ linkedin column might already exist\n";
}

// Add street column to applicant_location
$add_street = "ALTER TABLE applicant_location ADD COLUMN street VARCHAR(255) NULL DEFAULT NULL";

try {
    if ($conn->query($add_street)) {
        echo "✓ Added 'street' column to applicant_location\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "⚠ 'street' column already exists in applicant_location\n";
        } else {
            echo "✗ Error adding 'street': " . $conn->error . "\n";
        }
    }
} catch (Exception $e) {
    echo "⚠ street column might already exist\n";
}

// Ensure address_line column exists in applicant_location
$add_address_line = "ALTER TABLE applicant_location ADD COLUMN address_line VARCHAR(500) NULL DEFAULT NULL";

try {
    if ($conn->query($add_address_line)) {
        echo "✓ Added 'address_line' column to applicant_location\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "⚠ 'address_line' column already exists in applicant_location\n";
        } else {
            echo "✗ Error adding 'address_line': " . $conn->error . "\n";
        }
    }
} catch (Exception $e) {
    echo "⚠ address_line column might already exist\n";
}

// Ensure bio column exists in resume table
$add_bio = "ALTER TABLE resume ADD COLUMN bio TEXT NULL DEFAULT NULL";

try {
    if ($conn->query($add_bio)) {
        echo "✓ Added 'bio' column to resume\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "⚠ 'bio' column already exists in resume\n";
        } else {
            echo "✗ Error adding 'bio': " . $conn->error . "\n";
        }
    }
} catch (Exception $e) {
    echo "⚠ bio column might already exist\n";
}

echo "\n✓ Migration complete!\n";
echo "</pre>";

echo "<a href='profile.php'>← Back to Profile</a>";

$conn->close();
?>
