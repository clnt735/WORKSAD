# Quick Start Guide - Resume Download Feature

## 🚀 For Applicants

### How to Download Your Resume

1. **Navigate to Your Profile**
   - Go to: `http://localhost/WORKSAD/applicant/profile.php`
   - Make sure you're logged in

2. **Find the Download Button**
   - Look at the top right of the "Resume Builder" card
   - You'll see a blue button: **"Download Resume"** with a download icon

3. **Click and Download**
   - Click the button
   - Your resume will automatically download as a PDF
   - Filename format: `YourName_Resume.pdf`

### What's Included in Your PDF?
- ✅ Your name, email, phone number
- ✅ Location (barangay, city)
- ✅ Professional summary/bio
- ✅ Work experience (with dates and descriptions)
- ✅ Education history
- ✅ Skills (organized by category)
- ✅ Achievements and awards
- ✅ Job preferences

---

## 👔 For Employers

### How to View an Applicant's Resume

1. **Navigate to Matches**
   - Go to: `http://localhost/WORKSAD/employercontent/matches.php`
   - Make sure you're logged in

2. **Open Applicant Details**
   - Find a matched applicant
   - Click **"View Details"** on their card

3. **View Resume**
   - In the modal that opens, look for the blue button: **"View Resume"**
   - It's located just below the job title
   - Click the button

4. **Resume Opens**
   - Resume PDF opens in a new browser tab
   - You can view, print, or save it from there

### Security Note
- ⚠️ You can only view resumes of applicants you're matched with
- If not matched, you'll get a "Forbidden" error

---

## 🎨 Visual Guide

### Applicant Profile Page

```
┌─────────────────────────────────────────────────────────────┐
│  Profile > Resume                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌────────────────────────────────────────────────────────┐│
│  │  Resume Builder              📥 [Download Resume]      ││
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   ││
│  │  Complete                                              ││
│  │                                                        ││
│  │  📁 Work Experience  🎓 Education  ⚡ Skills  🏆 More  ││
│  └────────────────────────────────────────────────────────┘│
│                                                             │
└─────────────────────────────────────────────────────────────┘
                            ↑
                    Click here to download!
```

### Employer Matches Modal

```
┌──────────────────────────────────────────────┐
│  Applicant Details                        ×  │
├──────────────────────────────────────────────┤
│                                              │
│  💼 Software Developer                       │
│  📄 [View Resume] ← Click here!              │
│                                              │
│  ❓ Application Answers                      │
│  • Answer 1: ...                             │
│  • Answer 2: ...                             │
│                                              │
│  📅 Schedule Interview                       │
│  Month: [____] Day: [__]                     │
│  [✓ Schedule Interview]                      │
│                                              │
└──────────────────────────────────────────────┘
```

---

## 🧪 Quick Test

### Test #1: Applicant Download
```bash
1. Login as applicant
2. Go to: applicant/profile.php
3. Click "Download Resume"
4. ✓ PDF downloads with your name
```

### Test #2: Employer View
```bash
1. Login as employer (user_id: 86)
2. Go to: employercontent/matches.php
3. Click "View Details" on any match
4. Click "View Resume"
5. ✓ PDF opens in new tab
```

---

## ⚡ Keyboard Shortcuts

### Applicant
- Navigate to profile: Type in URL bar
- Download: Click button (no keyboard shortcut yet)

### Employer
- View resume: Click button in modal
- Close modal: `Esc` key or click ×

---

## 🔧 Technical Details

### Applicant Download
**Endpoint:** `applicant/download-my-resume.php`
**Method:** GET (via window.location)
**Authentication:** Session-based
**Response:** PDF file stream

### Employer View
**Endpoint:** `employer/download-resume.php?applicant_id=[id]`
**Method:** GET (via window.open)
**Authentication:** Session + match verification
**Response:** PDF in new tab

---

## 🐛 Common Issues

### Issue: "Unauthorized" Error
**Cause:** Not logged in
**Fix:** Log in first, then try again

### Issue: "Forbidden" Error (Employer)
**Cause:** Not matched with applicant
**Fix:** Only matched applicants' resumes can be viewed

### Issue: Blank PDF
**Cause:** No resume data in database
**Fix:** Complete your resume in profile page first

### Issue: Download Button Not Visible
**Cause:** Page not loaded or CSS issue
**Fix:** Refresh page, check browser console

---

## 📱 Mobile Support

### Applicant
- ✅ Download button is responsive
- ✅ Works on mobile browsers
- ✅ PDF downloads to mobile device

### Employer
- ✅ View Resume button works on mobile
- ✅ PDF opens in mobile browser
- ✅ Can share or save from mobile

---

## 🎯 Best Practices

### For Applicants
1. **Keep Resume Updated**
   - Update your experience, education, skills regularly
   - PDF will always show latest data

2. **Complete All Sections**
   - Fill in bio/summary
   - Add work experience with descriptions
   - List all relevant skills

3. **Verify Before Sharing**
   - Download and review your PDF
   - Check for typos or missing info

### For Employers
1. **Review Before Interview**
   - View resume before scheduling interview
   - Take notes on experience and skills

2. **Save Important Resumes**
   - Use browser's save feature to keep copies
   - Organize by candidate name

3. **Professional Use**
   - Only access matched applicants' resumes
   - Respect candidate privacy

---

## 📞 Need Help?

### Quick Checks
1. ✅ Are you logged in?
2. ✅ Is your resume complete? (applicants)
3. ✅ Are you matched? (employers)
4. ✅ Is your browser up to date?

### Still Having Issues?
- Check browser console (F12) for errors
- Clear browser cache
- Try different browser
- Contact system administrator

---

## 🎉 Success!

If everything works:
- ✅ Applicants can download their resume anytime
- ✅ Employers can view matched applicants' resumes
- ✅ PDFs are professional and well-formatted
- ✅ All data is up-to-date from database

**Enjoy the new feature!** 🚀

---

**Version:** 1.0  
**Last Updated:** December 15, 2025  
**Status:** Production Ready
