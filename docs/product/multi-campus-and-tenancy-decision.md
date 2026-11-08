# ARCHITECTURE DECISION RECORD: MULTI-CAMPUS & TENANCY DEPLOYMENT STRATEGY
**Status:** Accepted  
**Date:** 2026-11-09  
**Decision Driver:** Product Architecture & Deployment Reality (Offline-First Reliability vs Cloud SaaS Scaling)  
**File:** `docs/product/multi-campus-and-tenancy-decision.md`

---

## 1. Context and Problem Statement

Nizam must evolve from an application tailored to a single physical school into a universal product capable of supporting:
1. **Single-site independent schools** (offline-first, running on a local XAMPP/Apache server inside the school LAN with zero cloud dependencies).
2. **Multi-campus educational institutions** (one unified school board or administration operating 2-5 distinct branches/campuses across a city or region).
3. **Multi-tenant cloud SaaS** (a centralized cloud service hosting hundreds of completely independent, competing school organizations).

How should Nizam architect its tenancy, data isolation, and deployment model to achieve multi-campus flexibility without compromising local offline resilience or introducing dangerous cross-tenant data leaks?

---

## 2. Evaluation of Deployment Models

### Model A: Standalone School Installation (Single-Tenant, Offline-First)
- **Architecture:** One independent Nizam instance, web server, and database per physical school.
- **Data Isolation:** Physical database boundary (100% immune to cross-school data leakage).
- **Offline Capability:** Operates flawlessly on local LAN without internet access.
- **Suitability:** Essential foundation for primary target schools with intermittent connectivity.

### Model B: Multi-Campus Organization (Single-Tenant, Multi-Branch)
- **Architecture:** One school organization with shared governance, shared curriculum, and unified central administration, but multiple physical campuses.
- **Data Isolation:** `campuses` table. Classes, rooms, student enrollments, and teacher assignments are scoped by `campus_id`.
- **User Scopes:** Central administrators (Owner, Principal, Director) have global visibility; Campus administrators (Branch Registrar, Campus Attendance Officer) are scoped to their assigned campus.
- **Reporting:** Central administrators receive consolidated school-wide reports as well as campus-by-campus comparative analytics.

### Model C: Multi-Tenant Cloud SaaS (Multi-Organization, Shared Infrastructure)
- **Risks of Superficial Implementation:** Simply adding `school_id` to database tables without query rewrites, tenant-isolated connection pooling, storage isolation, and audit separation creates severe security risks (FERPA/GDPR violations where one school can inspect or mutate another school's minors' records through URL tampering or background job contamination).
- **Database Isolation Strategy:** True multi-tenant SaaS requires either:
  1. *Database-per-tenant:* Dedicated database for each institution (safest, highest operational isolation, matches MySQL/PostgreSQL migration runners).
  2. *Schema-per-tenant (PostgreSQL schemas):* Schema isolation per tenant with search_path pinning.

---

## 3. The Architecture Decision

### 3.1 Adopted Dual-Deployment Strategy

Nizam officially implements and supports **Model A (Standalone)** and **Model B (Multi-Campus)** directly within the core product architecture, while structuring the database for **Database-per-Tenant Model C** cloud hosting:

1. **Native Multi-Campus Support:**
   - Introduce `campuses` table: `id`, `code`, `name_en`, `name_ar`, `address`, `phone`, `is_main`, `is_active`.
   - In single-campus schools, a default `Main Campus` is generated automatically during setup; the campus selector remains unobtrusive.
   - In multi-campus institutions, classes, rooms, and attendance rosters are assigned to campuses.
   - Central reporting consolidates data or filters by campus.
2. **Database-per-Tenant for Cloud Multi-Tenancy:**
   - For cloud providers or hosting partners hosting multiple independent schools, each school receives its own database (`nizam_school_1`, `nizam_school_2`, etc.) managed via Nizam's automated migration and seed runner.
   - This provides **100% cryptographic and physical isolation** between separate schools, eliminates any possibility of cross-school data leaks, enables per-school backup/restore cycles, and allows individual schools to be migrated between cloud and offline on-premise servers with zero schema translation.

---

## 4. Consequences and Safeguards

### Positive Consequences
- **Zero Offline Compromise:** Local schools in areas with poor internet can run their standalone or multi-campus installation without cloud bloat.
- **Absolute Data Privacy:** Minors' sensitive data (health, disciplinary, grades, national IDs) is never colocated in shared tables with competing institutions.
- **Safe Backups & Disaster Recovery:** When a school restores a backup via `/backups/restore`, it restores *only* its own database without impacting any other school.
- **Clean Audit Trail:** Security and activity logs are strictly self-contained within each school's database.

### Implementation Checklist
- [x] Schema migration for `campuses` table.
- [x] Campus scoping helper in `AcademicYearContext` / session.
- [x] Context bar campus badge / selector.
- [x] Campus-filtered reporting in `ReportService`.
