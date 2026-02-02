# 🧪 Test Data Reference - Resume PDF Generator

## Valid Test Combinations

Based on your database `matches` table, here are valid employer-applicant combinations you can test:

### Employer ID: 86 (Primary Test Account)

| Applicant ID | Match Count | Test URL |
|--------------|-------------|----------|
| 10 | 6 matches | `?applicant_id=10` |
| 11 | 1 match | `?applicant_id=11` |
| 12 | 2 matches | `?applicant_id=12` |

### Employer ID: 85 (Secondary Test Account)

| Applicant ID | Match Count | Test URL |
|--------------|-------------|----------|
| 10 | 3 matches | `?applicant_id=10` |
| 12 | 1 match | `?applicant_id=12` |

## 🎯 Recommended Test Scenarios

### Scenario 1: Most Active Applicant
```
Employer: 86
Applicant: 10
URL: http://localhost/WORKSAD/employer/download-resume.php?applicant_id=10
Expected: ✅ Success (6 matches)
```

### Scenario 2: Moderate Activity
```
Employer: 86
Applicant: 12
URL: http://localhost/WORKSAD/employer/download-resume.php?applicant_id=12
Expected: ✅ Success (2 matches)
```

### Scenario 3: Single Match
```
Employer: 86
Applicant: 11
URL: http://localhost/WORKSAD/employer/download-resume.php?applicant_id=11
Expected: ✅ Success (1 match)
```

### Scenario 4: Unauthorized Access (Should Fail)
```
Employer: 86
Applicant: 99 (non-existent or not matched)
URL: http://localhost/WORKSAD/employer/download-resume.php?applicant_id=99
Expected: ❌ 403 Forbidden
```

### Scenario 5: Missing Parameter (Should Fail)
```
URL: http://localhost/WORKSAD/employer/download-resume.php
Expected: ❌ 400 Bad Request
```

## 🔑 Login Required

Before testing, make sure you're logged in as one of these employers:
- **User ID 86** (recommended - most matches)
- **User ID 85** (alternative)

## 📊 Expected Data for Applicant ID: 10

Based on database structure, applicant 10 should have:
- ✅ User profile (name, email, phone)
- ✅ Resume record with professional summary
- ✅ Location information
- ✅ Work experience entries
- ✅ Education entries
- ✅ Skills (grouped by category)
- ✅ Achievements/certifications
- ✅ Job preferences

## 🧪 Testing Steps

### Step 1: Login
```
1. Go to: http://localhost/WORKSAD/employer/login.php
2. Login with employer credentials (user_id 86 or 85)
3. Verify session is active
```

### Step 2: Access Test Page
```
1. Go to: http://localhost/WORKSAD/employer/test-resume-pdf.html
2. You should see the test form
```

### Step 3: Enter Applicant ID
```
1. Enter: 10 (recommended first test)
2. Click "Generate Resume PDF"
```

### Step 4: Verify Success Page
```
Expected Output:
✓ Resume PDF Ready
✓ Data Summary showing:
  - Name: [Applicant Name]
  - Email: [Email]
  - Phone: [Phone]
  - Location: [Address]
  - Work Experience: X entries
  - Education: X entries
  - Skills: X items
  - Achievements: X items
```

## 🔍 Verification Checklist

When testing, verify:
- [ ] No PHP errors in browser or logs
- [ ] Authentication check works (401 if not logged in)
- [ ] Match verification works (403 if not matched)
- [ ] All data fields populated correctly
- [ ] Counts match database records
- [ ] Success page displays properly
- [ ] Back button works

## 🐛 Troubleshooting

### Error: "Unauthorized: Please log in"
**Fix:** Log in as employer first
```
http://localhost/WORKSAD/employer/login.php
```

### Error: "Forbidden: You can only access resumes of matched applicants"
**Fix:** Use a matched applicant ID from the table above
```
Valid IDs for employer 86: 10, 11, 12
Valid IDs for employer 85: 10, 12
```

### Error: "Bad Request: Missing applicant_id parameter"
**Fix:** Add applicant_id to URL
```
✓ Correct: ?applicant_id=10
✗ Wrong: (no parameter)
```

### Empty Resume Data
**Check:**
1. Does applicant have resume record in database?
2. Run SQL: `SELECT * FROM resume WHERE user_id = 10`
3. Check if related tables have data

### PHP Errors
**Check:**
1. PHP error log: `c:\xampp\php\logs\php_error_log`
2. Apache error log: `c:\xampp\apache\logs\error.log`
3. Enable display_errors temporarily for debugging

## 📝 SQL Queries for Verification

### Check if match exists:
```sql
SELECT * FROM matches 
WHERE employer_id = 86 AND applicant_id = 10;
```

### Check applicant resume:
```sql
SELECT r.*, u.firstname, u.lastname, u.email 
FROM resume r 
JOIN user u ON r.user_id = u.user_id 
WHERE u.user_id = 10;
```

### Count resume data:
```sql
-- Work Experience
SELECT COUNT(*) FROM applicant_experience WHERE resume_id = 
  (SELECT resume_id FROM resume WHERE user_id = 10);

-- Education
SELECT COUNT(*) FROM applicant_education WHERE resume_id = 
  (SELECT resume_id FROM resume WHERE user_id = 10);

-- Skills
SELECT COUNT(*) FROM applicant_skills WHERE resume_id = 
  (SELECT resume_id FROM resume WHERE user_id = 10);

-- Achievements
SELECT COUNT(*) FROM applicant_achievements WHERE resume_id = 
  (SELECT resume_id FROM resume WHERE user_id = 10);
```

## 🎨 Next: Enable PDF Download

Once testing confirms everything works:

1. Edit `employer/download-resume.php`
2. Find line ~330 (Output Options section)
3. Uncomment this code:

```php
$dompdf->stream("resume_" . $resumeData['full_name'] . ".pdf", [
    "Attachment" => true  // true = download, false = view inline
]);
```

4. Comment out or remove the success page HTML
5. Test download functionality

## 📱 Mobile Testing

Test on mobile devices:
- iOS Safari
- Android Chrome
- Mobile responsive design
- Touch interactions

## 🌐 Browser Testing

Test on different browsers:
- Chrome
- Firefox
- Edge
- Safari

## ✅ Success Criteria

Test is successful when:
1. ✅ Authentication works
2. ✅ Match verification works
3. ✅ Data loads correctly
4. ✅ No PHP errors
5. ✅ Success page displays
6. ✅ Data counts are accurate
7. ✅ PDF can be generated (when enabled)

---

**Last Updated:** December 15, 2025  
**Database:** worksad (22).sql  
**Status:** Ready for Testing ✅
