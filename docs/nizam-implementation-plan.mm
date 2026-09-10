<?xml version="1.0" encoding="UTF-8"?>
<map version="1.0.1">
  <node ID="ID_1" TEXT="Nizam Implementation Plan">
    <node ID="ID_2" TEXT="Requirements">
      <node ID="ID_3" TEXT="12 Functional Pillars" FOLDED="true">
        <node ID="ID_4" TEXT="Students" FOLDED="true"/>
        <node ID="ID_5" TEXT="Teachers" FOLDED="true"/>
        <node ID="ID_6" TEXT="Grades" FOLDED="true"/>
        <node ID="ID_7" TEXT="Classes" FOLDED="true"/>
        <node ID="ID_8" TEXT="Subjects" FOLDED="true"/>
        <node ID="ID_9" TEXT="Teacher Assignments" FOLDED="true"/>
        <node ID="ID_10" TEXT="Weekly Teaching Load" FOLDED="true"/>
        <node ID="ID_11" TEXT="Statistics" FOLDED="true"/>
        <node ID="ID_12" TEXT="Reports" FOLDED="true"/>
        <node ID="ID_13" TEXT="Backup &amp; Restore" FOLDED="true"/>
        <node ID="ID_14" TEXT="Academic-Year Transitions" FOLDED="true"/>
        <node ID="ID_15" TEXT="Settings" FOLDED="true"/>
      </node>
      <node ID="ID_16" TEXT="Non-negotiable constraints" FOLDED="true">
        <node ID="ID_17" TEXT="100% offline after install" FOLDED="true"/>
        <node ID="ID_18" TEXT="XAMPP / Apache / PHP / MySQL-MariaDB" FOLDED="true"/>
        <node ID="ID_19" TEXT="Arabic-first, RTL by default" FOLDED="true"/>
        <node ID="ID_20" TEXT="English secondary, full LTR" FOLDED="true"/>
        <node ID="ID_21" TEXT="Scales to thousands of students" FOLDED="true"/>
        <node ID="ID_22" TEXT="No CDN dependencies, ever" FOLDED="true"/>
        <node ID="ID_23" TEXT="Non-technical end users" FOLDED="true"/>
      </node>
      <node ID="ID_24" TEXT="Scope decisions" FOLDED="true">
        <node ID="ID_25" TEXT="Single school per installation (confirmed)" FOLDED="true"/>
        <node ID="ID_26" TEXT="Bulk import deferred to post-v1 (O-14)" FOLDED="true"/>
        <node ID="ID_27" TEXT="No timetable/scheduling module (O-29)" FOLDED="true"/>
      </node>
    </node>
    <node ID="ID_28" TEXT="Architecture">
      <node ID="ID_29" TEXT="Layered MVC" FOLDED="true">
        <node ID="ID_30" TEXT="Controller - input shape only" FOLDED="true"/>
        <node ID="ID_31" TEXT="Service - business rules, transactions" FOLDED="true"/>
        <node ID="ID_32" TEXT="Repository - all SQL, PDO prepared statements" FOLDED="true"/>
      </node>
      <node ID="ID_33" TEXT="Middleware chain" FOLDED="true">
        <node ID="ID_34" TEXT="Session" FOLDED="true"/>
        <node ID="ID_35" TEXT="CSRF (hash_equals, O-19)" FOLDED="true"/>
        <node ID="ID_36" TEXT="RoleGuard" FOLDED="true"/>
        <node ID="ID_37" TEXT="AcademicYearContext" FOLDED="true"/>
      </node>
      <node ID="ID_38" TEXT="File structure" FOLDED="true">
        <node ID="ID_39" TEXT="app/ (controllers, services, repositories, middleware, helpers)" FOLDED="true"/>
        <node ID="ID_40" TEXT="config/" FOLDED="true"/>
        <node ID="ID_41" TEXT="database/ (migrations, seeds, backups)" FOLDED="true"/>
        <node ID="ID_42" TEXT="public/ - the only web-servable folder" FOLDED="true"/>
        <node ID="ID_43" TEXT="views/ + views/components" FOLDED="true"/>
        <node ID="ID_44" TEXT="reports/" FOLDED="true"/>
        <node ID="ID_45" TEXT="storage/" FOLDED="true"/>
        <node ID="ID_46" TEXT="vendor/ (committed)" FOLDED="true"/>
      </node>
      <node ID="ID_47" TEXT="Web-root lockout" FOLDED="true">
        <node ID="ID_48" TEXT="Root .htaccess deny-all (S-4, BLOCKER)" FOLDED="true"/>
        <node ID="ID_49" TEXT="public/.htaccess re-allows" FOLDED="true"/>
        <node ID="ID_50" TEXT="Uploads folder: PHP execution disabled (O-18)" FOLDED="true"/>
      </node>
    </node>
    <node ID="ID_51" TEXT="Database">
      <node ID="ID_52" TEXT="Core entities (16)" FOLDED="true">
        <node ID="ID_53" TEXT="schools" FOLDED="true"/>
        <node ID="ID_54" TEXT="academic_years" FOLDED="true"/>
        <node ID="ID_55" TEXT="roles / permissions / role_permissions" FOLDED="true"/>
        <node ID="ID_56" TEXT="users" FOLDED="true"/>
        <node ID="ID_57" TEXT="grades" FOLDED="true"/>
        <node ID="ID_58" TEXT="subjects" FOLDED="true"/>
        <node ID="ID_59" TEXT="classes" FOLDED="true"/>
        <node ID="ID_60" TEXT="students" FOLDED="true"/>
        <node ID="ID_61" TEXT="student_enrollments" FOLDED="true"/>
        <node ID="ID_62" TEXT="teachers" FOLDED="true"/>
        <node ID="ID_63" TEXT="teacher_subjects" FOLDED="true"/>
        <node ID="ID_64" TEXT="teacher_assignments" FOLDED="true"/>
        <node ID="ID_65" TEXT="subject_staffing_requirements" FOLDED="true"/>
        <node ID="ID_66" TEXT="settings" FOLDED="true"/>
        <node ID="ID_67" TEXT="activity_logs" FOLDED="true"/>
        <node ID="ID_68" TEXT="backups / login_attempts / migrations" FOLDED="true"/>
      </node>
      <node ID="ID_69" TEXT="Integrity fixes from the Final Gate" FOLDED="true">
        <node ID="ID_70" TEXT="Composite FK: enrollment &lt;-&gt; class/grade/year (S-1, BLOCKER)" FOLDED="true"/>
        <node ID="ID_71" TEXT="Composite FK: assignment &lt;-&gt; class/year (S-2, BLOCKER)" FOLDED="true"/>
        <node ID="ID_72" TEXT="One active year, DB-enforced via unique generated column (O-2)" FOLDED="true"/>
        <node ID="ID_73" TEXT="student_enrollments.status includes &apos;graduated&apos; (O-3)" FOLDED="true"/>
        <node ID="ID_74" TEXT="settings.setting_key not &apos;key&apos; (O-1)" FOLDED="true"/>
        <node ID="ID_75" TEXT="expected_weekly_capacity moved onto academic_years (S-11)" FOLDED="true"/>
      </node>
      <node ID="ID_76" TEXT="utf8mb4 / utf8mb4_unicode_ci everywhere (O-8)" FOLDED="true"/>
      <node ID="ID_77" TEXT="No hard-delete anywhere - archive only (O-10)" FOLDED="true"/>
      <node ID="ID_78" TEXT="Migration order: 8 files, forward-only, no down() (O-30)" FOLDED="true"/>
      <node ID="ID_79" TEXT="Idempotent seeds: roles, permissions, subjects, grades" FOLDED="true"/>
    </node>
    <node ID="ID_80" TEXT="Security">
      <node ID="ID_81" TEXT="Passwords: password_hash, session regen on login" FOLDED="true"/>
      <node ID="ID_82" TEXT="Login throttling: per-user+IP AND IP-wide (O-16)" FOLDED="true"/>
      <node ID="ID_83" TEXT="CSRF via hash_equals (O-19)" FOLDED="true"/>
      <node ID="ID_84" TEXT="Uploads: random filenames, explicit Content-Type, exec lockout (O-18)" FOLDED="true"/>
      <node ID="ID_85" TEXT="File serving resolves by DB id, never client path (S-10)" FOLDED="true"/>
      <node ID="ID_86" TEXT="Setup Wizard permanently locked after completion (O-6)" FOLDED="true"/>
      <node ID="ID_87" TEXT="Backups/uploads never web-reachable (S-4, BLOCKER)" FOLDED="true"/>
      <node ID="ID_88" TEXT="e() escaping everywhere, incl. report templates (S-13)" FOLDED="true"/>
      <node ID="ID_89" TEXT="Residual: no HTTPS by default on LAN (O-31, documented)" FOLDED="true"/>
    </node>
    <node ID="ID_90" TEXT="Modules">
      <node ID="ID_91" TEXT="Auth" FOLDED="true">
        <node ID="ID_92" TEXT="Login screen" FOLDED="true"/>
      </node>
      <node ID="ID_93" TEXT="Dashboard" FOLDED="true">
        <node ID="ID_94" TEXT="Summary cards, charts via GROUP BY (O-24)" FOLDED="true"/>
      </node>
      <node ID="ID_95" TEXT="Students" FOLDED="true">
        <node ID="ID_96" TEXT="List / Add / Edit / View / Archive" FOLDED="true"/>
        <node ID="ID_97" TEXT="Reassign Class (S-6)" FOLDED="true"/>
        <node ID="ID_98" TEXT="Promotion Preview/Confirm" FOLDED="true"/>
      </node>
      <node ID="ID_99" TEXT="Teachers" FOLDED="true">
        <node ID="ID_100" TEXT="List / Add / Edit / Archive" FOLDED="true"/>
      </node>
      <node ID="ID_101" TEXT="Subjects" FOLDED="true">
        <node ID="ID_102" TEXT="List / Add / Edit" FOLDED="true"/>
      </node>
      <node ID="ID_103" TEXT="Grades &amp; Classes" FOLDED="true">
        <node ID="ID_104" TEXT="Grades list, Classes list, Add/Edit class" FOLDED="true"/>
      </node>
      <node ID="ID_105" TEXT="Assignments" FOLDED="true">
        <node ID="ID_106" TEXT="List / Add / Edit, Workload summary" FOLDED="true"/>
      </node>
      <node ID="ID_107" TEXT="Academic Years" FOLDED="true">
        <node ID="ID_108" TEXT="List, Create+Rollover, Activate/Close" FOLDED="true"/>
      </node>
      <node ID="ID_109" TEXT="Reports" FOLDED="true">
        <node ID="ID_110" TEXT="Index + 6 report views + print view" FOLDED="true"/>
      </node>
      <node ID="ID_111" TEXT="Backup &amp; Restore" FOLDED="true">
        <node ID="ID_112" TEXT="Run/Restore screen, History" FOLDED="true"/>
      </node>
      <node ID="ID_113" TEXT="Settings" FOLDED="true">
        <node ID="ID_114" TEXT="5 grouped screens (O-21)" FOLDED="true"/>
      </node>
      <node ID="ID_115" TEXT="Users" FOLDED="true">
        <node ID="ID_116" TEXT="List / Add / Edit (Admin only)" FOLDED="true"/>
      </node>
      <node ID="ID_117" TEXT="Activity Log" FOLDED="true">
        <node ID="ID_118" TEXT="List" FOLDED="true"/>
      </node>
    </node>
    <node ID="ID_119" TEXT="Workflows">
      <node ID="ID_120" TEXT="Promotion (I.1)" FOLDED="true">
        <node ID="ID_121" TEXT="Select source/target year" FOLDED="true"/>
        <node ID="ID_122" TEXT="Propose defaults by grade sort_order" FOLDED="true"/>
        <node ID="ID_123" TEXT="Exceptions: repeat / graduate / transferred / withdrawn (S-5)" FOLDED="true"/>
        <node ID="ID_124" TEXT="Assign target classes" FOLDED="true"/>
        <node ID="ID_125" TEXT="Preview" FOLDED="true"/>
        <node ID="ID_126" TEXT="Promotion lock (O-17)" FOLDED="true"/>
        <node ID="ID_127" TEXT="Confirm -&gt; transaction" FOLDED="true"/>
        <node ID="ID_128" TEXT="Undo single student (O-12, S-7 exception)" FOLDED="true"/>
      </node>
      <node ID="ID_129" TEXT="Year rollover (I.4)" FOLDED="true">
        <node ID="ID_130" TEXT="Clone classes forward" FOLDED="true"/>
        <node ID="ID_131" TEXT="Clone teacher assignments forward" FOLDED="true"/>
      </node>
      <node ID="ID_132" TEXT="Year close (I.4)" FOLDED="true">
        <node ID="ID_133" TEXT="Guard: zero active enrollments required (S-3, BLOCKER)" FOLDED="true"/>
      </node>
      <node ID="ID_134" TEXT="Reassign class (I.9)" FOLDED="true">
        <node ID="ID_135" TEXT="Same grade/year only, enforced by composite FK (S-6)" FOLDED="true"/>
      </node>
      <node ID="ID_136" TEXT="Enrollment immutability (S-7)" FOLDED="true">
        <node ID="ID_137" TEXT="Non-active rows never edited except Undo" FOLDED="true"/>
      </node>
      <node ID="ID_138" TEXT="Weekly workload (I.5)" FOLDED="true">
        <node ID="ID_139" TEXT="Always explicit year (O-23)" FOLDED="true"/>
      </node>
      <node ID="ID_140" TEXT="Staffing shortage (I.6)" FOLDED="true">
        <node ID="ID_141" TEXT="Excludes subjects with no requirement set" FOLDED="true"/>
      </node>
    </node>
    <node ID="ID_142" TEXT="UI">
      <node ID="ID_143" TEXT="Reusable components" FOLDED="true">
        <node ID="ID_144" TEXT="Navbar / Sidebar (role-aware)" FOLDED="true"/>
        <node ID="ID_145" TEXT="Data table" FOLDED="true"/>
        <node ID="ID_146" TEXT="Form" FOLDED="true"/>
        <node ID="ID_147" TEXT="Modal (confirm-destructive only)" FOLDED="true"/>
        <node ID="ID_148" TEXT="Alert / toast" FOLDED="true"/>
        <node ID="ID_149" TEXT="Pagination (capped page size, O-25)" FOLDED="true"/>
        <node ID="ID_150" TEXT="Filter bar" FOLDED="true"/>
        <node ID="ID_151" TEXT="Empty state" FOLDED="true"/>
      </node>
      <node ID="ID_152" TEXT="RTL is re-ordered, not mirrored" FOLDED="true">
        <node ID="ID_153" TEXT="Column order flips, not just text-align" FOLDED="true"/>
        <node ID="ID_154" TEXT="Western numerals kept" FOLDED="true"/>
        <node ID="ID_155" TEXT="Consistent Gregorian dates" FOLDED="true"/>
      </node>
    </node>
    <node ID="ID_156" TEXT="Reports">
      <node ID="ID_157" TEXT="Religion statistics" FOLDED="true"/>
      <node ID="ID_158" TEXT="Class student list" FOLDED="true"/>
      <node ID="ID_159" TEXT="Class density" FOLDED="true"/>
      <node ID="ID_160" TEXT="Teachers by subject" FOLDED="true"/>
      <node ID="ID_161" TEXT="Teacher workload" FOLDED="true"/>
      <node ID="ID_162" TEXT="Staffing shortage" FOLDED="true"/>
      <node ID="ID_163" TEXT="Shared header/footer (school id, filters, timestamp)" FOLDED="true"/>
      <node ID="ID_164" TEXT="Print CSS (A4)" FOLDED="true"/>
      <node ID="ID_165" TEXT="PDF export - mPDF + bundled Amiri font (O-15)" FOLDED="true"/>
      <node ID="ID_166" TEXT="Excel export - PhpSpreadsheet, RTL sheet direction (O-32)" FOLDED="true"/>
    </node>
    <node ID="ID_167" TEXT="Backup / Restore">
      <node ID="ID_168" TEXT="Backup engine" FOLDED="true">
        <node ID="ID_169" TEXT="Schema + data, dependency-ordered (O-4)" FOLDED="true"/>
        <node ID="ID_170" TEXT="Versioned signature + SHA-256 checksum (S-8)" FOLDED="true"/>
      </node>
      <node ID="ID_171" TEXT="Restore engine" FOLDED="true">
        <node ID="ID_172" TEXT="Signature + checksum + version check before any write" FOLDED="true"/>
        <node ID="ID_173" TEXT="Mandatory pre-restore emergency backup" FOLDED="true"/>
        <node ID="ID_174" TEXT="Replay with FOREIGN_KEY_CHECKS=0 (O-5)" FOLDED="true"/>
        <node ID="ID_175" TEXT="Structural sanity + composite-FK spot-check (S-9)" FOLDED="true"/>
        <node ID="ID_176" TEXT="DDL can&apos;t roll back - emergency backup is the real safety net (O-5)" FOLDED="true"/>
      </node>
    </node>
    <node ID="ID_177" TEXT="Testing">
      <node ID="ID_178" TEXT="Auth &amp; permissions" FOLDED="true"/>
      <node ID="ID_179" TEXT="CRUD, search, filter, pagination" FOLDED="true"/>
      <node ID="ID_180" TEXT="Promotion incl. forced mid-batch failure" FOLDED="true"/>
      <node ID="ID_181" TEXT="Reports vs. hand-written queries" FOLDED="true"/>
      <node ID="ID_182" TEXT="Print / PDF / Excel Arabic rendering" FOLDED="true"/>
      <node ID="ID_183" TEXT="Backup/restore incl. truncated-file and corrupted-FK cases" FOLDED="true"/>
      <node ID="ID_184" TEXT="RTL on every screen, not just the obvious ones" FOLDED="true"/>
      <node ID="ID_185" TEXT="Full offline: network disconnected, grep for http(s)://" FOLDED="true"/>
    </node>
    <node ID="ID_186" TEXT="Deployment">
      <node ID="ID_187" TEXT="Setup Wizard" FOLDED="true">
        <node ID="ID_188" TEXT="Environment check" FOLDED="true"/>
        <node ID="ID_189" TEXT="DB connection test" FOLDED="true"/>
        <node ID="ID_190" TEXT="Schema install + seeds" FOLDED="true"/>
        <node ID="ID_191" TEXT="School profile" FOLDED="true"/>
        <node ID="ID_192" TEXT="First academic year" FOLDED="true"/>
        <node ID="ID_193" TEXT="Administrator account" FOLDED="true"/>
        <node ID="ID_194" TEXT="Locked permanently on completion (O-6)" FOLDED="true"/>
      </node>
      <node ID="ID_195" TEXT="Packaging" FOLDED="true">
        <node ID="ID_196" TEXT="vendor/ pre-populated" FOLDED="true"/>
        <node ID="ID_197" TEXT="Copy-to-htdocs, no build step" FOLDED="true"/>
        <node ID="ID_198" TEXT="Root .htaccess included" FOLDED="true"/>
      </node>
    </node>
    <node ID="ID_199" TEXT="Documentation">
      <node ID="ID_200" TEXT="README" FOLDED="true"/>
      <node ID="ID_201" TEXT="Developer docs (architecture, DB, conventions)" FOLDED="true"/>
      <node ID="ID_202" TEXT="Administrator guide (students, promotion, backup, restore, years)" FOLDED="true"/>
    </node>
    <node ID="ID_203" TEXT="Implementation Phases">
      <node ID="ID_204" TEXT="Phase 3 - Database (migrations, seeds)" FOLDED="true"/>
      <node ID="ID_205" TEXT="Phase 4 - Core Infrastructure (auth, middleware, router, logging)" FOLDED="true"/>
      <node ID="ID_206" TEXT="Phase 5 - UI Foundation (layout, components, .htaccess)" FOLDED="true"/>
      <node ID="ID_207" TEXT="Phase 6 - Core Modules (Dashboard-&gt;Years-&gt;Classes-&gt;Subjects-&gt;Teachers-&gt;Students-&gt;Assignments)" FOLDED="true"/>
      <node ID="ID_208" TEXT="Phase 7 - Reports (6 builders, print/PDF/Excel)" FOLDED="true"/>
      <node ID="ID_209" TEXT="Phase 8 - Backup &amp; Restore" FOLDED="true"/>
      <node ID="ID_210" TEXT="Phase 9 - Testing (full §M pass)" FOLDED="true"/>
      <node ID="ID_211" TEXT="Phase 10 - Final QA &amp; Docs" FOLDED="true"/>
    </node>
    <node ID="ID_212" TEXT="Key Task Dependencies">
      <node ID="ID_213" TEXT="Migrations -&gt; Seeds" FOLDED="true"/>
      <node ID="ID_214" TEXT="Auth -&gt; RoleGuard -&gt; every protected route" FOLDED="true"/>
      <node ID="ID_215" TEXT="Rollover -&gt; Promotion (classes must exist first)" FOLDED="true"/>
      <node ID="ID_216" TEXT="Promotion -&gt; Close-year guard" FOLDED="true"/>
      <node ID="ID_217" TEXT="Full stable schema -&gt; Backup engine -&gt; Restore engine" FOLDED="true"/>
      <node ID="ID_218" TEXT="Every module -&gt; its own §M checklist row, as it ships" FOLDED="true"/>
    </node>
  </node>
</map>
