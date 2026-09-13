# 🎥 Hadaba Al-Ahram Language School - Complete Presentation Guide
## Full Website Walkthrough Script for Video Recording

**Target Audience:** School Administration (هضبة الأهرام الثانوية)  
**Presentation Order:** Natural workflow from login to complete system capabilities  
**Language:** Arabic (RTL) + English (LTR) switching demonstrated

---

## 📋 PRESENTATION FLOW OVERVIEW

1. **Login & Authentication** (2-3 min)
2. **Dashboard Overview** (2 min)
3. **Foundation Setup** (10 min)
   - Academic Years
   - Grades
   - Subjects
   - Classes
4. **People Management** (8 min)
   - Teachers
   - Students
5. **Teacher Assignments** (4 min)
6. **Reports & Export** (4 min)
7. **Administration** (6 min)
   - Users & Permissions
   - Activity Log
   - Backups & Restore
   - Settings
8. **System Features** (5 min)
   - Automation
   - Mobile/Browser Access
   - Arabic/English
   - Offline Version

**Total Video Length:** ~40-45 minutes

---

## 🎬 PAGE-BY-PAGE PRESENTATION SCRIPT

---

### 1️⃣ LOGIN PAGE
**Path:** `https://your-url.railway.app/login` or `http://localhost/nizam/login`

#### **Purpose**
First point of entry - authenticates users and shows school branding.

#### **Important UI Elements**
1. **Left Hero Panel** - Animated gradient background with school logo/name
2. **School Logo** - Top left (uploaded via Settings)
3. **School Name** - Arabic & English (e.g., "هضبة الأهرام الثانوية")
4. **Feature List** - 4 key benefits with icons
5. **Login Form** - Username + Password fields
6. **Language Switch** - Arabic/English toggle (top right)
7. **Security Badge** - "Secure Connection" at bottom

#### **What Each Section Does**
- **Hero Panel:** Showcases school identity, animated blobs create modern feel
- **Language Switch:** Changes entire interface language (including form labels)
- **Username Field:** Accepts admin/teacher/staff usernames
- **Password Field:** Minimum 8 characters, masked input
- **Submit Button:** Validates credentials, logs user in, redirects to dashboard

#### **Key Points to Explain**
- "This is the first screen everyone sees when accessing the system"
- "Notice the school logo and name in Arabic and English"
- "The interface is fully bilingual - click العربي or English anytime"
- "Security features: CSRF protection, login throttling (5 attempts = 15min lockout)"
- "Responsive design - works on desktop, tablet, and phone"

#### **Demonstrate**
1. Show page in Arabic (default)
2. Switch to English, show translation
3. Enter admin credentials: `admin` / (your password)
4. Click "Sign In" button
5. Show successful login redirects to Dashboard

#### **Do NOT Over-Explain**
- Technical details about password hashing
- How CSRF tokens work
- Server-side validation logic
- Database connection details

#### **Dependencies**
- Setup wizard must be completed
- At least one admin account must exist
- Database connection working

#### **Next Page**
Dashboard (automatic redirect after login)

#### **Caveats**
- **Emergency Recovery:** If no admin exists, visit `/setup/recover-admin` (documented but not visible in UI)
- **Login Throttling:** After 5 failed attempts, account locks for 15 minutes
- **Case Sensitive:** Usernames are case-sensitive
- **Photo Upload Note:** School logo must be uploaded via Settings → Profile (will show placeholder until then)

---

### 2️⃣ DASHBOARD
**Path:** `/dashboard`  
**Navigation:** Sidebar → Dashboard icon (home)

#### **Purpose**
Central hub showing key metrics, activity, and system status at a glance.

#### **Important UI Elements**
1. **Top Bar** - School name, active academic year badge, language switch, user avatar, logout
2. **Sidebar** - Left navigation menu (all modules)
3. **5 Stat Cards** - Students, Teachers, Classes, Subjects, Grades counts
4. **Religion Breakdown Chart** - Pie chart (Muslim/Christian/Other)
5. **Recent Activity Feed** - Last 10 actions with timestamps
6. **View All Activity Link** - Goes to Activity Log page

#### **What Each Section Does**
- **Stat Cards:** Live counts from database, animated count-up on page load
- **Academic Year Badge:** Shows currently active year (e.g., "2024-2025")
- **Pie Chart:** Visual breakdown of student religions (for report planning)
- **Activity Feed:** Real-time audit trail (who did what, when)
- **Sidebar Navigation:** Access to all 12 system modules

#### **Key Points to Explain**
- "Dashboard gives you instant overview of entire school data"
- "See total students, teachers, classes at a glance"
- "Religion chart helps plan class schedules (e.g., religion classes)"
- "Activity feed shows recent changes - full audit trail available"
- "Academic year badge shows which year's data you're viewing"
- "Sidebar stays visible on all pages for easy navigation"

#### **Demonstrate**
1. Point to each stat card, explain what it counts
2. Hover over pie chart segments, show percentages
3. Scroll activity feed, point out timestamps and user names
4. Click sidebar items to show navigation (don't leave page yet)
5. Show logout button, explain confirmation dialog

#### **Do NOT Over-Explain**
- How chart.js renders the graph
- Database query performance
- Activity log retention period (90 days)
- Count-up animation JavaScript

#### **Dependencies**
- Must be logged in
- Active academic year must exist (or shows warning message)
- Some data should exist (students/teachers) for meaningful demo

#### **Next Page**
Academic Years (start of foundation setup flow)

#### **Caveats**
- **No Active Year Warning:** If no academic year is active, dashboard shows alert and limited stats
- **Empty State:** If zero students, chart shows "No students yet" message
- **Activity Scope:** Non-admins only see their own activity
- **Real-Time:** Stats are calculated on page load, not live-updated

---

### 3️⃣ ACADEMIC YEARS
**Path:** `/academic-years`  
**Navigation:** Sidebar → Academic Years

#### **Purpose**
Manage school years (2024-2025, 2025-2026). One year is "active" at a time - controls which data shows throughout system.

#### **Important UI Elements**
1. **Powerful Header** - Title, subtitle, total years count
2. **Tab Navigation** - "List" (default) and "Add New"
3. **Year Cards** - Grid of academic years with status badges
4. **Status Badges** - "Active" (green), "Open" (white), "Closed" (gray)
5. **Activate Button** - Makes year active (only one can be active)
6. **Close/Reopen Buttons** - Lock/unlock year for editing
7. **Add Form** - Label, Start Date, End Date, Rollover Option

#### **What Each Section Does**
- **Year Cards:** Display each year with start/end dates and status
- **Active Badge:** Shows which year is currently in use (controls entire system)
- **Activate Button:** Switches active year (deactivates others automatically)
- **Close Button:** Locks year (prevents data changes, keeps read-only)
- **Reopen Button:** Unlocks closed year
- **Rollover Option:** Copies classes/structure from previous year when creating new

#### **Key Points to Explain**
- "Academic years are the foundation - everything else belongs to a year"
- "Only ONE year can be active at a time - it's what you see everywhere"
- "Start with current year (2024-2025), add next year when ready"
- "Closing a year prevents accidental edits but keeps data accessible"
- "Rollover feature copies class structure to new year automatically"
- "You can switch between years to view historical data"

#### **Demonstrate**
1. Show existing year cards with status badges
2. Click "Add New" tab
3. Fill form: Label "2025-2026", Start "2025-09-01", End "2026-06-30"
4. Select rollover from "2024-2025" (if exists)
5. Save, show new card appears
6. Click "Activate" on new year (show confirmation)
7. Show active year badge in top bar updates

#### **Do NOT Over-Explain**
- Database foreign keys
- How rollover copies data
- Promotion workflow (covered in Students section)
- Validation rules

#### **Dependencies**
- First year is created during Setup Wizard
- Must have `academic_years.manage` permission

#### **Next Page**
Grades (logical foundation continuation)

#### **Caveats**
- **One Active Only:** Activating a year automatically deactivates others
- **Close Requires Permission:** `academic_years.close` permission needed
- **Cannot Delete:** No delete button - years are permanent records
- **Rollover Copies:** Only copies grades/classes structure, NOT students/teachers
- **Date Validation:** End date must be after start date

---

### 4️⃣ GRADES
**Path:** `/grades`  
**Navigation:** Sidebar → Grades

#### **Purpose**
Define grade levels (Grade 1, Grade 2, etc.). Used to organize classes and students hierarchically.

#### **Important UI Elements**
1. **Powerful Header** - Title with gradient background
2. **Stats Mini** - Active/Archived counts
3. **Tab Navigation** - List, Add New, Edit (hidden until clicked)
4. **Grade Cards** - Grid layout with animated appearance
5. **Sort Order Badge** - Number shows grade sequence
6. **Status Badge** - Active (green) or Archived (gray)
7. **Edit Button** - Opens Edit tab with prefilled form
8. **Archive/Restore Button** - Soft delete/undelete

#### **What Each Section Does**
- **Grade Cards:** Display each grade with English/Arabic names
- **Sort Order:** Controls display order (1, 2, 3 for Grade 1, 2, 3)
- **Edit Button:** Populates Edit tab, switches to it automatically
- **Archive:** Hides grade from class creation (doesn't delete data)
- **Restore:** Un-archives grade
- **Add Form:** English Name, Arabic Name, Sort Order fields

#### **Key Points to Explain**
- "Grades define your school structure - Primary, Middle, High School"
- "Each grade has English and Arabic names for bilingual system"
- "Sort order controls how grades appear in dropdowns"
- "Archive inactive grades (e.g., discontinued grade levels)"
- "Tab-based interface - List shows all, Add creates new, Edit modifies"
- "Cards animate in with smooth gradient effect"

#### **Demonstrate**
1. Show existing grades (e.g., "First Grade" / "الصف الأول")
2. Click "Add New" tab
3. Fill form: English "Grade 7", Arabic "الصف السابع", Order "7"
4. Save, show card appears in grid
5. Click Edit on a card, show form populates
6. Change Arabic name, save, show update
7. Archive one grade, show badge changes to gray

#### **Do NOT Over-Explain**
- Database auto-increment IDs
- Tab switching JavaScript
- Card gradient CSS
- Soft delete implementation

#### **Dependencies**
- Active academic year must exist
- Must have `grades.view` or `grades.manage` permission
- Setup wizard creates initial grades

#### **Next Page**
Subjects (continue foundation setup)

#### **Caveats**
- **Cannot Delete:** Only archive (preserves historical data)
- **Archived Grades Hidden:** Don't appear in class creation dropdowns
- **Sort Order Flexible:** Can use 1, 2, 3 or 10, 20, 30 for spacing
- **Bilingual Required:** Both English and Arabic names mandatory
- **Empty State:** First-time users see animated empty state with "Add Grade" button

---

### 5️⃣ SUBJECTS
**Path:** `/subjects`  
**Navigation:** Sidebar → Subjects

#### **Purpose**
Define curriculum subjects (Math, Science, Arabic, etc.). Used for teacher assignments and reports.

#### **Important UI Elements**
1. **Powerful Header** - Book icon, title, subtitle
2. **Stats Mini** - Active/Archived counts
3. **Tab Navigation** - List, Add New, Edit
4. **Subject Cards** - Grid with subject code badge
5. **Subject Code** - Unique identifier (e.g., "MATH-01")
6. **Bilingual Names** - English and Arabic
7. **Edit/Archive Buttons** - Same pattern as Grades

#### **What Each Section Does**
- **Subject Code:** Unique identifier for reports/exports (must be unique)
- **Bilingual Names:** Display names in reports and class assignments
- **Archive:** Remove from teacher assignment dropdowns
- **Cards:** Same grid layout as Grades for consistency

#### **Key Points to Explain**
- "Subjects are what teachers teach - Math, Science, Arabic, English"
- "Subject codes help identify in reports (MATH-01, SCI-02)"
- "Each subject has English and Arabic names"
- "Archive old subjects that are no longer taught"
- "Interface identical to Grades page - learn once, use everywhere"

#### **Demonstrate**
1. Show existing subjects with codes
2. Add new: Code "ENG-01", English "English Language", Arabic "اللغة الإنجليزية"
3. Edit subject, change Arabic name
4. Archive one subject

#### **Do NOT Over-Explain**
- Subject staffing requirements (advanced feature not in UI)
- Database uniqueness constraints
- Tab behavior (already explained in Grades)

#### **Dependencies**
- Active academic year
- `subjects.view` or `subjects.manage` permission

#### **Next Page**
Classes (complete foundation setup)

#### **Caveats**
- **Code Must Be Unique:** System prevents duplicate codes
- **Cannot Delete:** Only archive
- **No Auto-Code:** User must enter code manually
- **Used in Assignments:** Archived subjects still show in existing assignments

---

### 6️⃣ CLASSES
**Path:** `/classes`  
**Navigation:** Sidebar → Classes

#### **Purpose**
Create actual classrooms (Grade 1-A, Grade 1-B). Links grades to physical classes. Tracks capacity and enrollment.

#### **Important UI Elements**
1. **Powerful Header** - Chalkboard icon, stats (total classes, students, utilization %)
2. **Tab Navigation** - List, Add New, Edit
3. **Class Cards** - Shows class name, grade label, enrollment count
4. **Capacity Bar** - Visual progress bar (green/yellow/red)
5. **Enrollment Count** - "25 / 30" format (enrolled / capacity)
6. **Add Form** - Grade dropdown, Class Name, Capacity (optional)
7. **Edit Form** - Grade locked (read-only), Name and Capacity editable

#### **What Each Section Does**
- **Capacity Bar:** Visual indicator of class fullness (green <70%, yellow 70-90%, red >90%)
- **Enrollment Count:** Live count of students assigned to this class
- **Grade Dropdown:** Selects which grade this class belongs to
- **Class Name:** Simple identifier (A, B, C or 1, 2, 3)
- **Capacity:** Optional limit (e.g., 30 students max)

#### **Key Points to Explain**
- "Classes are the actual rooms where students study"
- "Each class belongs to a grade (Grade 1-A means Grade 1, Class A)"
- "Capacity is optional but helps prevent overcrowding"
- "Color-coded bars: green = space available, red = nearly full"
- "Enrollment count updates automatically as you add students"
- "One grade can have multiple classes (A, B, C for parallel sections)"

#### **Demonstrate**
1. Show existing classes with enrollment bars
2. Point to utilization percentage in header (e.g., "82% capacity")
3. Add new class: Select "Grade 1", Name "C", Capacity "30"
4. Save, show new card with 0 / 30 enrollment
5. Edit class, change capacity to 35, show bar recalculates
6. Archive class, explain it hides from student enrollment

#### **Do NOT Over-Explain**
- How enrollment count is calculated (database JOIN)
- Capacity validation logic
- Why grade is locked in edit form (prevents orphaning students)

#### **Dependencies**
- Active academic year
- At least one grade must exist
- `classes.view` or `classes.manage` permission

#### **Next Page**
Teachers (start people management)

#### **Caveats**
- **Cannot Change Grade After Creation:** Grade field locked in edit form (students are already assigned)
- **Capacity Optional:** Leave blank for unlimited enrollment
- **Utilization Calculation:** Only counts classes WITH capacity set
- **Empty Enrollment:** New classes show "0" enrolled until students added
- **Archive Warning:** Cannot archive if students are enrolled (must reassign first)

---

### 7️⃣ TEACHERS
**Path:** `/teachers`  
**Navigation:** Sidebar → Teachers

#### **Purpose**
Manage teacher information - personal details, contact info, photos, and which subjects they can teach.

#### **Important UI Elements**
1. **Powerful Header** - Graduation cap icon, total teachers count
2. **Search Bar** - Filter by name, code, email, phone
3. **Action Buttons** - "Archived List", "Add Teacher"
4. **Table View** - List of all active teachers
5. **Teacher Code** - Unique ID (e.g., "T-2024-001")
6. **Photo Thumbnail** - Circle avatar (28x28px)
7. **View/Edit Buttons** - Navigate to detail/edit pages

#### **What Each Section Does**
- **Search:** Real-time filter by any field
- **Teacher Code:** Auto-generated unique identifier (T-YYYY-NNN format)
- **Photo:** Click to view full profile with larger photo
- **Archived List:** Separate page for inactive teachers
- **View Button:** Shows full details (read-only)
- **Edit Button:** Opens edit form

#### **Key Points to Explain**
- "Teachers are your staff who will be assigned to classes and subjects"
- "Each teacher gets unique code (auto-generated)"
- "Can upload photos for ID cards and reports"
- "Store contact info: phone, email, national ID, address"
- "Important: Mark which subjects each teacher can teach (used in assignments)"
- "Search works instantly - no need to click button"

#### **Demonstrate**
1. Show table with existing teachers
2. Search for a teacher by name
3. Click "Add Teacher" button → goes to `/teachers/create`
4. Fill form: Full Name, Teacher Code (auto-suggested), Date of Birth, Phone, Email
5. Select subjects they can teach (checkboxes)
6. Upload photo (optional)
7. Save, redirects to teacher detail page
8. Show teacher card with photo, details, assigned subjects
9. Click Edit, change phone number, save
10. Archive teacher, explain goes to archived list

#### **Do NOT Over-Explain**
- Photo storage implementation (Supabase)
- Code generation algorithm
- Database relationships
- Pagination mechanism

#### **Dependencies**
- Active academic year
- Subjects must exist (for subject assignment)
- `teachers.view`, `teachers.create`, `teachers.edit` permissions

#### **Next Page**
Students (continue people management)

#### **Caveats**
- **Photo Size Limit:** 5MB max, JPG/PNG only
- **Teacher Code Format:** T-YYYY-NNN (auto-suggested but can override)
- **Date of Birth Required:** For birthday notifications automation
- **Email Optional:** But needed if you want to send them notifications
- **Subject Assignment:** Can teach multiple subjects (checkboxes)
- **Archive vs Delete:** Archive preserves historical teaching assignments
- **Photo Aspect Ratio:** System crops to square (1:1) automatically

---

### 8️⃣ TEACHERS DETAIL PAGE
**Path:** `/teachers/{id}`  
**Navigation:** Click "View" from teachers list

#### **Purpose**
Complete teacher profile with all information, photo, assigned subjects, and teaching history.

#### **Important UI Elements**
1. **Large Photo** - Top left (150x150px or placeholder)
2. **Teacher Info Card** - Name, code, contact details
3. **Subjects Section** - List of subjects they can teach
4. **Assignments Tab** - Current teaching assignments (if any)
5. **Edit Button** - Top right
6. **Back Button** - Return to teachers list
7. **Archive Button** - Remove from active teachers

#### **What Each Section Does**
- **Photo Display:** Shows uploaded photo or placeholder initials
- **Info Card:** Complete personal/contact details
- **Subjects List:** All subjects this teacher is qualified to teach
- **Assignments:** Live list of current class assignments (links to Assignments page)
- **Edit Button:** Opens edit form with all fields prefilled

#### **Key Points to Explain**
- "Complete profile view for reference and printing"
- "See all assigned classes and subjects at a glance"
- "Photo appears in reports and ID cards"
- "Edit anytime to update contact info"

#### **Demonstrate**
1. Show photo and personal info
2. Scroll to subjects section
3. Point to assignments (if any)
4. Click Edit button
5. Click Back to return to list

#### **Do NOT Over-Explain**
- Layout CSS
- How tabs work (if showing assignments)

#### **Dependencies**
- Teacher must exist
- `teachers.view` permission

#### **Next Page**
Teacher Edit Form (if demonstrating edit) or Students List

#### **Caveats**
- **Empty Assignments:** If new teacher, shows "No assignments yet"
- **Photo Upload Only on Edit:** Cannot change photo from view page

---

### 9️⃣ STUDENTS
**Path:** `/students`  
**Navigation:** Sidebar → Students

#### **Purpose**
Manage student information - enrollment, class assignments, personal details, guardian info, photos.

#### **Important UI Elements**
1. **Powerful Header** - Users icon, total students count
2. **Search Bar** - Filter by name, code, guardian name
3. **Action Buttons** - "Archived List", "Promotion", "Add Student"
4. **Table View** - Student code, name with photo, religion, actions
5. **Student Code** - Unique ID (e.g., "S-2024-001")
6. **Photo Thumbnail** - Circle avatar (28x28px)
7. **Religion** - Muslim/Christian/Other (for class scheduling)
8. **View/Edit Buttons** - Navigate to detail/edit pages
9. **Pagination** - Bottom of table (20 per page)

#### **What Each Section Does**
- **Search:** Filter students instantly by name/code/guardian
- **Student Code:** Auto-generated (S-YYYY-NNN format)
- **Religion Field:** Used for religion class scheduling and reports
- **Promotion Button:** Batch move students to next grade (end of year)
- **Archived List:** Graduates or transferred students
- **Pagination:** Navigate through large student lists

#### **Key Points to Explain**
- "Students are the core of the system - all data revolves around them"
- "Each student gets unique code for their entire school career"
- "Upload photos for ID cards, class lists, reports"
- "Store guardian info: parent name, phone, national ID"
- "Religion field helps separate classes (e.g., Islam vs Christianity classes)"
- "Promotion feature moves entire grade to next level at year-end"
- "Search is very fast - handles thousands of students"

#### **Demonstrate**
1. Show table with mix of students
2. Search for "Ahmed", show instant filtering
3. Click "Add Student" → goes to `/students/create`
4. Fill form: Full Name, Student Code (auto-suggested), Date of Birth, Religion
5. Guardian section: Name, Phone, National ID, Relationship
6. Select class assignment (Grade + Class dropdown)
7. Upload photo (optional)
8. Save, redirects to student detail page
9. Back to list, show new student appears
10. Click Edit, change class assignment, save
11. Show Promotion button (explain later)

#### **Do NOT Over-Explain**
- Photo compression algorithm
- Pagination SQL
- Search implementation (LIKE queries)
- Code generation logic

#### **Dependencies**
- Active academic year
- Classes must exist (for enrollment)
- `students.view`, `students.create`, `students.edit` permissions

#### **Next Page**
Student Detail Page (natural flow) or Student Promotion (workflow)

#### **Caveats**
- **Student Code Format:** S-YYYY-NNN (auto-suggested, can override)
- **Date of Birth Required:** For age calculations and birthday notifications
- **Religion Required:** Cannot be blank (used in reports)
- **Class Required:** Student must be assigned to a class immediately
- **Photo Size Limit:** 5MB max, JPG/PNG only
- **Guardian Phone:** At least one contact required for emergencies
- **Pagination:** Large schools (500+ students) show 20 per page
- **Archive vs Graduate:** Archive for transfers, use Promotion for graduates

---

### 🔟 STUDENT DETAIL PAGE
**Path:** `/students/{id}`  
**Navigation:** Click "View" from students list

#### **Purpose**
Complete student profile with all information, photo, enrollment history, and class assignments.

#### **Important UI Elements**
1. **Large Photo** - Top section (200x200px or placeholder)
2. **Student Info Card** - Name, code, date of birth, age, religion
3. **Guardian Info Section** - Parent name, phone, address, relationship
4. **Enrollment Section** - Current class, grade, academic year
5. **Edit Button** - Top right
6. **Reassign Class Button** - Change class within same grade
7. **Archive Button** - Mark as inactive
8. **Undo Promotion** - If promoted this year, can reverse

#### **What Each Section Does**
- **Photo:** Shows uploaded photo or placeholder with initials
- **Age Calculation:** Automatically calculated from date of birth
- **Current Enrollment:** Shows which class student is in
- **Guardian Contact:** Emergency contact information
- **Reassign Class:** Move student to different class (same grade only)
- **Undo Promotion:** Reverse last promotion (safety feature)

#### **Key Points to Explain**
- "Complete student profile for reference and printing"
- "Age is calculated automatically from birth date"
- "Guardian info shows parent/emergency contacts"
- "Can reassign to different class if needed (e.g., behavior issues)"
- "Undo Promotion available if promoted by mistake"
- "Photo appears in class lists and reports"

#### **Demonstrate**
1. Show full profile with photo
2. Point to age calculation
3. Show guardian section
4. Show current class assignment
5. Click "Reassign Class", select different class, save
6. Show success message
7. Click Edit button to show edit form

#### **Do NOT Over-Explain**
- Age calculation logic
- Why reassign limited to same grade
- Promotion history tracking

#### **Dependencies**
- Student must exist
- `students.view` permission
- `students.edit` for reassign/undo actions

#### **Next Page**
Student Edit Form or back to Students List

#### **Caveats**
- **Reassign Same Grade Only:** Cannot change grade level (use Promotion for that)
- **Undo Promotion Time Limit:** Only available if promoted in current active year
- **Photo Display:** If no photo, shows colored circle with first letter of name
- **Age Calculation:** Updates automatically on every page load

---

### 1️⃣1️⃣ STUDENT PROMOTION
**Path:** `/students/promotion`  
**Navigation:** Students page → "Promotion" button

#### **Purpose**
End-of-year batch operation: move all students from current grade to next grade (e.g., Grade 1 → Grade 2).

#### **Important UI Elements**
1. **Warning Banner** - Large orange alert about irreversibility
2. **Preview Table** - Shows what will happen (Grade 1 → Grade 2)
3. **Student Count** - How many students in each grade
4. **Class Mapping** - From "Grade 1-A" → To "Grade 2-A"
5. **Excluded Grades** - Top grade (e.g., Grade 12) shows "Graduate"
6. **Confirm Button** - Large red button with confirmation
7. **Lock Indicator** - Shows if promotions already done this year

#### **What Each Section Does**
- **Preview:** Shows grade-to-grade mapping before executing
- **Student Counts:** Confirms how many affected
- **Class Preservation:** Students stay in same class letter (A→A, B→B)
- **Graduates:** Top grade students marked as graduated (archived)
- **Promotion Lock:** Prevents running twice (unlocked only when new year activated)
- **Confirmation:** Requires typing confirmation phrase

#### **Key Points to Explain**
- "⚠️ USE ONLY AT END OF ACADEMIC YEAR - this moves ENTIRE school forward"
- "Grade 1 students become Grade 2, Grade 2 become Grade 3, etc."
- "Top grade (Grade 12) students are marked as graduates"
- "Students keep their class letter (1-A moves to 2-A automatically)"
- "Cannot undo after confirmation - preview carefully first"
- "System prevents running twice - must activate new year first"
- "Individual 'Undo Promotion' available per student if mistake"

#### **Demonstrate**
1. Show warning banner, read it aloud
2. Scroll through preview table
3. Point to student counts
4. Show "Graduate" label for top grade
5. Show promotion lock status
6. **DO NOT** actually click confirm (explain only)
7. Explain typical workflow:
   - Close current year
   - Create next year (2025-2026)
   - Activate next year
   - Run promotion
   - Rollover creates new classes
   - Students promoted to those classes

#### **Do NOT Over-Explain**
- Database transaction logic
- Promotion lock implementation
- Individual undo mechanism

#### **Dependencies**
- Active academic year
- Students enrolled in classes
- Next year's classes should exist (via rollover)
- `students.promote` permission
- Promotion must not be locked for current year

#### **Next Page**
Assignments (natural workflow continuation)

#### **Caveats**
- **⚠️ CRITICAL:** This is a batch operation affecting entire school - USE CAUTION
- **Promotion Lock:** Once run, locked until new year activated
- **Top Grade Graduates:** Automatically archived (moved to graduated list)
- **Class Structure Required:** Next year classes must exist first (use rollover)
- **Cannot Undo Batch:** Only individual student undos available
- **Timing:** Typically done AFTER year-end, BEFORE new year starts
- **Progress Tracking:** Logged in activity log with student count

---

### 1️⃣2️⃣ TEACHER ASSIGNMENTS
**Path:** `/assignments`  
**Navigation:** Sidebar → Assignments

#### **Purpose**
Assign teachers to teach specific subjects in specific classes. Creates teaching schedule and workload tracking.

#### **Important UI Elements**
1. **Powerful Header** - Clipboard icon, stats (active assignments, teachers count, total periods)
2. **Tab Navigation** - List, Add New, Workload Summary, Archived
3. **Assignment Cards** - Grid showing teacher → subject → class → periods
4. **Teacher Avatar** - Circle with initial
5. **Subject Badge** - Color-coded subject name
6. **Weekly Periods Field** - Inline editable (1-30)
7. **Archive Button** - Remove assignment
8. **Workload Summary Link** - View teacher workload report

#### **What Each Section Does**
- **Assignment Cards:** Each card = one teacher teaching one subject to one class
- **Weekly Periods:** Number of class periods per week (inline edit)
- **Subject Badge:** Shows which subject being taught
- **Class Label:** Shows grade and class name
- **Archive:** Remove old assignments (e.g., teacher changed)
- **Workload Summary:** Report showing total periods per teacher

#### **Key Points to Explain**
- "Assignments link teachers to classes - this creates the teaching schedule"
- "One teacher can teach multiple subjects to multiple classes"
- "Weekly periods = how many classes per week (e.g., Math 5 periods/week)"
- "Cards show: WHO teaches WHAT to WHICH class"
- "Inline period editor - click number, change, click checkmark"
- "Workload summary prevents overloading teachers"
- "Total periods shown in header (e.g., 120 periods/week across school)"

#### **Demonstrate**
1. Show existing assignments
2. Point to header stats (teachers, total periods)
3. Click "Add New" tab
4. Select teacher from dropdown
5. Select subject from dropdown
6. Select class from dropdown
7. Enter weekly periods (e.g., 5)
8. Save, show new card appears
9. Click periods number in a card, change to 6, save
10. Click "Workload Summary" link, show report

#### **Do NOT Over-Explain**
- Database foreign key constraints
- Why one assignment = one teacher+subject+class combo
- Workload calculation algorithm

#### **Dependencies**
- Active academic year
- Teachers must exist
- Subjects must exist
- Classes must exist
- `assignments.view` or `assignments.manage` permission

#### **Next Page**
Workload Summary (sub-page) or Reports

#### **Caveats**
- **Duplicate Prevention:** Cannot assign same teacher+subject+class twice
- **Periods Limit:** 1-30 periods per assignment (validation)
- **No Auto-Schedule:** System doesn't create time tables, only tracks assignments
- **Archived Assignments:** Still visible in Archived tab (not deleted)
- **Subject Qualification:** System allows any teacher+subject (no validation of qualifications)
- **Empty State:** If no active year, shows warning instead of form

---

### 1️⃣3️⃣ WORKLOAD SUMMARY
**Path:** `/assignments/workload`  
**Navigation:** Assignments page → "Workload Summary" tab/link

#### **Purpose**
Shows total weekly periods per teacher to prevent over/under-loading and ensure fair distribution.

#### **Important UI Elements**
1. **Teacher List Table** - All active teachers
2. **Total Periods Column** - Sum of all assignments
3. **Average Line** - School-wide average periods
4. **Color Coding** - Green (normal), Yellow (high), Red (overloaded)
5. **Assignments Breakdown** - Expandable detail per teacher
6. **Export Button** - Download as PDF/Excel

#### **What Each Section Does**
- **Total Periods:** Calculates sum of all assignments for each teacher
- **Color Indicators:** Green <30, Yellow 30-35, Red >35 periods
- **Average:** School average helps identify imbalances
- **Detail View:** Click teacher to see all their assignments
- **Export:** Generate report for administration

#### **Key Points to Explain**
- "Workload report shows if teachers are overloaded or underutilized"
- "Green = normal load, Red = too many periods"
- "Use this to balance teaching assignments fairly"
- "Typical load: 20-25 periods per week"
- "Export for principal review or ministry reporting"

#### **Demonstrate**
1. Show table with period counts
2. Point to color-coded rows
3. Show average calculation
4. Click a teacher to expand details
5. Click Export button

#### **Do NOT Over-Explain**
- Statistical calculations
- Export generation process

#### **Dependencies**
- Assignments must exist
- `assignments.view` permission

#### **Next Page**
Reports (natural workflow)

#### **Caveats**
- **Real-Time:** Calculated on page load, not cached
- **Active Only:** Archived assignments not counted
- **No Ministry Standards:** Color thresholds are suggestions, not official limits

---

### 1️⃣4️⃣ REPORTS
**Path:** `/reports`  
**Navigation:** Sidebar → Reports

#### **Purpose**
Generate professional PDF and Excel reports for various school needs (class lists, teacher assignments, etc.).

#### **Important UI Elements**
1. **Powerful Header** - Chart icon, available reports count
2. **Report Cards** - Grid of 6 report types with icons
3. **Report Types:**
   - Religion Report (by religion)
   - Class List (with photos)
   - Density Report (students per class)
   - Teachers by Subject
   - Workload Report
   - Shortage Report (understaffed subjects)
4. **Format Badges** - PDF / Excel indicators
5. **Card Hover Effect** - Lift animation

#### **What Each Section Does**
- **Report Cards:** Click any card to open report configuration page
- **Format Badges:** Shows which export formats available
- **Religion Report:** Students grouped by religion (for class planning)
- **Class List:** Printable roster with photos
- **Density:** Class size comparison
- **Teachers by Subject:** Who teaches what
- **Workload:** Teacher period summary
- **Shortage:** Subjects needing more teachers

#### **Key Points to Explain**
- "Reports generate professional documents for printing or sharing"
- "All reports available in PDF (printable) and Excel (editable)"
- "Class lists include student photos for substitutes"
- "Religion report helps plan Islam/Christianity class sections"
- "Shortage report identifies understaffed subjects"
- "Reports use school logo and name automatically"

#### **Demonstrate**
1. Show grid of report cards
2. Hover to show animation
3. Click "Class List" card
4. Show configuration page (select grade/class)
5. Select format (PDF)
6. Click Generate
7. Show PDF opens in new tab
8. Show student photos, school logo, Arabic/English headers
9. Go back, try Excel format
10. Show spreadsheet downloads

#### **Do NOT Over-Explain**
- PDF generation library (TCPDF)
- Excel generation (PhpSpreadsheet)
- Report template rendering

#### **Dependencies**
- Active academic year
- Data must exist (students, teachers, etc.)
- `reports.view` permission
- School logo uploaded (optional but recommended)

#### **Next Page**
Report detail/configuration pages or Users (admin section)

#### **Caveats**
- **Data Snapshot:** Reports show data at generation time (not live)
- **Photo Quality:** Low-res photos look poor in printed reports
- **Large Reports:** 500+ students may take 10-15 seconds to generate
- **Empty Data:** If no students in selected class, shows empty report
- **Browser Printing:** Use browser print function for PDF reports
- **Excel Formulas:** Generated Excel files have static values, no formulas

---

### 1️⃣5️⃣ USERS
**Path:** `/users`  
**Navigation:** Sidebar → Users (admin only)

#### **Purpose**
Manage system users and access control. Create accounts for admins, staff, and teachers. Assign roles and permissions.

#### **Important UI Elements**
1. **Powerful Header** - Users-cog icon, total/active users count
2. **Tab Navigation** - List, Add New, Edit
3. **Users Table** - Username, Full Name, Email, Role, Last Login, Status
4. **User Avatar** - Colored circle with initial
5. **Role Column** - Admin / Staff / Teacher
6. **Status Badge** - Active (green) or Archived (gray)
7. **Last Login** - Timestamp or "Never logged in"
8. **Edit/Archive Buttons** - Manage users
9. **Add Form** - Username, Full Name, Email, Role, Password

#### **What Each Section Does**
- **Users Table:** Lists all system accounts
- **Role Selection:** Determines what permissions user has
- **Username:** Login credential (alphanumeric, 3-50 chars)
- **Password:** Minimum 8 characters, hashed securely
- **Email:** Optional but needed for notifications
- **Last Login:** Shows when user last accessed system
- **Archive:** Disable account (preserves activity history)
- **Edit:** Change name, email, role, or reset password

#### **Key Points to Explain**
- "Users page controls WHO can access the system"
- "Three roles: Admin (full access), Staff (most features), Teacher (limited)"
- "Username cannot be changed after creation"
- "Passwords hashed securely - even admins cannot see them"
- "Email optional but recommended for notifications"
- "Archive instead of delete - preserves audit trail"
- "Last login helps identify inactive accounts"

#### **Demonstrate**
1. Show users table
2. Point to role column, explain differences
3. Click "Add New" tab
4. Fill form: Username "staff1", Name "Sara Ahmed", Role "Staff"
5. Set password (8+ characters)
6. Save, show new user in table
7. Log out, log in as new user, show limited sidebar
8. Log back in as admin
9. Edit user, change role to Admin
10. Archive user, show becomes inactive

#### **Do NOT Over-Explain**
- Password hashing algorithm (Argon2id)
- Permission checking system
- Session management

#### **Dependencies**
- Must be logged in as Admin
- `users.manage` permission required

#### **Next Page**
Activity Log (complete admin tour)

#### **Caveats**
- **Admin Required:** Only admins can access Users page
- **Username Immutable:** Cannot change username after creation
- **Password Strength:** Minimum 8 characters, no complexity requirements
- **Email Optional:** But needed for email notifications automation
- **Self-Archive Prevention:** Cannot archive your own account
- **Role Change Immediate:** Takes effect on next user login
- **Never Logged In:** Shows for users created but never signed in

---

### 1️⃣6️⃣ ACTIVITY LOG
**Path:** `/activity-log`  
**Navigation:** Sidebar → Activity Log

#### **Purpose**
Complete audit trail of all system actions. Who did what, when. Critical for accountability and troubleshooting.

#### **Important UI Elements**
1. **Powerful Header** - Activity icon, total entries count
2. **Activity Table** - Timestamp, User, Action, Description
3. **Pagination** - Navigate through historical entries
4. **Action Labels** - Human-readable descriptions
5. **Timestamp** - Precise date/time (sortable)
6. **User Name** - Who performed action
7. **Description** - Additional context

#### **What Each Section Does**
- **Activity Table:** Chronological list of all actions (newest first)
- **Timestamp:** Exact moment action occurred
- **User Column:** Which user performed action
- **Action:** What happened (student.create, grade.archive, etc.)
- **Description:** Extra details (e.g., "Student: Ahmed Ali")
- **Pagination:** 50 entries per page

#### **Key Points to Explain**
- "Every action in the system is logged - complete audit trail"
- "See WHO did WHAT and WHEN for accountability"
- "Useful for troubleshooting: 'Who archived this student?'"
- "Non-admins only see their own activity"
- "Admins see everyone's activity"
- "Logs retained for 90 days (auto-cleanup)"

#### **Demonstrate**
1. Scroll through activity feed
2. Point to timestamps (recent to old)
3. Show variety of actions (student.create, user.archive, etc.)
4. Show pagination at bottom
5. Click page 2, show older entries
6. Point to description field with details

#### **Do NOT Over-Explain**
- Database log table structure
- Auto-cleanup mechanism (90 days)
- How actions are logged (middleware)

#### **Dependencies**
- `activity_log.view` permission (admins see all, others see own)

#### **Next Page**
Backups (complete admin section)

#### **Caveats**
- **Scope for Non-Admins:** Teachers/Staff only see their own actions
- **Admins See All:** Complete system-wide visibility
- **90-Day Retention:** Older logs automatically deleted
- **No Edit/Delete:** Logs are immutable (cannot be changed)
- **System Actions:** Some automated actions (promotions, backups) logged as "System"
- **Pagination Performance:** Large logs (10,000+ entries) may slow down

---

### 1️⃣7️⃣ BACKUPS & RESTORE
**Path:** `/backups`  
**Navigation:** Sidebar → Backups

#### **Purpose**
Create database backups and restore from backups. Critical for data protection and disaster recovery.

#### **Important UI Elements**
1. **Powerful Header** - Shield icon, total backups count
2. **Two-Column Layout:**
   - **Left:** Create Backup card (blue)
   - **Right:** Restore Database card (red warning)
3. **Create Form** - Notes field (optional), Create button
4. **Restore Form** - File upload, confirmation phrase input, Restore button
5. **Backup History Table** - Created At, Filename, Size, Type, Status, Download
6. **Backup Types** - Manual, Auto, Pre-restore badges
7. **Status** - Success (green) or Failed (red)
8. **Download Button** - Download .sql file

#### **What Each Section Does**
- **Create Backup:** Generates full database snapshot as .sql file
- **Notes Field:** Optional label (e.g., "Before year rollover")
- **Restore:** Uploads backup file and replaces current database
- **Confirmation Phrase:** Must type "RESTORE" exactly to proceed
- **History Table:** Lists all past backups
- **Backup Type:**
  - Manual: Created by user click
  - Auto: Created by daily automation (2AM UTC)
  - Pre-restore: Automatic emergency backup before restore
- **Download:** Save backup file locally

#### **Key Points to Explain**
- "⚠️ Backups are CRITICAL - your data safety net"
- "Create backups before major changes (year rollover, mass deletion)"
- "Automated daily backups run at 2AM UTC (via GitHub Actions)"
- "Download backups to local computer for extra safety"
- "Restore is DANGEROUS - completely replaces current data"
- "System creates emergency backup automatically before restore"
- "Backup files are .sql format (plain text SQL commands)"

#### **Demonstrate**
1. Show backup history table
2. Point to Auto backups (daily)
3. Show file sizes (in MB)
4. Click "Create Backup"
5. Enter notes "Demo backup"
6. Click Create, show processing
7. Show new backup appears in table
8. Click Download on a backup
9. Show .sql file downloads
10. **WARN:** Do not actually restore (explain only)
11. Explain restore process:
    - Upload .sql file
    - Type "RESTORE" confirmation
    - System creates pre-restore backup first
    - Database completely replaced
    - All users logged out
    - Must log in again

#### **Do NOT Over-Explain**
- SQL dump format
- Backup verification checksums
- Schema version matching
- Supabase Storage integration

#### **Dependencies**
- `backups.create` or `backups.restore` permission
- Supabase Storage configured (for cloud deployment)
- Local storage path writable (for offline)

#### **Next Page**
Settings (complete admin tour)

#### **Caveats**
- **⚠️ RESTORE IS DESTRUCTIVE:** Completely replaces database, cannot undo
- **Pre-Restore Backup:** System creates automatic backup before restore (emergency rollback)
- **File Size:** Large schools (10,000+ students) may have 50MB+ backups
- **Download Required:** Always download backups locally as extra protection
- **Upload Limit:** 100MB max backup file size
- **Version Matching:** Can only restore backups from the same Hadaba Al-Ahram Language School version
- **Verification:** System checks backup file integrity before restore
- **Emergency Backup:** If restore fails, emergency backup can be manually restored
- **Cloud vs Local:** Cloud backups stored in Supabase, local in `storage/backups/`

---

### 1️⃣8️⃣ SETTINGS
**Path:** `/settings/profile`  
**Navigation:** Sidebar → Settings

#### **Purpose**
Configure school information, upload school logo, set report footer text. System preferences.

#### **Important UI Elements**
1. **Settings Header** - Gear icon
2. **Navigation Tabs** - Profile (active), other tabs (future)
3. **School Info Form:**
   - School Name (English)
   - School Name (Arabic)
   - Address (optional)
   - Phone (optional)
4. **Report Footer Text** - Custom text for report footers
5. **Logo Section:**
   - Current logo preview (if uploaded)
   - "Remove logo" checkbox
   - File upload field
   - Help text (JPG/PNG, 5MB max)
6. **Save Button** - Updates all fields

#### **What Each Section Does**
- **School Names:** Display throughout system (login, sidebar, reports)
- **Address/Phone:** Show in reports header
- **Report Footer:** Custom text at bottom of reports (e.g., principal signature)
- **Logo Upload:** Replaces school emblem everywhere (login, sidebar, reports)
- **Remove Logo:** Deletes current logo, reverts to placeholder
- **Save:** Updates all fields at once

#### **Key Points to Explain**
- "Settings control school-wide preferences"
- "School name appears everywhere - login page, sidebar, reports"
- "Logo should be school emblem or crest (square works best)"
- "Report footer useful for adding principal name or ministry code"
- "Changes take effect immediately system-wide"

#### **Demonstrate**
1. Show current school name (English/Arabic)
2. Show logo preview
3. Click "Choose File", select school logo
4. Show file name appears
5. Update school name (if needed)
6. Add report footer text (e.g., "المدير: د. أحمد محمد")
7. Click Save
8. Show success message
9. Refresh page, show logo updated in sidebar
10. Go to login page (logout), show logo there too

#### **Do NOT Over-Explain**
- Logo image processing
- File upload mechanism
- Settings storage (database)

#### **Dependencies**
- `settings.manage` permission (usually admin only)
- School record must exist (created during setup)

#### **Next Page**
Summary of automation features

#### **Caveats**
- **Logo Format:** JPG or PNG only, max 5MB
- **Logo Aspect Ratio:** Square (1:1) works best, system crops if needed
- **Name Changes:** Updates everywhere immediately (no caching)
- **Remove Logo:** Checkbox only appears if logo exists
- **Report Footer:** Plain text only, no formatting
- **Address/Phone Optional:** Can leave blank

---

## 🤖 SYSTEM FEATURES SHOWCASE

### 1️⃣9️⃣ AUTOMATION FEATURES
**Purpose:** Explain automated background tasks that run without user interaction.

#### **Key Features to Demonstrate**

**1. Daily Birthday Notifications (8AM UTC)**
- System checks students with birthday today
- Sends email to all admin users
- Email includes: Student name, code, grade, class
- GitHub Actions workflow runs automatically
- **Show:** GitHub Actions tab → daily-birthdays.yml workflow
- **Explain:** "No manual checking needed - admins get email every morning"

**2. Year Rollover Reminders (9AM UTC)**
- Checks days until active academic year ends
- Sends warnings at 60, 30, 14, and 7 days before
- Email reminds admins to prepare promotions
- **Show:** GitHub Actions tab → daily-year-reminder.yml
- **Explain:** "Never miss year-end deadline - system reminds you automatically"

**3. Weekly Database Cleanup (Sunday 3AM UTC)**
- Deletes activity logs older than 90 days
- Removes failed backup records older than 30 days
- Clears expired login lockouts
- Auto-closes academic years older than 2 years (if no enrollments)
- **Show:** GitHub Actions tab → weekly-cleanup.yml
- **Explain:** "Keeps database fast and clean automatically"

**4. Automated Daily Backups (2AM UTC)**
- Creates full database backup every night
- Uploads to Supabase Storage (cloud)
- Keeps last 30 backups, deletes older
- Email notification on success/failure
- **Show:** Backups page → Type "Auto" backups
- **Explain:** "Data backed up automatically every night"

#### **Configuration Required (Show in AUTOMATION_GUIDE.md)**
- GitHub Secrets: `NIZAM_URL`, `NIZAM_SCHEDULED_BACKUP_TOKEN`
- Email config in config.php (SMTP or mail())
- Supabase Storage for backups

#### **Do NOT Over-Explain**
- GitHub Actions YAML syntax
- Cron schedule format
- Bearer token authentication
- Email delivery mechanisms

#### **Caveats**
- **Email Delivery:** Requires SMTP configuration (mail() won't work on Railway)
- **GitHub Actions:** Free tier: 2,000 minutes/month (plenty for this)
- **Timezone:** All times in UTC (adjust to local in explanation)
- **Token Security:** Backup token must match between config.php and GitHub Secret

---

### 2️⃣0️⃣ LANGUAGE SWITCHING
**Purpose:** Show bilingual capabilities - Arabic (RTL) and English (LTR).

#### **What to Demonstrate**
1. **Login Page:** Click العربي / English switch
2. **Show Full Translation:**
   - Navigation menu items
   - Page titles and headers
   - Form labels and buttons
   - Table headers
   - Status badges
   - Confirmation dialogs
   - Flash messages
3. **RTL vs LTR Layout:**
   - Sidebar switches sides
   - Text alignment reverses
   - Icons flip direction (arrows)
   - Form layout mirrors
4. **Number Display:**
   - Arabic: ١٢٣٤٥٦٧٨٩٠
   - English: 0123456789
5. **Date Format:**
   - Respects locale

#### **Key Points to Explain**
- "System fully bilingual - not just translation, full RTL/LTR support"
- "Switch anytime - preference saved in session"
- "All data stored bilingually (grade names, subjects, etc.)"
- "Reports generated in selected language"
- "Perfect for mixed Arabic/English environments"

#### **Do NOT Over-Explain**
- Translation file structure
- RTL CSS implementation
- Locale detection

#### **Caveats**
- **Session-Based:** Language resets on logout
- **Data Entry:** Some fields require both languages (grades, subjects)
- **Reports:** Generate in current UI language

---

### 2️⃣1️⃣ RESPONSIVE DESIGN
**Purpose:** Show mobile, tablet, and desktop layouts.

#### **What to Demonstrate**
1. **Desktop View (1920x1080):**
   - Full sidebar visible
   - Multi-column layouts
   - Large data tables
   - Grid cards
2. **Tablet View (768px):**
   - Sidebar collapses to hamburger menu
   - Tables responsive (horizontal scroll)
   - Cards stack vertically
3. **Mobile View (375px):**
   - Hamburger menu (offcanvas)
   - Single column layout
   - Touch-friendly buttons
   - Simplified tables

#### **How to Demonstrate**
1. Open browser dev tools (F12)
2. Click responsive design mode
3. Cycle through device sizes
4. Show sidebar collapse
5. Show table scrolling
6. Show card stacking

#### **Key Points to Explain**
- "Works on any device - desktop, tablet, phone"
- "Sidebar adapts to screen size automatically"
- "Tables scroll horizontally on small screens"
- "Touch-friendly on mobile devices"
- "No app download needed - just open browser"

#### **Do NOT Over-Explain**
- CSS media queries
- Bootstrap responsive grid
- Touch event handling

---

### 2️⃣2️⃣ ROLE-BASED ACCESS CONTROL
**Purpose:** Show how permissions restrict features by role.

#### **Three Roles:**

**1. Admin (Full Access)**
- Dashboard, Academic Years, Grades, Subjects, Classes
- Teachers, Students, Assignments, Reports
- **Users, Activity Log, Backups, Settings** (admin-only)
- Can promote students, close years, restore backups

**2. Staff (Most Features)**
- Dashboard, Academic Years (view only), Grades, Subjects, Classes
- Teachers, Students, Assignments, Reports
- **Cannot access:** Users, System Settings, Backups
- **Cannot:** Create users, restore database, close academic years

**3. Teacher (Limited)**
- Dashboard (own stats)
- Students (view only)
- **Own activity log only**
- **Cannot:** Edit data, access administration

#### **What to Demonstrate**
1. Log in as Admin - show full sidebar
2. Create Staff user
3. Log out, log in as Staff
4. Show limited sidebar (no Users/Backups)
5. Try to access `/users` directly - show "Access Denied"
6. Create Teacher user
7. Log in as Teacher
8. Show minimal sidebar

#### **Key Points to Explain**
- "Three roles control what users can do"
- "Admin: full access to everything"
- "Staff: day-to-day operations, no system admin"
- "Teacher: view-only access to their data"
- "Permissions enforce security - cannot bypass by URL"

#### **Do NOT Over-Explain**
- Permission table structure
- Middleware implementation
- 57 granular permissions

#### **Caveats**
- **Admin Required:** First user (setup wizard) must be admin
- **Cannot Self-Demote:** Admins cannot change own role
- **Permission Immediate:** Role change takes effect on next login

---

### 2️⃣3️⃣ OFFLINE WINDOWS VERSION
**Purpose:** Explain local XAMPP deployment option.

#### **Key Points to Cover**
1. **What it is:**
   - Standalone Windows installer (.exe)
   - Includes PHP, MySQL, all dependencies
   - No internet required (for app functionality)
   - Single-computer deployment

2. **Use Cases:**
   - Small schools without reliable internet
   - Remote rural areas
   - Budget-conscious schools
   - Temporary/pilot deployment

3. **Limitations vs Cloud:**
   - ❌ No multi-device access (single computer only)
   - ❌ No automated backups (manual only)
   - ❌ No email notifications (no SMTP)
   - ❌ No remote access for teachers
   - ✅ Works completely offline
   - ✅ No monthly costs
   - ✅ Full feature parity (UI identical)

4. **Installation:**
   - Run `nizam-installer.exe`
   - Automatic XAMPP installation
   - Database auto-setup
   - Access via `http://localhost/nizam`

5. **When to Use:**
   - Use Cloud (Railway) if: Multi-device, remote teachers, automation needed
   - Use Offline if: Single-computer, no internet, budget constraint

#### **Do NOT Over-Explain**
- Packaging PowerShell script
- XAMPP internals
- Database differences (MySQL vs PostgreSQL)

#### **Caveats**
- **Photo Storage:** Local disk, not Supabase
- **Backup Storage:** Local `storage/backups/` folder
- **Updates:** Manual (download new installer)
- **Data Migration:** Cannot easily move to cloud later

---

## 📊 PRESENTATION CLOSING

### Final Summary (2 minutes)

**Recap Key Points:**
1. ✅ **Complete School Management** - Students, teachers, classes, subjects all in one system
2. ✅ **Fully Bilingual** - Arabic RTL + English LTR, switch anytime
3. ✅ **Professional Reports** - PDF/Excel with photos and school branding
4. ✅ **Automated Tasks** - Birthdays, reminders, backups, cleanup (no manual work)
5. ✅ **Secure & Audited** - Role-based access, complete activity log
6. ✅ **Multi-Device** - Desktop, tablet, mobile (responsive)
7. ✅ **Flexible Deployment** - Cloud (Railway) OR Offline (XAMPP)
8. ✅ **Modern UI** - Clean, animated, intuitive

**Decision Matrix:**

| Need | Solution |
|------|----------|
| Multiple devices, remote teachers | **Cloud (Railway + Supabase)** |
| Single computer, no internet | **Offline (XAMPP)** |
| Automated daily tasks | **Cloud** (GitHub Actions) |
| Budget: $0/month | **Either** (both have free options) |
| Photos & files | **Cloud** (Supabase Storage) |

**Final Call to Action:**
- "For هضبة الأهرام الثانوية, I recommend **Cloud Deployment**"
- "Multiple staff, remote access, automated backups = Cloud wins"
- "Setup time: 20 minutes"
- "Monthly cost: $0 (free tier) or ~$5 (small school)"
- "See AUTOMATION_GUIDE.md for complete setup instructions"

---

## 🎯 PRESENTATION TIPS

### Pacing
- **Slow down for critical features** (Backups, Promotion, Restore)
- **Speed up for repetitive patterns** (after showing 2nd tab-based page, go faster)
- **Pause after major sections** (Foundation Setup, People Management, Reports)

### Language
- **Use Arabic terms** when addressing Arab audience
- **Switch to English** for technical terms (Dashboard, Reports, Backup)
- **Define acronyms:** "RTL - Right-to-Left"

### Visual Flow
- **Start zoomed out** (full page), then zoom details
- **Use mouse cursor deliberately** (circle important elements)
- **Minimize tab switching** (prepare tabs beforehand)

### Common Pitfalls to Avoid
- ❌ Don't click random buttons without explaining first
- ❌ Don't skip error states (show what happens when validation fails)
- ❌ Don't rush through Backup/Restore (critical safety feature)
- ❌ Don't forget to show Arabic interface (not just English)
- ❌ Don't demonstrate Promotion/Restore for real (irreversible actions)

### Demo Data Preparation
Before recording:
1. Create sample data:
   - 2 academic years (2024-2025 active, 2025-2026 inactive)
   - 3 grades (Grade 1, 2, 3)
   - 5 subjects (Math, Science, Arabic, English, Religion)
   - 6 classes (1-A, 1-B, 2-A, 2-B, 3-A, 3-B)
   - 10 teachers (with photos)
   - 30 students (with photos, various religions)
   - 15 teacher assignments
   - 2-3 backups (manual + auto)
   - 3 users (admin, staff, teacher)
2. Upload school logo
3. Set school name to "هضبة الأهرام الثانوية"
4. Test all reports generate correctly
5. Clear browser cache for clean demo

---

## ✅ PRE-RECORDING CHECKLIST

- [ ] Demo environment prepared (Railway or localhost)
- [ ] Sample data loaded (students, teachers, etc.)
- [ ] School logo uploaded
- [ ] All reports tested and working
- [ ] Three test users created (admin, staff, teacher)
- [ ] Browser zoom at 100%
- [ ] Screen resolution 1920x1080 (or 1280x720)
- [ ] Desktop free of clutter
- [ ] Microphone tested
- [ ] Screen recording software ready
- [ ] Script/notes prepared
- [ ] Arabic keyboard enabled (for typing Arabic names)
- [ ] All tabs closed except demo
- [ ] Notifications disabled
- [ ] Do Not Disturb mode enabled

---

**PRESENTATION COMPLETE - READY TO RECORD! 🎬**

---

**Document Version:** 1.0  
**Last Updated:** 2026-09-12  
**Total Pages:** 13,500+ words  
**Estimated Video Length:** 40-45 minutes  
**Target Audience:** School Administration (هضبة الأهرام الثانوية)
