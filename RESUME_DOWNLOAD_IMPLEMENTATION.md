# Resume Download & View Implementation

**Date:** December 15, 2025  
**Feature:** PDF Resume Generation for Applicants and Employers

## 📋 Overview

This implementation adds resume PDF generation and viewing capabilities to the WorkMuna platform:

1. **Applicants** can download their own resume as a PDF from their profile page
2. **Employers** can view resumes of matched applicants in their matches dashboard

## ✅ What Was Implemented

### 1. Applicant Resume Download

**File Modified:** `applicant/profile.php`
- Added "Download Resume" button in the resume-card header
- Button is positioned at the top right, aligned with the header-row
- Styled with accent color and download icon

**File Created:** `applicant/download-my-resume.php`
- Authenticates the logged-in applicant
- Fetches resume data from multiple tables:
  - `resume` - Professional summary/bio
  - `user` + `user_profile` - Basic info (name, email, phone)
  - `applicant_location` - Location with barangay and city
  - `applicant_experience` - Work experience with levels
  - `applicant_education` - Education with levels
  - `applicant_skills` - Skills grouped by category
  - `applicant_achievements` - Awards and achievements
  - `resume_preference` - Job preferences
- Generates PDF using Dompdf
- Automatically downloads the PDF with filename: `FirstName_LastName_Resume.pdf`

### 2. Employer Resume View

**File Modified:** `employercontent/matches.php`
- Added "View Resume" button in applicant details modal
- Button appears below the job application title
- Styled with blue color and file icon
- Opens resume in new browser tab

**File Modified:** `employer/download-resume.php`
- Enabled PDF generation (was previously disabled for testing)
- Changed from test mode to production mode
- PDF displays inline in browser (not forced download)
- Removed test HTML output

## 🗂️ Database Tables Used

The resume data is fetched from these tables:

| Table | Columns Used | Purpose |
|-------|-------------|---------|
| `user` | user_id | User identification |
| `user_profile` | user_profile_first_name, user_profile_middle_name, user_profile_last_name, user_profile_email_address, user_profile_contact_no | Basic info |
| `resume` | resume_id, user_id, bio | Resume metadata and summary |
| `applicant_location` | resume_id, barangay_id, city_mun_id | Address information |
| `applicant_experience` | experience_id, experience_name, experience_company, start_date, end_date, experience_description, experience_level_id, resume_id | Work history |
| `applicant_education` | applicant_education_id, education_level_id, school_name, start_date, end_date, resume_id | Education history |
| `applicant_skills` | applicant_skills_id, job_category_id, skill_id, resume_id | Skills |
| `applicant_achievements` | achievement_id, achievement_name, achievement_organization, date_received, description, resume_id | Awards |
| `resume_preference` | resume_preference_id, resume_id, job_type_id, industry_id | Job preferences |

**Reference Tables:**
- `barangay` - Barangay names
- `city_mun` - City/municipality names
- `experience_level` - Experience level names
- `education_level` - Education level names
- `skills` - Skill names
- `job_category` - Skill categories
- `job_type` - Job type names
- `industry` - Industry names

## 🎨 UI/UX Changes

### Applicant Profile Page
```
┌────────────────────────────────────────────────┐
│  Resume Builder              [Download Resume] │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│  Complete                                      │
└────────────────────────────────────────────────┘
```

### Employer Matches Modal
```
┌────────────────────────────────────────────────┐
│  Applicant Details                          ×  │
├────────────────────────────────────────────────┤
│  💼 Software Developer                         │
│  [📄 View Resume]                              │
│                                                │
│  ❓ Application Answers                        │
│  ...                                           │
└────────────────────────────────────────────────┘
```

## 🔒 Security Features

### Applicant Download
- ✅ Session authentication required
- ✅ User can only download their own resume
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS prevention (output escaping in template)

### Employer View
- ✅ Session authentication required
- ✅ Match verification (employer must be matched with applicant)
- ✅ 403 Forbidden if not matched
- ✅ SQL injection prevention
- ✅ XSS prevention

## 📄 PDF Template

The PDF is generated using:
- **Template:** `templates/resume_pdf.php`
- **Font:** DejaVu Sans (included with Dompdf)
- **Paper:** A4, Portrait orientation
- **Styling:** Professional inline CSS
- **Sections:**
  1. Header (name, contact, location)
  2. Professional Summary
  3. Work Experience (with dates, levels, descriptions)
  4. Education (with dates, levels)
  5. Skills (grouped by category)
  6. Achievements (with organization, dates)
  7. Job Preferences (type, industry)
  8. Footer (generated date)

## 🧪 Testing Instructions

### Test Applicant Download
1. Log in as an applicant
2. Navigate to profile page: `http://localhost/WORKSAD/applicant/profile.php`
3. Click "Download Resume" button at top right
4. PDF should download automatically
5. Verify PDF contains all resume information

### Test Employer View
1. Log in as employer (user_id: 86 recommended)
2. Navigate to matches: `http://localhost/WORKSAD/employercontent/matches.php`
3. Click "View Details" on any matched applicant
4. Click "View Resume" button in the modal
5. Resume PDF should open in new browser tab
6. Verify all information is displayed correctly

### Test Security
1. Try to access employer download without being logged in → Should get 401
2. Try to view resume of non-matched applicant → Should get 403
3. Try SQL injection in applicant_id parameter → Should be blocked

## 🐛 Troubleshooting

### PDF Not Generating

**Symptom:** Blank page or error message

**Solutions:**
1. Check if Dompdf is installed:
   ```bash
   composer show dompdf/dompdf
   ```
2. Verify database connection in `database.php`
3. Check PHP error log: `c:\xampp\php\logs\php_error_log`
4. Ensure resume data exists for the applicant

### "No resume found" Error

**Solutions:**
1. Verify applicant has created a resume
2. Check if `resume` table has entry for user_id
3. Run SQL query:
   ```sql
   SELECT * FROM resume WHERE user_id = [applicant_id];
   ```

### Employer Can't View Resume

**Solutions:**
1. Verify employer and applicant are matched:
   ```sql
   SELECT * FROM matches WHERE employer_id = [employer_id] AND applicant_id = [applicant_id];
   ```
2. Check if match exists before clicking "View Resume"
3. Ensure employer is logged in

### Styling Issues in PDF

**Solutions:**
1. Check if DejaVu Sans font is available
2. Verify `templates/resume_pdf.php` uses inline CSS only
3. No external CSS or JavaScript should be in template
4. Test with simpler content first

## 📊 Files Changed/Created

### Modified Files
1. `applicant/profile.php` - Added download button and JavaScript handler
2. `employercontent/matches.php` - Added view resume button, CSS, and JavaScript
3. `employer/download-resume.php` - Enabled PDF generation

### Created Files
1. `applicant/download-my-resume.php` - Applicant resume download handler
2. `RESUME_DOWNLOAD_IMPLEMENTATION.md` - This documentation

## 🚀 Future Enhancements

### Potential Improvements
1. **Email Resume** - Send resume PDF via email
2. **Custom Templates** - Multiple PDF template designs
3. **PDF Caching** - Cache generated PDFs for performance
4. **Batch Download** - Employers download multiple resumes at once
5. **Preview Mode** - Preview before download (HTML version)
6. **Profile Photo** - Include applicant photo in PDF
7. **Certificates** - Attach uploaded certificates to PDF
8. **QR Code** - Add QR code linking to online profile
9. **Watermark** - Add "Downloaded by [Employer]" watermark
10. **Analytics** - Track how many times resume was viewed

## 📝 Code Examples

### Applicant Download Usage
```javascript
// In profile.php
document.getElementById('downloadResumeBtn').addEventListener('click', function() {
    window.location.href = 'download-my-resume.php';
});
```

### Employer View Usage
```javascript
// In matches.php
function viewResume() {
    if (!currentApplicantId) {
        alert('No applicant selected.');
        return;
    }
    window.open('../employer/download-resume.php?applicant_id=' + currentApplicantId, '_blank');
}
```

### PDF Generation Flow
```
1. User clicks button
2. Request sent to download handler
3. Authentication check
4. Database queries (fetch data)
5. Data passed to template
6. Dompdf renders HTML to PDF
7. PDF streamed to browser
```

## ✨ Key Features

- ✅ One-click resume download for applicants
- ✅ One-click resume view for employers
- ✅ Professional PDF formatting
- ✅ Complete resume data included
- ✅ Secure access control
- ✅ Mobile-friendly buttons
- ✅ Inline PDF viewing (no forced download)
- ✅ Proper filename generation
- ✅ Error handling
- ✅ Empty state handling

## 📞 Support

For issues or questions:
1. Check error logs: `c:\xampp\php\logs\php_error_log`
2. Verify database structure matches expected schema
3. Test with known valid data (employer 86, applicant 10)
4. Review existing documentation:
   - `RESUME_PDF_README.md` - Complete PDF system guide
   - `TEST_DATA_REFERENCE.md` - Test data combinations

---

**Status:** ✅ Complete and Ready for Production  
**Dependencies:** Dompdf v3.1.4, PHP 8.0+, MySQL/MariaDB  
**Tested:** Local XAMPP environment
