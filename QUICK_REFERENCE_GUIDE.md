# 📖 Quick Reference Guide - New Features

## 🎯 Para sa Professor

Ang sistema ay may **3 bagong management pages** at **improved conflict detection**!

---

## 1️⃣ MANAGE COURSES (Pag-manage ng Subjects)

### 📍 Location: Admin Menu → **Manage Courses**

### Ano ang pwedeng gawin:
- ✅ **Add Course** - Magdagdag ng bagong subject
- ✅ **Edit Course** - Baguhin ang course code o title
- ✅ **Delete Course** - Tanggalin ang hindi ginagamit
- ✅ **Set Category** - I-categorize (NSTP, IT, GE, etc.)
- ✅ **Set Units** - Itakda ang units (1-6)
- ✅ **Active/Inactive** - I-toggle ang status

### Paano gamitin:
```
1. Click "Manage Courses" sa menu
2. Click "+ Add Course"
3. Fill in:
   - Course Code (e.g., IT 111)
   - Course Title (e.g., Introduction to Computing)
   - Category (NSTP, IT, GE, etc.)
   - Units (default: 3)
   - Status (Active/Inactive)
4. Click "Add Course"
```

### Important:
⚠️ Hindi pwedeng i-delete ang course na ginagamit sa teaching loads!

---

## 2️⃣ MANAGE SECTIONS (Pag-manage ng Sections)

### 📍 Location: Admin Menu → **Manage Sections**

### Ano ang pwedeng gawin:
- ✅ **Add Section** - Magdagdag ng section (1A, 2B, etc.)
- ✅ **Edit Section** - Baguhin ang section info
- ✅ **Delete Section** - Tanggalin ang hindi ginagamit
- ✅ **Set Year Level** - Itakda ang year (1st, 2nd, 3rd, 4th)
- ✅ **Set Program** - I-assign sa program (BSIT, BSCS, etc.)
- ✅ **Max Students** - Itakda ang maximum students

### Paano gamitin:
```
1. Click "Manage Sections" sa menu
2. Click "+ Add Section"
3. Fill in:
   - Section Name (e.g., 1A, 2B)
   - Year Level (1st, 2nd, 3rd, 4th)
   - Program (optional)
   - Max Students (default: 40)
   - Status (Active/Inactive)
4. Click "Add Section"
```

---

## 3️⃣ ENCODE WORKLOAD (Improved!)

### 📍 Location: Admin Menu → **Encode Workload**

### Ano ang bago:

#### **A. DROPDOWN FIELDS** (Hindi na manual typing!)
```
✅ Course Code → Dropdown (from database)
✅ Course Title → Auto-fill (based on course code)
✅ Section → Dropdown (from database)
✅ Room → Dropdown (existing)
✅ Day → Dropdown (MWF, TTH, etc.)
```

#### **B. QUICK-ADD ICONS** (➕)
```
Makikita mo ang ➕ icon sa:
- Course Code field → Opens "Manage Courses"
- Section field → Opens "Manage Sections"
- Room field → Opens "Manage Rooms"

Click lang, mag-open sa bagong tab!
```

#### **C. CONFLICT DETECTION** (Automatic!)
```
Real-time checking:
✅ No conflict → Green, pwedeng i-save
❌ Has conflict → Red border + warning message

Kapag may conflict:
🔴 Red border sa room field
⚠️ Warning message: "Time conflict with..."
📛 Red banner at bottom: "Conflicts detected!"
🚫 Submit button turns RED: "Cannot Save"
```

#### **D. CANNOT SAVE POPUP** (New!)
```
Kapag may conflict at nag-click ng Save:
❌ Form HINDI mag-submit
🚨 Modal popup lalabas showing:
   - List of all conflicts
   - Room name
   - Day and time
   - Conflicting course
   - Faculty who has the room
   - Instructions how to fix

Kailangan i-fix ang conflicts bago maka-save!
```

---

## 🎯 WORKFLOW (Step-by-Step)

### **First Time Setup:**
```
STEP 1: Add Courses
├─ Go to "Manage Courses"
├─ Add all your courses (IT 111, NSTP 11, GE 1, etc.)
└─ Set titles and categories

STEP 2: Add Sections
├─ Go to "Manage Sections"
├─ Add all sections (1A, 1B, 2A, 2B, etc.)
└─ Set year levels

STEP 3: Done! Now ready to encode workloads
```

### **Daily Encoding:**
```
STEP 1: Go to "Encode Workload"

STEP 2: Select Faculty & Semester

STEP 3: Click "Add Subject"

STEP 4: Fill using DROPDOWNS:
├─ Select Course Code → Title auto-fills! ✨
├─ Select Section
├─ Select Room
├─ Select Day
└─ Enter Time

STEP 5: System checks for conflicts
├─ ✅ No conflict? Good to go!
└─ ❌ Has conflict? Fix it first!

STEP 6: Save
├─ ✅ No conflicts? Saves successfully!
└─ ❌ Has conflicts? Modal blocks save!
```

---

## 🚨 CONFLICT HANDLING

### **Paano alam na may conflict:**
```
1. 🔴 RED BORDER sa room dropdown
2. ⚠️ Warning message below:
   "⚠️ Time conflict with IT 111 - Intro to Computing (Prof. Juan Dela Cruz)"
3. 📛 RED BANNER at bottom:
   "Room Schedule Conflicts Detected!"
4. 🚫 Submit button turns RED:
   "Cannot Save - Conflicts Exist"
```

### **Paano i-fix ang conflict:**
```
Option 1: CHANGE ROOM
- Select different room from dropdown

Option 2: CHANGE TIME
- Adjust start time or end time

Option 3: CHANGE DAY
- Select different day (MWF → TTH)

Option 4: ADD NEW ROOM
- Click ➕ icon → Add new room
- Return to workload page
- Select the new room
```

### **Kung nag-try mag-save with conflict:**
```
🚨 MODAL POPUP lalabas showing:

┌─────────────────────────────────────┐
│ ⚠️ Room Schedule Conflict Detected! │
├─────────────────────────────────────┤
│ Cannot save workload!               │
│                                     │
│ Conflicts Found:                    │
│ ┌─────────────────────────────────┐ │
│ │ 🚪 Room: M103                   │ │
│ │ 📅 MWF 10:00 AM - 11:00 AM      │ │
│ │ ⚠️ Conflicts with: IT 111       │ │
│ │ 👤 Faculty: Prof. Juan          │ │
│ └─────────────────────────────────┘ │
│                                     │
│ How to resolve:                     │
│ 1. Change the room                  │
│ 2. Change day or time               │
│ 3. Add new room                     │
│                                     │
│       [Close and Fix Conflicts]     │
└─────────────────────────────────────┘
```

---

## 💡 TIPS & TRICKS

### **For Faster Encoding:**
```
1. ✅ Setup courses and sections FIRST
2. ✅ Use dropdowns - mas mabilis!
3. ✅ Course title auto-fills - no typing!
4. ✅ Check conflicts as you go
5. ✅ Use ➕ icons for quick additions
```

### **For Better Organization:**
```
1. ✅ Group courses by category
2. ✅ Mark old courses as "Inactive"
3. ✅ Use consistent naming (1A, 1B, 1C)
4. ✅ Set realistic max students per section
```

### **For Avoiding Conflicts:**
```
1. ✅ Check room schedule first
2. ✅ Use different time slots
3. ✅ Add more rooms if needed
4. ✅ Fix conflicts immediately
```

---

## 🎨 VISUAL GUIDE

### **No Conflict (OK):**
```
┌─────────────────────┐
│ Room: [M103     ▼] │ ← Normal border
└─────────────────────┘

Submit Button:
┌──────────────────────┐
│ 💾 Save Workload     │ ← GREEN
└──────────────────────┘
```

### **Has Conflict (ERROR):**
```
┌─────────────────────┐
│ Room: [M103     ▼] │ ← RED border
└─────────────────────┘
⚠️ Time conflict with IT 111

┌──────────────────────────────────────────┐
│ ⚠️ Room Schedule Conflicts Detected!     │
│ Resolve conflicts before saving.         │
└──────────────────────────────────────────┘

Submit Button:
┌──────────────────────────────────────┐
│ ❌ Cannot Save - Conflicts Exist     │ ← RED
└──────────────────────────────────────┘
```

---

## 📋 CHECKLIST

### **Before Encoding Workloads:**
- [ ] All courses added in "Manage Courses"
- [ ] All sections added in "Manage Sections"
- [ ] All rooms added in "Manage Rooms"

### **While Encoding:**
- [ ] Course selected from dropdown
- [ ] Section selected from dropdown
- [ ] Room selected from dropdown
- [ ] Day and time filled in
- [ ] No red borders (no conflicts)
- [ ] Green submit button visible

### **Before Saving:**
- [ ] All conflicts resolved
- [ ] No red warnings
- [ ] Submit button is GREEN
- [ ] All required fields filled

---

## 🆘 TROUBLESHOOTING

### **"Course not in dropdown?"**
```
Solution:
1. Click ➕ icon next to Course Code
2. Add the course in "Manage Courses"
3. Return to workload page
4. Reload page (F5)
5. Course should appear now
```

### **"Section not in dropdown?"**
```
Solution:
1. Click ➕ icon next to Section
2. Add the section in "Manage Sections"
3. Return to workload page
4. Reload page (F5)
5. Section should appear now
```

### **"Cannot save - has conflict?"**
```
Solution:
1. Look for RED borders
2. Read conflict message
3. Change room/time/day
4. Wait for green (no red)
5. Try saving again
```

### **"Course title not auto-filling?"**
```
Solution:
1. Check if course exists in "Manage Courses"
2. Make sure course has a title
3. Try selecting "Other" then type manually
```

---

## 📞 NEED HELP?

### **Common Questions:**

**Q: Pwede bang i-edit ang courses later?**  
A: Yes! Go to "Manage Courses" → Click "Edit"

**Q: Paano mag-add ng section while encoding?**  
A: Click ➕ icon → Opens "Manage Sections" in new tab

**Q: Ano gagawin sa conflicts?**  
A: Change room, time, or day until walang conflict

**Q: Pwede bang i-delete ang course?**  
A: Yes, if NOT used in any teaching loads

**Q: Kailangan ba laging dropdown?**  
A: Select "Other" option for manual entry

---

## 🎉 SUMMARY

### **New Features:**
- ✅ **Manage Courses** page
- ✅ **Manage Sections** page
- ✅ **Dropdown selections** everywhere
- ✅ **Auto-fill** course titles
- ✅ **Cannot save** with conflicts
- ✅ **Modal popup** shows conflicts
- ✅ **Quick-add icons** (➕) for fast additions

### **Benefits:**
- 💪 Less manual typing
- 🎯 No typing errors
- 🚫 No room conflicts possible
- ⚡ Faster encoding
- 📊 Better organized data
- 🎨 More professional

---

**Enjoy the new features!** 🎊

Kung may tanong, just ask! 😊

