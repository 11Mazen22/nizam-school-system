-- Nizam School Management System -- Seed: subjects
--
-- IMPLEMENTATION CHOICE (documented per the Phase 3 kickoff's "Case B/conservative"
-- rule -- see the Phase 3 verification report): the frozen blueprint (§A-T) never
-- pins an exact default subject list, only "default subjects" in the abstract
-- (§C, §N, §R). The ORIGINAL project brief (item 5, "SUBJECT MANAGEMENT") gave a
-- concrete, explicit example list -- reused here verbatim as the starter seed,
-- since anything beyond the brief's own given examples would mean inventing school
-- curriculum content with no basis in either document. Trivially editable afterward
-- via the Subjects module (Phase 6) -- this is seed data, not an architecture
-- decision. ON DUPLICATE KEY UPDATE: subjects are a shared reference list like
-- roles, not per-installation user state, so re-asserting the seed's labels on a
-- future update is the same intended behavior as for roles/permissions.

INSERT INTO subjects (code, name_en, name_ar, is_active) VALUES
  ('AR',   'Arabic',           'اللغة العربية',       1),
  ('EN',   'English',          'اللغة الإنجليزية',   1),
  ('MATH', 'Mathematics',      'الرياضيات',           1),
  ('SCI',  'Science',          'العلوم',              1),
  ('SOC',  'Social Studies',   'الدراسات الاجتماعية', 1),
  ('CS',   'Computer',         'الحاسوب',             1),
  ('REL',  'Religious Studies','التربية الدينية',     1)
ON DUPLICATE KEY UPDATE
  name_en = VALUES(name_en),
  name_ar = VALUES(name_ar);
