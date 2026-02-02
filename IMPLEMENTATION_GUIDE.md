# Resume Builder Database Integration - Implementation Guide

## Files Created

### Backend PHP Files (in applicant folder):

1. **save_resume.php** - Handles saving all resume sections
   - Saves work experience to `applicant_experience` table
   - Saves education to `applicant_education` table  
   - Saves skills to `applicant_skills` table
   - Saves achievements to `applicant_achievements` table
   - Links all via `resume` table with user_id

2. **load_resume.php** - Loads all resume data for the user
   - Fetches work, education, skills, achievements
   - Joins with related tables for display names

3. **get_dropdown_data.php** - Gets education levels and industries

4. **get_categories.php** - Gets job categories for selected industry

5. **get_skills.php** - Gets skills for selected category

6. **delete_resume_item.php** - Deletes items from resume sections

## Key Changes Needed in profile.php

### 1. JavaScript Initialization (replace existing)

```javascript
// Global variables for dropdown data
let educationLevels = [];
let industries = [];

// Load dropdown data on page load
async function loadDropdownData() {
    const res = await fetch('get_dropdown_data.php');
    const data = await res.json();
    if (data.success) {
        educationLevels = data.education_levels;
        industries = data.industries;
    }
}

// Load all resume data
async function loadResumeData() {
    const res = await fetch('load_resume.php');
    const data = await res.json();
    if (data.success) {
        workData = data.data.work || [];
        eduData = data.data.education || [];
        skillsData = data.data.skills || [];
        certData = data.data.achievements || [];
        renderAll();
    }
}

// Call on page load
loadDropdownData();
loadResumeData();
```

### 2. Work Experience Changes

**Update collectWorkForm():**
```javascript
function collectWorkForm(){
  const job = document.getElementById('w_job').value.trim();
  const comp = document.getElementById('w_company').value.trim() || null; // Optional now
  if(!job) { alert('Job title is required'); return null; }
  const start = document.getElementById('w_start').value || null;
  let end = document.getElementById('w_end').value || null;
  if(document.getElementById('w_present').checked) end = 'Present';
  const desc = document.getElementById('w_desc').value.trim() || null;
  return { 
    job_title: job, 
    company: comp, 
    start_date: start, 
    end_date: end, 
    description: desc 
  };
}
```

**Update saveWorkBtn event:**
```javascript
if(saveWorkBtnEl) saveWorkBtnEl.addEventListener('click', async ()=>{
  const obj = collectWorkForm();
  if(!obj) return;
  
  const payload = {
    section: 'work',
    ...obj
  };
  
  if(editingWorkIndex >= 0 && workData[editingWorkIndex].experience_id) {
    payload.experience_id = workData[editingWorkIndex].experience_id;
  }
  
  const res = await fetch('save_resume.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  });
  
  const result = await res.json();
  if(result.success) {
    workForm.style.display='none';
    clearWorkForm();
    editingWorkIndex = -1;
    await loadResumeData(); // Reload from database
  } else {
    alert(result.msg || 'Save failed');
  }
});
```

**Update deleteWork():**
```javascript
async function deleteWork(i){
  if(!confirm('Delete this entry?')) return;
  const exp_id = workData[i].experience_id;
  
  const res = await fetch('delete_resume_item.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({section: 'work', id: exp_id})
  });
  
  const result = await res.json();
  if(result.success) {
    await loadResumeData();
  }
}
```

**Update renderWork():**
```javascript
function renderWork(){
  workCards.innerHTML = '';
  if(!workData.length) workCards.innerHTML = '<div class="small" style="padding:8px 0;">No work experience yet.</div>';
  workData.forEach((w, idx)=>{
    const div = document.createElement('div'); div.className='card';
    div.innerHTML = `<div class="left">
      <h4>${escapeHtml(w.experience_name || '')}</h4>
      <div class="meta">${escapeHtml(w.experience_company || 'N/A')} • ${escapeHtml(w.start_date||'')} - ${escapeHtml(w.end_date||'')}</div>
      <p>${escapeHtml(w.experience_description || '')}</p>
    </div>
    <div class="actions">
      <button class="editWorkBtn" onclick="editWork(${idx})"><i class="fa-regular fa-pen-to-square"></i></button>
      <button class="deleteWorkBtn" onclick="deleteWork(${idx})"><i class="far fa-trash-alt"></i></button>
    </div>`;
    workCards.appendChild(div);
  });
}
```

### 3. Education Changes

**HTML: Replace Degree input with Education Level dropdown:**

In the education form section of profile.php, replace:
```html
<div class="col field"><label>Degree *</label><input id="e_degree" type="text" placeholder="e.g., Bachelor of Science"></div>
```

With:
```html
<div class="col field">
  <label>Education Level *</label>
  <select id="e_level">
    <option value="">Select Education Level</option>
  </select>
</div>
```

**Remove description field** - Delete this entire div:
```html
<div class="field"><label>Description (Optional)</label><textarea id="e_desc" placeholder="Major, honors, relevant coursework..."></textarea></div>
```

**JavaScript Changes:**

Update clearEduForm():
```javascript
function clearEduForm(){ 
  document.getElementById('e_level').value=''; 
  document.getElementById('e_institution').value=''; 
  document.getElementById('e_start').value=''; 
  document.getElementById('e_end').value=''; 
}
```

Update populateEducationLevels():
```javascript
function populateEducationLevels() {
  const select = document.getElementById('e_level');
  select.innerHTML = '<option value="">Select Education Level</option>';
  educationLevels.forEach(level => {
    const opt = document.createElement('option');
    opt.value = level.education_level_id;
    opt.textContent = level.education_level_name;
    select.appendChild(opt);
  });
}

// Call after loading dropdown data
```

Update saveEduBtn:
```javascript
if(saveEduBtnEl) saveEduBtnEl.addEventListener('click', async ()=>{
  const level = document.getElementById('e_level').value;
  const inst = document.getElementById('e_institution').value.trim();
  if(!level || !inst){ alert('Education level and Institution are required'); return; }
  
  const payload = {
    section: 'education',
    education_level_id: level,
    school_name: inst,
    start_year: document.getElementById('e_start').value||null,
    end_year: document.getElementById('e_end').value||null
  };
  
  if(editingEduIndex>=0 && eduData[editingEduIndex].applicant_education_id) {
    payload.applicant_education_id = eduData[editingEduIndex].applicant_education_id;
  }
  
  const res = await fetch('save_resume.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  });
  
  const result = await res.json();
  if(result.success) {
    cancelEduForm();
    await loadResumeData();
  } else {
    alert(result.msg || 'Save failed');
  }
});
```

Update renderEdu():
```javascript
function renderEdu(){
  eduCards.innerHTML = '';
  if(!eduData.length) eduCards.innerHTML = '<div class="small" style="padding:8px 0;">No education yet.</div>';
  eduData.forEach((e, idx)=>{
    const div = document.createElement('div'); div.className='card';
    div.innerHTML = `<div class="left">
      <h4>${escapeHtml(e.education_level_name || 'N/A')}</h4>
      <div class="meta">${escapeHtml(e.school_name || '')} • ${escapeHtml(e.start_date||'')} - ${escapeHtml(e.end_date||'')}</div>
    </div>
    <div class="actions">
      <button class="editEduBtn" onclick="editEdu(${idx})"><i class="fa-regular fa-pen-to-square"></i></button>
      <button class="deleteEduBtn" onclick="deleteEdu(${idx})"><i class="far fa-trash-alt"></i></button>
    </div>`;
    eduCards.appendChild(div);
  });
}
```

### 4. Skills Changes

**HTML: Replace the skills input with Industry/Category/Skills dropdowns**

Replace the entire skills section with:
```html
<div class="section" id="panel-skills" style="display:none;">
  <div style="max-width:920px;">
    <h3 style="margin:0 0 16px 0;font-size:18px;">Skills</h3>
    
    <div class="field">
      <label>Industry *</label>
      <select id="skill_industry">
        <option value="">Select Industry</option>
      </select>
    </div>
    
    <div class="field">
      <label>Category *</label>
      <select id="skill_category" disabled>
        <option value="">Select Category</option>
      </select>
    </div>
    
    <div class="field">
      <label>Skills *</label>
      <select id="skill_list" disabled>
        <option value="">Select Skill</option>
      </select>
    </div>
    
    <button class="btn" onclick="addSelectedSkill()">Add Skill</button>
    
    <div class="tags" id="skillTags" style="margin-top:16px;"></div>
  </div>
</div>
```

**JavaScript for Skills:**

```javascript
// Populate industry dropdown
function populateIndustries() {
  const select = document.getElementById('skill_industry');
  select.innerHTML = '<option value="">Select Industry</option>';
  industries.forEach(ind => {
    const opt = document.createElement('option');
    opt.value = ind.industry_id;
    opt.textContent = ind.industry_name;
    select.appendChild(opt);
  });
}

// Industry change handler
document.getElementById('skill_industry')?.addEventListener('change', async function() {
  const industryId = this.value;
  const categorySelect = document.getElementById('skill_category');
  const skillSelect = document.getElementById('skill_list');
  
  // Reset dependent dropdowns
  categorySelect.innerHTML = '<option value="">Select Category</option>';
  skillSelect.innerHTML = '<option value="">Select Skill</option>';
  skillSelect.disabled = true;
  
  if (!industryId) {
    categorySelect.disabled = true;
    return;
  }
  
  // Load categories
  const res = await fetch(`get_categories.php?industry_id=${industryId}`);
  const data = await res.json();
  
  if (data.success && data.categories.length) {
    data.categories.forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat.job_category_id;
      opt.textContent = cat.job_category_name;
      categorySelect.appendChild(opt);
    });
    categorySelect.disabled = false;
  }
});

// Category change handler
document.getElementById('skill_category')?.addEventListener('change', async function() {
  const categoryId = this.value;
  const skillSelect = document.getElementById('skill_list');
  
  skillSelect.innerHTML = '<option value="">Select Skill</option>';
  
  if (!categoryId) {
    skillSelect.disabled = true;
    return;
  }
  
  // Load skills
  const res = await fetch(`get_skills.php?category_id=${categoryId}`);
  const data = await res.json();
  
  if (data.success && data.skills.length) {
    data.skills.forEach(skill => {
      const opt = document.createElement('option');
      opt.value = skill.skill_id;
      opt.textContent = skill.skill_name;
      skillSelect.appendChild(opt);
    });
    skillSelect.disabled = false;
  }
});

// Add selected skill
async function addSelectedSkill() {
  const industryId = document.getElementById('skill_industry').value;
  const categoryId = document.getElementById('skill_category').value;
  const skillId = document.getElementById('skill_list').value;
  
  if (!skillId) {
    alert('Please select a skill');
    return;
  }
  
  const payload = {
    section: 'skill',
    industry_id: industryId,
    job_category_id: categoryId,
    skill_id: skillId
  };
  
  const res = await fetch('save_resume.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  });
  
  const result = await res.json();
  if (result.success) {
    // Reset dropdowns
    document.getElementById('skill_industry').value = '';
    document.getElementById('skill_category').innerHTML = '<option value="">Select Category</option>';
    document.getElementById('skill_category').disabled = true;
    document.getElementById('skill_list').innerHTML = '<option value="">Select Skill</option>';
    document.getElementById('skill_list').disabled = true;
    
    await loadResumeData();
  } else {
    alert(result.msg || 'Failed to add skill');
  }
}

// Render skills
function renderSkills(){
  const container = document.getElementById('skillTags');
  container.innerHTML = '';
  
  if (!skillsData.length) {
    container.innerHTML = '<div class="small">No skills added yet.</div>';
    return;
  }
  
  skillsData.forEach((s, idx)=>{
    const span = document.createElement('div');
    span.className='tag';
    span.innerHTML = `${escapeHtml(s.skill_name || 'N/A')} <button style="background:transparent;border:none;color:#1b4f9c;font-weight:700;margin-left:6px;cursor:pointer" onclick="removeSkill(${s.applicant_skills_id})">×</button>`;
    container.appendChild(span);
  });
}

// Remove skill
async function removeSkill(skillId) {
  const res = await fetch('delete_resume_item.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({section: 'skill', id: skillId})
  });
  
  const result = await res.json();
  if (result.success) {
    await loadResumeData();
  }
}
```

### 5. Achievements Changes

Update field IDs and save logic:

```javascript
function clearCertForm(){ 
  document.getElementById('c_title').value=''; 
  document.getElementById('c_org').value=''; 
  document.getElementById('c_date').value=''; 
}

if(saveCertBtnEl) saveCertBtnEl.addEventListener('click', async ()=>{
  const title = document.getElementById('c_title').value.trim();
  const org = document.getElementById('c_org').value.trim();
  if(!title || !org){ alert('Title and Organization are required'); return; }
  
  const payload = {
    section: 'achievement',
    achievement_name: title,
    achievement_organization: org,
    date_received: document.getElementById('c_date').value || null
  };
  
  if(editingCertIndex >= 0 && certData[editingCertIndex].achievement_id) {
    payload.achievement_id = certData[editingCertIndex].achievement_id;
  }
  
  const res = await fetch('save_resume.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  });
  
  const result = await res.json();
  if(result.success) {
    cancelCertForm();
    await loadResumeData();
  } else {
    alert(result.msg || 'Save failed');
  }
});

function renderCerts(){
  certCards.innerHTML = '';
  if(!certData.length) certCards.innerHTML = '<div class="small" style="padding:8px 0;">No achievements yet.</div>';
  certData.forEach((c, idx)=>{
    const div = document.createElement('div'); div.className='card';
    div.innerHTML = `<div class="left">
      <h4>${escapeHtml(c.achievement_name || '')}</h4>
      <div class="meta">${escapeHtml(c.achievement_organization || '')} • ${escapeHtml(c.date_received || '')}</div>
    </div>
    <div class="actions">
      <button class="editCertBtn" onclick="editCert(${idx})"><i class="fa-regular fa-pen-to-square"></i></button>
      <button class="deleteCertBtn" onclick="deleteCert(${idx})"><i class="far fa-trash-alt"></i></button>
    </div>`;
    certCards.appendChild(div);
  });
}

async function deleteCert(i){
  if(!confirm('Delete?')) return;
  const ach_id = certData[i].achievement_id;
  
  const res = await fetch('delete_resume_item.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({section: 'achievement', id: ach_id})
  });
  
  const result = await res.json();
  if(result.success) {
    await loadResumeData();
  }
}
```

### 6. Remove autosave and old JSON-based saving

Remove or comment out:
- `doAutoSave()` function
- `triggerAutoSave()` calls
- Any code that saves to `autosave_resume.php`

## Summary

All resume data now saves to proper database tables:
- Work → `applicant_experience`
- Education → `applicant_education`  
- Skills → `applicant_skills`
- Achievements → `applicant_achievements`

All linked via the `resume` table which contains `user_id`.

Company field is now optional for work experience.
Education uses dropdown from `education_level` table.
Skills use cascading dropdowns: Industry → Category → Skills.
No description field in education.
