<!-- Quick Reference: Resume PDF Generation -->

# Resume PDF - Quick Reference

## 🚀 Quick Start

### Test URL
```
http://localhost/WORKSAD/employer/download-resume.php?applicant_id=10
```
*(Replace 10 with actual matched applicant ID)*

## ✅ What's Working

✓ Dompdf installed (v3.1.4)  
✓ Template created with inline CSS  
✓ Match verification implemented  
✓ Data fetching from all resume tables  
✓ PDF preparation ready  
✓ Security checks in place  

## ⚠️ What's Disabled

❌ PDF download (for employer review)  
❌ PDF viewing in browser  
❌ PDF saving to server  

## 🔓 Enable PDF Download

**Edit:** `employer/download-resume.php` (around line 330)

**Uncomment one of:**

```php
// Option 1: View in browser
$dompdf->stream("resume_" . $resumeData['full_name'] . ".pdf", [
    "Attachment" => false
]);
```

```php
// Option 2: Force download
$dompdf->stream("resume_" . $resumeData['full_name'] . ".pdf", [
    "Attachment" => true
]);
```

## 📁 Files Created

```
WORKSAD/
├── employer/download-resume.php       ← Main handler
├── templates/resume_pdf.php           ← PDF template
├── RESUME_PDF_README.md              ← Full documentation
└── RESUME_PDF_QUICKSTART.md          ← This file
```

## 🔒 Security Flow

1. Check if user logged in → 401 if not
2. Validate applicant_id param → 400 if missing
3. Verify employer-applicant match → 403 if not matched
4. Fetch resume data
5. Generate PDF
6. Show success page (or deliver PDF when enabled)

## 🧪 Testing

1. Log in as employer (user_id should be in matches table)
2. Visit: `employer/download-resume.php?applicant_id=X`
3. Should see success page with data summary
4. Check for any PHP errors in logs

## 📊 Database Dependencies

**Required tables:**
- matches (employer_id, applicant_id)
- user (firstname, lastname, email, contact_number)
- resume (professional_summary)
- applicant_experience
- applicant_education
- applicant_skills
- applicant_achievements
- applicant_location
- resume_preference

## 🎨 Customize Template

**Edit:** `templates/resume_pdf.php`

**Change colors:**
- Primary: `#2563eb` (blue)
- Success: `#16a34a` (green)
- Warning: `#f59e0b` (orange)

**Change font:**
Currently: DejaVu Sans (included)

## 🐛 Common Issues

**"Not authenticated"**  
→ Log in as employer first

**"Forbidden: You can only access..."**  
→ Need match record in `matches` table

**"Missing applicant_id"**  
→ Add `?applicant_id=123` to URL

**Blank PDF**  
→ Check if resume data exists in database

## 📞 Function Reference

### isMatched($conn, $employerId, $applicantId)
Returns true if match exists

### fetchResumeData($conn, $applicantId)
Returns array with all resume data

### preparePDF($data)
Returns configured Dompdf instance

## 🔧 Configuration

**Dompdf Options (in download-resume.php):**
- Paper: A4
- Orientation: Portrait
- Font: DejaVu Sans
- Remote assets: Disabled
- PHP execution: Enabled

## 📈 Next Steps

1. Test with real applicant data
2. Review generated PDF layout
3. Adjust styling if needed
4. Enable download option
5. Test download functionality
6. Deploy to production

## 📚 Full Documentation

See: `RESUME_PDF_README.md` for complete details

---
**Created:** December 15, 2025  
**Status:** Ready for Testing ✅
