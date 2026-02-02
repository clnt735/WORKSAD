<?php
session_start();
include '../database.php';
header('Content-Type: text/html; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
if(!$body){ echo '<div>Invalid request</div>'; exit; }
$user_id = $_SESSION['user_id'] ?? ($body['user_id'] ?? null);
if(!$user_id){ echo '<div>Not authenticated</div>'; exit; }

$st = $conn->prepare("SELECT up.user_profile_first_name AS first_name, up.user_profile_last_name AS last_name, up.user_profile_email_address AS email, up.user_profile_contact_no AS phone FROM user_profile up WHERE up.user_id = ?");
$st->bind_param('i',$user_id);
$st->execute();
$prof = $st->get_result()->fetch_assoc();

$work = json_decode($body['work_experience'] ?? '[]', true) ?: [];
$educ = json_decode($body['education'] ?? '[]', true) ?: [];
$skills = json_decode($body['skills'] ?? '[]', true) ?: [];
$certs = json_decode($body['achievements'] ?? '[]', true) ?: [];

$name = trim(($prof['first_name'] ?? '') . ' ' . ($prof['last_name'] ?? '')) ?: 'Your Name';
$email = $prof['email'] ?? '';
$phone = $prof['phone'] ?? '';

?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#111;">
  <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #eee;padding-bottom:10px;margin-bottom:12px;">
    <div>
      <h1 style="margin:0;font-size:22px;"><?= htmlspecialchars($name) ?></h1>
      <div style="color:#666;padding-top:6px;"><?= htmlspecialchars($email) ?> &nbsp; • &nbsp; <?= htmlspecialchars($phone) ?></div>
    </div>
    <div style="text-align:right;color:#666;font-size:13px;">
      <div>Generated: <?= date('Y-m-d') ?></div>
    </div>
  </div>

  <?php if($work): ?>
    <section style="margin-bottom:12px;">
      <h3 style="margin:0 0 6px 0;font-size:16px;">Work Experience</h3>
      <?php foreach($work as $w): ?>
        <div style="margin-bottom:10px;">
          <div style="font-weight:600;"><?= htmlspecialchars($w['job_title'] ?? '') ?> — <span style="font-weight:500;color:#555;"><?= htmlspecialchars($w['company_name'] ?? '') ?></span></div>
          <div style="color:#666;font-size:13px;"><?= htmlspecialchars($w['start_date'] ?? '') ?> — <?= htmlspecialchars($w['end_date'] ?? '') ?></div>
          <div style="margin-top:6px;color:#333;"><?= nl2br(htmlspecialchars($w['description'] ?? '')) ?></div>
          <?php if(!empty($w['achievements'])): ?>
            <div style="margin-top:6px;font-weight:600">Key Achievements:</div>
            <ul>
              <?php foreach($w['achievements'] as $a): ?><li><?= htmlspecialchars($a) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php if($educ): ?>
    <section style="margin-bottom:12px;">
      <h3 style="margin:0 0 6px 0;font-size:16px;">Education</h3>
      <?php foreach($educ as $e): ?>
        <div style="margin-bottom:8px;">
          <div style="font-weight:600;"><?= htmlspecialchars($e['degree'] ?? '') ?> — <span style="font-weight:500;color:#555;"><?= htmlspecialchars($e['institution'] ?? '') ?></span></div>
          <div style="color:#666;font-size:13px;"><?= htmlspecialchars($e['start_year'] ?? '') ?> — <?= htmlspecialchars($e['end_year'] ?? '') ?></div>
          <div style="margin-top:6px;color:#333;"><?= nl2br(htmlspecialchars($e['description'] ?? '')) ?></div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php if($skills): ?>
    <section style="margin-bottom:12px;">
      <h3 style="margin:0 0 6px 0;font-size:16px;">Skills</h3>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach($skills as $s): ?><div style="padding:6px 8px;border-radius:6px;background:#f2f6ff;color:#183b7a;font-size:13px;"><?= htmlspecialchars($s) ?></div><?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if($certs): ?>
    <section style="margin-bottom:12px;">
      <h3 style="margin:0 0 6px 0;font-size:16px;">Achievements</h3>
      <ul>
        <?php foreach($certs as $c): ?><li style="margin-bottom:6px;color:#333;"><?= htmlspecialchars($c['title'] ?? $c) ?> — <?= htmlspecialchars($c['organization'] ?? '') ?> (<?= htmlspecialchars($c['date_received'] ?? '') ?>)</li><?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</div>
