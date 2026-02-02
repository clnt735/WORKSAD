<?php
// Basic match computation script
// Run via CLI or cron; can be adapted to run per-user on resume update
include '../../database.php';

// weights (sum 100)
$weights = [
  'skills' => 40,
  'title' => 20,
  'location' => 15,
  'salary' => 10,
  'experience' => 10,
  'education' => 5
];

// helper: safe float
function pct($v){ return max(0, min(100, (float)$v)); }

// fetch all users with resumes
$users = $conn->query("SELECT user_id FROM resume");
if (!$users) die("Failed to fetch users");

// fetch all active jobs
$jobsRes = $conn->query("SELECT * FROM job_post WHERE job_status_id = 1 OR job_status_id IS NULL");
$jobs = [];
while ($j = $jobsRes->fetch_assoc()) $jobs[$j['job_post_id']] = $j;

// prefetch job skills
$jobSkills = [];
$jsRes = $conn->query("SELECT job_post_id, skill_id, weight FROM job_post_skills");
while ($r = $jsRes->fetch_assoc()) {
  $jobSkills[$r['job_post_id']][] = $r;
}

// iterate users
while ($u = $users->fetch_assoc()) {
  $uid = (int)$u['user_id'];
  // fetch user's normalized skills
  $rs = $conn->prepare("SELECT rs.skill_id, rs.proficiency_level FROM resume_skills rs WHERE rs.user_id = ?");
  $rs->bind_param('i',$uid);
  $rs->execute();
  $rv = $rs->get_result();
  $userSkills = [];
  while ($s = $rv->fetch_assoc()) $userSkills[$s['skill_id']] = $s['proficiency_level'];

  // basic derived experience years from work_experience table
  $expYears = 0;
  $we = $conn->prepare("SELECT start_date, end_date FROM work_experience WHERE resume_id IN (SELECT resume_id FROM resume WHERE user_id=?)");
  $we->bind_param('i',$uid);
  $we->execute();
  $weR = $we->get_result();
  while ($w = $weR->fetch_assoc()) {
    if (!empty($w['start_date'])) {
      $start = strtotime($w['start_date']);
      $end = !empty($w['end_date']) ? strtotime($w['end_date']) : time();
      $expYears += max(0, ($end - $start) / (365*24*60*60));
    }
  }

  // title tokens from resume experiences (very basic)
  $titleTokens = [];
  $tq = $conn->prepare("SELECT job_title FROM work_experience WHERE resume_id IN (SELECT resume_id FROM resume WHERE user_id=?)");
  $tq->bind_param('i',$uid);
  $tq->execute();
  $tres = $tq->get_result();
  while ($tr = $tres->fetch_assoc()) {
    $titleTokens = array_merge($titleTokens, preg_split('/\W+/', strtolower($tr['job_title'])));
  }

  // compute for every job
  foreach ($jobs as $jobId => $job) {
    $score = 0.0;

    // Skills match (weighted by job skill weight): compute overlap %
    $required = $jobSkills[$jobId] ?? [];
    if (count($required) > 0) {
      $totalWeight = 0; $matchedWeight = 0;
      foreach ($required as $rq) {
        $totalWeight += (float)$rq['weight'];
        if (isset($userSkills[$rq['skill_id']])) {
          // simple proficiency multiplier
          $prof = $userSkills[$rq['skill_id']];
          $mult = 1.0;
          if ($prof === 'Beginner') $mult = 0.6;
          elseif ($prof === 'Intermediate') $mult = 0.8;
          elseif ($prof === 'Advanced') $mult = 0.95;
          elseif ($prof === 'Expert') $mult = 1.0;
          $matchedWeight += ((float)$rq['weight']) * $mult;
        }
      }
      $skillsPct = $totalWeight ? ($matchedWeight / $totalWeight) * 100 : 0;
      $score += ($skillsPct * $weights['skills'] / 100);
    }

    // Title match (simple token overlap)
    $jobTitleTokens = preg_split('/\W+/', strtolower($job['job_title'] ?? ''));
    $titleMatches = array_intersect($jobTitleTokens, $titleTokens);
    $titlePct = count($jobTitleTokens) ? (count($titleMatches) / count($jobTitleTokens)) * 100 : 0;
    $score += ($titlePct * $weights['title'] / 100);

    // Location match (exact or remote)
    $locPct = 0;
    if (!empty($job['job_location']) && !empty($job['job_location'])) {
      // simple exact match; improve with geodistance if location data available
      // assume user desired locations in profile/resume not implemented here
      $locPct = 0; // default 0 unless remote
    }
    if (!empty($job['remote_option'])) $locPct = max($locPct, 100);
    $score += ($locPct * $weights['location'] / 100);

    // Salary: assume user expected salary is stored in resume.skills? (not present) -> skip conservative 0
    $salaryPct = 0;
    // If salary_min/max and user's expected available, compute overlap; left as 0 by default
    $score += ($salaryPct * $weights['salary'] / 100);

    // Experience (compare required_years with $expYears)
    $reqY = (int)($job['required_years'] ?? 0);
    $expPct = 0;
    if ($reqY <= 0) $expPct = 100;
    else $expPct = min(100, ($expYears / $reqY) * 100);
    $score += ($expPct * $weights['experience'] / 100);

    // Education match (basic substring)
    $eduPct = 0;
    if (!empty($job['education_level'])) {
      // check resume.education JSON for degree names (simplified)
      $rEdu = $conn->query("SELECT education FROM resume WHERE user_id = $uid LIMIT 1")->fetch_assoc()['education'] ?? '';
      if ($rEdu && stripos($rEdu, $job['education_level']) !== false) $eduPct = 100;
    } else $eduPct = 100;
    $score += ($eduPct * $weights['education'] / 100);

    $score = round(min(100,$score),2);

    // upsert into match_scores
    $up = $conn->prepare("
      INSERT INTO match_scores (user_id, job_post_id, score, computed_at)
      VALUES (?, ?, ?, NOW())
      ON DUPLICATE KEY UPDATE score = VALUES(score), computed_at = NOW()
    ");
    $up->bind_param('iid', $uid, $jobId, $score);
    $up->execute();
  } // end foreach job
} // end foreach user

echo "Done\n";
