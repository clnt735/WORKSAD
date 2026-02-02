# Resume Download Feature - Testing Checklist

## 📋 Pre-Testing Setup

### Environment Requirements
- [ ] XAMPP is running (Apache + MySQL)
- [ ] Database is accessible
- [ ] Composer dependencies installed (Dompdf v3.1.4)
- [ ] Browser supports PDF viewing
- [ ] Session cookies enabled

### Test Data Verification
- [ ] At least one applicant account exists with resume data
- [ ] At least one employer account exists
- [ ] At least one match exists in `matches` table
- [ ] Test with employer_id: 86 and applicant_id: 10 (known valid match)

---

## 🧪 Test Suite 1: Applicant Resume Download

### Test 1.1: Basic Download Functionality
**URL:** `http://localhost/WORKSAD/applicant/profile.php`

**Steps:**
1. [ ] Log in as applicant (any valid applicant account)
2. [ ] Navigate to profile page
3. [ ] Scroll to "Resume Builder" section
4. [ ] Locate "Download Resume" button (top right)
5. [ ] Click the button

**Expected Results:**
- [ ] Button is visible and styled correctly (blue/accent color)
- [ ] Button shows download icon
- [ ] PDF downloads automatically
- [ ] Filename format: `FirstName_LastName_Resume.pdf`
- [ ] PDF opens successfully in PDF viewer
- [ ] No error messages appear

**Actual Results:**
```
[Write your test results here]
```

---

### Test 1.2: PDF Content Verification
**Prerequisites:** Test 1.1 passed

**Steps:**
1. [ ] Open downloaded PDF
2. [ ] Verify all sections are present

**Expected Results:**
- [ ] Header section shows: Name, Email, Phone, Location
- [ ] Professional Summary section (if bio exists)
- [ ] Work Experience section with:
  - [ ] Job titles
  - [ ] Company names
  - [ ] Date ranges (formatted as "MMM YYYY")
  - [ ] Experience levels
  - [ ] Descriptions
- [ ] Education section with:
  - [ ] School names
  - [ ] Education levels
  - [ ] Date ranges
- [ ] Skills section with:
  - [ ] Skills grouped by category
  - [ ] Category headings visible
- [ ] Achievements section (if any exist)
- [ ] Job Preferences section
- [ ] Footer with generation date
- [ ] No PHP errors or broken formatting
- [ ] All text is readable (no overlapping)
- [ ] Professional appearance

**Actual Results:**
```
[Write your test results here]
```

---

### Test 1.3: Empty Resume Handling
**Prerequisites:** Create applicant with empty/minimal resume

**Steps:**
1. [ ] Log in as applicant with empty resume
2. [ ] Click "Download Resume"

**Expected Results:**
- [ ] PDF still generates (no error)
- [ ] Empty sections show "No [section] added yet" or similar
- [ ] Basic info (name, email) still displays
- [ ] PDF layout remains intact

**Actual Results:**
```
[Write your test results here]
```

---

### Test 1.4: Security - Unauthenticated Access
**Prerequisites:** Logged out

**Steps:**
1. [ ] Log out completely
2. [ ] Navigate directly to: `http://localhost/WORKSAD/applicant/download-my-resume.php`

**Expected Results:**
- [ ] 401 Unauthorized error
- [ ] Error message: "Unauthorized: Please log in."
- [ ] No PDF generated

**Actual Results:**
```
[Write your test results here]
```

---

## 🧪 Test Suite 2: Employer Resume View

### Test 2.1: Basic View Functionality
**URL:** `http://localhost/WORKSAD/employercontent/matches.php`

**Steps:**
1. [ ] Log in as employer (user_id: 86 recommended)
2. [ ] Navigate to matches page
3. [ ] Locate a matched applicant card
4. [ ] Click "View Details" button
5. [ ] Modal opens
6. [ ] Locate "View Resume" button (below job title)
7. [ ] Click "View Resume" button

**Expected Results:**
- [ ] Button is visible in modal
- [ ] Button styled with blue color and file icon
- [ ] New browser tab opens
- [ ] PDF displays inline in browser (not forced download)
- [ ] PDF shows applicant's resume
- [ ] No error messages
- [ ] Original tab/modal remains open

**Actual Results:**
```
[Write your test results here]
```

---

### Test 2.2: PDF Content Verification (Employer Side)
**Prerequisites:** Test 2.1 passed

**Steps:**
1. [ ] Verify PDF in new tab
2. [ ] Check all sections

**Expected Results:**
- [ ] Same content as applicant download
- [ ] All sections present and formatted correctly
- [ ] Applicant's data is accurate
- [ ] No employer-specific information shown
- [ ] Can print/save PDF from browser

**Actual Results:**
```
[Write your test results here]
```

---

### Test 2.3: Security - Match Verification
**Prerequisites:** Know employer and applicant IDs that are NOT matched

**Steps:**
1. [ ] Log in as employer
2. [ ] Manually navigate to: `http://localhost/WORKSAD/employer/download-resume.php?applicant_id=[unmatched_id]`

**Expected Results:**
- [ ] 403 Forbidden error
- [ ] Error message: "Forbidden: You can only access resumes of matched applicants."
- [ ] No PDF generated

**Actual Results:**
```
[Write your test results here]
```

---

### Test 2.4: Security - Unauthenticated Access
**Prerequisites:** Logged out

**Steps:**
1. [ ] Log out completely
2. [ ] Navigate to: `http://localhost/WORKSAD/employer/download-resume.php?applicant_id=10`

**Expected Results:**
- [ ] 401 Unauthorized error
- [ ] Error message: "Unauthorized: Please log in."
- [ ] No PDF generated

**Actual Results:**
```
[Write your test results here]
```

---

### Test 2.5: Security - Missing Parameter
**Prerequisites:** Logged in as employer

**Steps:**
1. [ ] Navigate to: `http://localhost/WORKSAD/employer/download-resume.php` (no applicant_id)

**Expected Results:**
- [ ] 400 Bad Request error
- [ ] Error message: "Bad Request: Missing applicant_id parameter."
- [ ] No PDF generated

**Actual Results:**
```
[Write your test results here]
```

---

### Test 2.6: Security - Invalid Parameter
**Prerequisites:** Logged in as employer

**Steps:**
1. [ ] Navigate to: `http://localhost/WORKSAD/employer/download-resume.php?applicant_id=abc`
2. [ ] Navigate to: `http://localhost/WORKSAD/employer/download-resume.php?applicant_id=-1`
3. [ ] Navigate to: `http://localhost/WORKSAD/employer/download-resume.php?applicant_id=999999`

**Expected Results:**
- [ ] No PDF generated or empty PDF
- [ ] No SQL errors
- [ ] Proper error handling

**Actual Results:**
```
[Write your test results here]
```

---

## 🧪 Test Suite 3: UI/UX Testing

### Test 3.1: Button Visibility (Applicant)
**Steps:**
1. [ ] View profile page on desktop (1920x1080)
2. [ ] View on tablet (768x1024)
3. [ ] View on mobile (375x667)

**Expected Results:**
- [ ] Button visible on all screen sizes
- [ ] Button aligned to right on desktop
- [ ] Button doesn't overflow on mobile
- [ ] Icon + text visible on all sizes

**Actual Results:**
```
[Write your test results here]
```

---

### Test 3.2: Button Visibility (Employer)
**Steps:**
1. [ ] Open modal on desktop
2. [ ] Open modal on tablet
3. [ ] Open modal on mobile

**Expected Results:**
- [ ] Button visible in modal on all sizes
- [ ] Button positioned correctly below job title
- [ ] Button accessible (not hidden behind other elements)

**Actual Results:**
```
[Write your test results here]
```

---

### Test 3.3: Button Hover States
**Steps:**
1. [ ] Hover over "Download Resume" button (applicant)
2. [ ] Hover over "View Resume" button (employer)

**Expected Results:**
- [ ] Hover effect visible (color change, shadow, etc.)
- [ ] Cursor changes to pointer
- [ ] Smooth transition

**Actual Results:**
```
[Write your test results here]
```

---

## 🧪 Test Suite 4: Cross-Browser Testing

### Test 4.1: Chrome
- [ ] Applicant download works
- [ ] Employer view works
- [ ] PDF displays correctly
- [ ] No console errors

### Test 4.2: Firefox
- [ ] Applicant download works
- [ ] Employer view works
- [ ] PDF displays correctly
- [ ] No console errors

### Test 4.3: Edge
- [ ] Applicant download works
- [ ] Employer view works
- [ ] PDF displays correctly
- [ ] No console errors

### Test 4.4: Safari (if available)
- [ ] Applicant download works
- [ ] Employer view works
- [ ] PDF displays correctly
- [ ] No console errors

---

## 🧪 Test Suite 5: Performance Testing

### Test 5.1: Generation Speed
**Steps:**
1. [ ] Time how long it takes to generate PDF
2. [ ] Test with small resume (few entries)
3. [ ] Test with large resume (many entries)

**Expected Results:**
- [ ] Small resume: < 2 seconds
- [ ] Large resume: < 5 seconds
- [ ] No timeout errors

**Actual Results:**
```
Small resume: ___ seconds
Large resume: ___ seconds
```

---

### Test 5.2: Multiple Simultaneous Downloads
**Steps:**
1. [ ] Open 3 browser tabs
2. [ ] Log in as applicant in all tabs
3. [ ] Click download in all tabs simultaneously

**Expected Results:**
- [ ] All PDFs generate successfully
- [ ] No database connection errors
- [ ] No file locking issues

**Actual Results:**
```
[Write your test results here]
```

---

## 🧪 Test Suite 6: Data Integrity Testing

### Test 6.1: Special Characters
**Prerequisites:** Resume with special characters in data

**Steps:**
1. [ ] Add special characters to resume data:
   - Job title: "Software Engineer & Developer"
   - Company: "ABC Corp. (Pvt.) Ltd."
   - Description: "Managed 100+ clients, improved efficiency by 50%"
2. [ ] Generate PDF

**Expected Results:**
- [ ] All special characters display correctly
- [ ] No encoding issues
- [ ] Ampersands, quotes, percentages render properly

**Actual Results:**
```
[Write your test results here]
```

---

### Test 6.2: Long Text Handling
**Prerequisites:** Resume with very long descriptions

**Steps:**
1. [ ] Add 500+ character description to work experience
2. [ ] Generate PDF

**Expected Results:**
- [ ] Text wraps correctly
- [ ] No text overflow
- [ ] Maintains readability
- [ ] Page breaks if needed

**Actual Results:**
```
[Write your test results here]
```

---

### Test 6.3: Date Formatting
**Prerequisites:** Resume with various date formats in database

**Steps:**
1. [ ] Verify dates in database (check raw format)
2. [ ] Generate PDF
3. [ ] Check date display in PDF

**Expected Results:**
- [ ] Dates formatted as "MMM YYYY" (e.g., "Jan 2023")
- [ ] Current/ongoing dates show "Present"
- [ ] All dates consistent format

**Actual Results:**
```
[Write your test results here]
```

---

## 🧪 Test Suite 7: Error Recovery Testing

### Test 7.1: Database Connection Loss
**Steps:**
1. [ ] Stop MySQL service
2. [ ] Try to generate PDF

**Expected Results:**
- [ ] Graceful error message
- [ ] No white screen of death
- [ ] Error logged to PHP error log

**Actual Results:**
```
[Write your test results here]
```

---

### Test 7.2: Dompdf Library Missing
**Steps:**
1. [ ] Temporarily rename vendor/dompdf directory
2. [ ] Try to generate PDF
3. [ ] Restore directory

**Expected Results:**
- [ ] Fatal error with clear message
- [ ] Instructions to run composer install

**Actual Results:**
```
[Write your test results here]
```

---

## 🧪 Test Suite 8: Regression Testing

### Test 8.1: Profile Page Functionality
**Steps:**
1. [ ] Verify all existing profile page features still work:
   - [ ] Edit bio
   - [ ] Add work experience
   - [ ] Add education
   - [ ] Add skills
   - [ ] Add achievements
   - [ ] Save changes

**Expected Results:**
- [ ] All features work as before
- [ ] New button doesn't interfere with existing functionality

---

### Test 8.2: Matches Page Functionality
**Steps:**
1. [ ] Verify all existing matches page features still work:
   - [ ] View applicant details
   - [ ] Schedule interview
   - [ ] Filter matches
   - [ ] Search functionality

**Expected Results:**
- [ ] All features work as before
- [ ] New button doesn't interfere with modal

---

## 📊 Test Results Summary

### Overall Status
- [ ] All critical tests passed
- [ ] All security tests passed
- [ ] All UI/UX tests passed
- [ ] No blocking issues found

### Issues Found
```
Issue #1: [Description]
Severity: [High/Medium/Low]
Status: [Open/Fixed]

Issue #2: [Description]
Severity: [High/Medium/Low]
Status: [Open/Fixed]
```

### Performance Metrics
```
Average PDF generation time: ___ seconds
Peak memory usage: ___ MB
Database queries per request: ___
```

### Browser Compatibility
```
Chrome: [✓/✗]
Firefox: [✓/✗]
Edge: [✓/✗]
Safari: [✓/✗]
```

---

## ✅ Sign-Off

### Tested By
**Name:** _________________  
**Date:** _________________  
**Environment:** _________________

### Approved By
**Name:** _________________  
**Date:** _________________  
**Signature:** _________________

---

## 📝 Notes
```
[Add any additional notes, observations, or recommendations here]
```

---

**Test Document Version:** 1.0  
**Feature Version:** 1.0.0  
**Last Updated:** December 15, 2025
