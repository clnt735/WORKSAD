<?php
include '../database.php'; 

// Fetch all job posts with company details
$query = "
SELECT 
    jp.job_post_id,
    jp.job_post_name,
    jp.job_description,
    jp.requirements,
    jp.benefits,
    jp.budget,
    jp.vacancies,
    jp.job_location_id,
    jp.job_type_id,
    jp.job_status_id,
    jp.experience_level_id,
    jp.education_level_id,
    jp.work_setup_id,
    jp.created_at,
    jp.updated_at,
    c.company_id,
    c.company_name,
    c.industry,
    c.location AS company_location,
    c.logo,
    c.website
FROM job_post jp
LEFT JOIN company c ON jp.company_id = c.company_id
ORDER BY jp.created_at DESC
";
$result = mysqli_query($conn, $query);
?>
