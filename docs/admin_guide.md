# Hadaba Al-Ahram Language School Administrator Guide

Welcome to Hadaba Al-Ahram Language School. This guide covers daily operations and system management.

## 1. Access and Responsibilities
- **Login**: Access the system via the `/login` route. After 5 failed attempts, accounts are temporarily locked to prevent brute-force attacks.
- **Roles**: 
  - **Admin**: Full access, including Settings and Backup/Restore.
  - **Staff**: Limited access restricted to managing students, teachers, and academic data.

## 2. Managing Users & Permissions
- Administrators manage every account from **Users** in the sidebar (Administrator only): add a new Administrator or Staff account, edit an existing one, reset a password (leave the password field blank to keep the current one), or archive/restore an account. Archiving never deletes a user -- it only prevents sign-in, matching the same archive-only rule used everywhere else in the system.
- The system will not let you archive or demote the last active Administrator account away from the Administrator role -- there must always be at least one active Administrator who can sign in and manage the system.
- Staff accounts cannot access Backup & Restore, Settings, Users, or any other administrator-only screen, even by typing the URL directly -- this is enforced by the server, not just hidden menu items.
- **Activity Log** (sidebar) shows a history of consequential actions. Administrators see every user's actions; Staff see only their own.

## 3. Academic Year Management
- **Active Year**: The system operates on exactly one "Active" Academic Year at a time. All workload and enrollment calculations are scoped to this active year.
- **Rollover**: Use the "Create Year from Previous" tool to clone classes and staffing requirements into a new year without affecting the active year's operations.
- **Closing a Year**: A year cannot be closed if any students have an unresolved promotion status (e.g., active enrollment without a graduation, transfer, or promotion decision).

## 4. Student, Teacher, Class, and Subject Workflows
- **Students**: Create records with basic demographic data. The system automatically generates unique Student IDs (e.g., `STU-2026-000118`). Archiving a student automatically cascades to archive their active enrollments. A photo is optional -- upload or remove one any time from the student's Edit screen.
- **Teachers**: Manage teacher profiles, including an optional photo. Note that teacher assignments are soft-checked against their qualified subjects, but administrators can override this if necessary.
- **Classes & Subjects**: Define the curriculum and physical class capacities. Capacity is strictly monitored on class lists and density reports.

## 5. Promotion Workflows
The promotion engine is designed to handle mass transitions safely:
1. **Preview**: Select a source year and target year. Review all recommended outcomes (Promote, Repeat, Graduate, Transfer, Withdraw).
2. **Confirm**: Confirming the batch writes all records in a single transactional batch.
3. **Rollback**: If a mistake is made, individual student promotions can be undone from their profile, cleanly reverting their state.

## 6. Reports & Exports
- Reports (Religion Stats, Class Lists, Class Density, Teacher Workload, Staffing Shortages) provide real-time metrics.
- **Exporting**: All tables and reports can be exported to Print (A4 CSS), PDF, or Excel. The system guarantees correct Right-To-Left (RTL) formatting and column ordering for Arabic exports.

## 7. Backup & Restore Operations
- **Backup**: We recommend taking a manual backup before any major operation (like Year Rollover or Mass Promotion). Backups generate a single `.sql` file combining schema and data.
- **Restore**: **WARNING:** Restoring a database overwrites all current system data.
  - The system will safely reject corrupt, incomplete, or foreign SQL files.
  - An emergency "pre-restore" snapshot is automatically taken before applying any uploaded file, protecting you against corrupted uploads.

## 8. Settings
Administrators reach **Settings** from the sidebar, grouped into five screens so a routine change never sits next to a security-relevant one:
- **Profile**: school name (English/Arabic), address, phone, an optional logo (JPG/PNG, shown throughout the app and on every printed/exported report), and an optional custom report footer line.
- **Localization**: the default language a new sign-in session starts in.
- **Academic Rules**: the default expected weekly teaching capacity used when a new academic year is created, and the Student/Teacher ID generation patterns.
- **Security**: maximum login attempts before lockout, lockout duration, and how long an idle session stays signed in. Changes take effect immediately for new sign-ins and new lockout decisions.
- **Backup**: where one-click backups are saved. Moving this to a new folder does not move backups already saved to the old one.

## 8. Operational & Security Warnings
- **Keep the System Offline**: Hadaba Al-Ahram Language School is designed for local LAN usage. Do not expose this server directly to the public internet without consulting an IT security professional to configure HTTPS and firewall rules.
- **Uploads**: All student photos and documents are stored securely. Never place custom PHP scripts in the `uploads/` or `backups/` directories.
