# Resume PDF Generation System - WorkMuna

## Overview
This system allows employers to generate and download PDF resumes for matched applicants using Dompdf.

## Files Created

### 1. `employer/download-resume.php`
Main PHP file that handles resume PDF generation for employers.

**Features:**
- Authentication check (must be logged in as employer)
- Match verification using `isMatched()` function
- Fetches complete resume data from database
- Prepares PDF using Dompdf
- Currently displays success page (download disabled)

**Usage:**
```
GET /employer/download-resume.php?applicant_id=123
```

**Parameters:**
- `applicant_id` (required): The user ID of the applicant

**Security:**
- Checks if user is logged in
- Verifies employer-applicant match before allowing access
- Returns 403 Forbidden if not matched

### 2. `templates/resume_pdf.php`
HTML template for PDF generation with inline CSS.

**Features:**
- Clean, professional layout
- Uses DejaVu Sans font (included with Dompdf)
- Inline CSS only (no external stylesheets)
- No JavaScript
- Responsive sections that handle empty data gracefully

**Sections Included:**
- Header (name, email, phone)
- Professional Summary
- Location
- Work Experience
- Education
- Skills (grouped by category)
- Achievements & Certifications
- Job Preferences

## Database Tables Used

The system fetches data from the following tables:

### Core Tables
- `user` - Basic user information (name, email, phone)
- `resume` - Resume metadata and professional summary
- `matches` - Employer-applicant matches

### Resume Data Tables
- `applicant_location` - Address information
- `applicant_experience` - Work history
- `applicant_education` - Educational background
- `applicant_skills` - Skills and competencies
- `applicant_achievements` - Awards and certifications
- `resume_preference` - Job preferences

### Reference Tables
- `barangay` - Location barangay names
- `city_mun` - City/municipality names
- `experience_level` - Experience level descriptions
- `education_level` - Education level descriptions
- `skills` - Skill names
- `job_category` - Skill categories
- `job_type` - Job type preferences
- `industry` - Industry preferences

## Setup Instructions

### 1. Install Dompdf (Already Completed)
```bash
cd c:\xampp\htdocs\WORKSAD
composer require dompdf/dompdf
```

### 2. Verify File Structure
```
WORKSAD/
├── vendor/
│   └── autoload.php
├── templates/
│   └── resume_pdf.php
├── employer/
│   └── download-resume.php
└── database.php
```

### 3. Test the System
1. Log in as an employer
2. Find a matched applicant
3. Navigate to: `employer/download-resume.php?applicant_id={ID}`
4. You should see a success page with resume data summary

## Enabling PDF Download

Currently, the PDF download is **disabled** for employers. The system prepares the PDF but displays a success page instead.

### To Enable Download Options:

Edit `employer/download-resume.php` and uncomment one of the following options (around line 330):

#### Option 1: View PDF in Browser (Inline)
```php
$dompdf->stream("resume_" . $resumeData['full_name'] . ".pdf", [
    "Attachment" => false  // Display inline
]);
```

#### Option 2: Force Download
```php
$dompdf->stream("resume_" . $resumeData['full_name'] . ".pdf", [
    "Attachment" => true  // Force download
]);
```

#### Option 3: Save to Server
```php
$output = $dompdf->output();
$filename = __DIR__ . '/../uploads/resumes/resume_' . $applicant_id . '_' . time() . '.pdf';

// Create directory if it doesn't exist
if (!file_exists(dirname($filename))) {
    mkdir(dirname($filename), 0755, true);
}

file_put_contents($filename, $output);
echo "PDF saved to: " . $filename;
```

## Security Features

### 1. Authentication
- Checks `$_SESSION['user_id']` to ensure user is logged in
- Returns 401 Unauthorized if not authenticated

### 2. Authorization
- Uses `isMatched()` function to verify employer-applicant relationship
- Only matched applicants' resumes are accessible
- Returns 403 Forbidden if not matched

### 3. Input Validation
- Validates `applicant_id` parameter
- Uses prepared statements for all database queries
- Escapes output with `htmlspecialchars()`

## Function Reference

### `isMatched($conn, $employerId, $applicantId)`
Checks if employer and applicant have a match record.

**Parameters:**
- `$conn` (mysqli): Database connection
- `$employerId` (int): Employer user ID
- `$applicantId` (int): Applicant user ID

**Returns:** `bool` - True if matched, false otherwise

### `fetchResumeData($conn, $applicantId)`
Fetches complete resume data for an applicant.

**Parameters:**
- `$conn` (mysqli): Database connection
- `$applicantId` (int): Applicant user ID

**Returns:** `array` - Associative array with resume data

**Data Structure:**
```php
[
    'full_name' => string,
    'email' => string,
    'phone' => string,
    'professional_summary' => string,
    'location' => string,
    'work_experience' => array,
    'education' => array,
    'skills' => array,
    'achievements' => array,
    'preferences' => array
]
```

### `preparePDF($data)`
Prepares Dompdf instance with rendered HTML.

**Parameters:**
- `$data` (array): Resume data array

**Returns:** `Dompdf` - Configured and rendered Dompdf instance

## Customization

### Styling the PDF Template
Edit `templates/resume_pdf.php` to customize:
- Colors (search for hex codes like `#2563eb`)
- Font sizes (adjust `font-size` properties)
- Layout sections (add/remove/reorder sections)
- Section styles (modify CSS classes)

### Adding New Data Fields
1. Update `fetchResumeData()` to include new database queries
2. Add data to the returned array
3. Update `templates/resume_pdf.php` to display new fields

## Testing Checklist

- [ ] Employer authentication works
- [ ] Match verification prevents unauthorized access
- [ ] Resume data loads correctly
- [ ] PDF generates without errors
- [ ] All sections display properly
- [ ] Empty sections are handled gracefully
- [ ] Date formatting works correctly
- [ ] Special characters display properly
- [ ] DejaVu Sans font renders correctly

## Troubleshooting

### Error: "Unauthorized: Please log in"
**Solution:** Ensure you're logged in as an employer and have an active session.

### Error: "Forbidden: You can only access resumes of matched applicants"
**Solution:** Verify that the employer and applicant have a match record in the `matches` table.

### Error: "Bad Request: Missing applicant_id parameter"
**Solution:** Include `applicant_id` in the URL query string.

### PDF appears blank or incomplete
**Solution:** 
1. Check if resume data exists in the database
2. Verify table relationships and foreign keys
3. Check PHP error logs for SQL errors

### Font issues or missing characters
**Solution:** Dompdf includes DejaVu Sans by default. If issues persist, verify Dompdf installation:
```bash
composer show dompdf/dompdf
```

## Best Practices

1. **Always verify matches** before allowing access to resume data
2. **Use prepared statements** for all database queries
3. **Escape output** with `htmlspecialchars()` to prevent XSS
4. **Log errors** for debugging without exposing details to users
5. **Test with various data** (empty fields, special characters, long text)
6. **Limit file sizes** if implementing server storage option
7. **Clean up old PDFs** if storing on server to manage disk space

## Future Enhancements

Potential features to add:
- PDF caching to improve performance
- Custom branding/logo for employer's company
- Multiple resume templates/themes
- Batch download for multiple applicants
- PDF encryption with password protection
- Email PDF directly to employer
- Resume analytics (view tracking)
- Watermarking for draft/preview versions

## Dependencies

- **PHP**: 7.4 or higher
- **MySQL/MariaDB**: 5.7 or higher
- **Composer**: For dependency management
- **Dompdf**: ^3.1 (installed via Composer)
- **DejaVu Sans font**: Included with Dompdf

## Support

For issues or questions:
1. Check PHP error logs: `c:\xampp\php\logs\php_error_log`
2. Check Apache error logs: `c:\xampp\apache\logs\error.log`
3. Enable display_errors temporarily for debugging
4. Verify database connections and table structures

## Changelog

### Version 1.0.0 (Initial Release)
- Created download-resume.php with match verification
- Created resume_pdf.php template with inline CSS
- Implemented fetchResumeData() for complete data retrieval
- Implemented preparePDF() for Dompdf configuration
- Added comprehensive security checks
- Disabled download by default (dev mode)
- Added detailed documentation

---

**Status:** ✅ Ready for development/testing
**Last Updated:** December 15, 2025
**Author:** WorkMuna Development Team
