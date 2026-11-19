-- Nizam -- authorized document signatories (PostgreSQL twin of migration 043).
--
-- See database/migrations/043_document_signatories.sql for the full reasoning.
-- The same two authorised roles are created empty -- never a fabricated name
-- or signature -- so documents keep their honest role line until the school
-- configures the real signatory.

CREATE TABLE document_signatories (
  id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  role_code      VARCHAR(40)  NOT NULL,
  name_ar        VARCHAR(150) NOT NULL DEFAULT '',
  name_en        VARCHAR(150) NOT NULL DEFAULT '',
  title_ar       VARCHAR(100) NOT NULL DEFAULT '',
  title_en       VARCHAR(100) NOT NULL DEFAULT '',
  font_key       VARCHAR(40)  NOT NULL DEFAULT 'aref-ruqaa',
  signature_path VARCHAR(255),
  stamp_path     VARCHAR(255),
  placement      VARCHAR(20)  NOT NULL DEFAULT 'every_page',
  is_active      BOOLEAN      NOT NULL DEFAULT TRUE,
  sort_order     INTEGER      NOT NULL DEFAULT 0,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uq_document_signatories_role ON document_signatories (role_code);
CREATE INDEX idx_document_signatories_order ON document_signatories (sort_order, id);

INSERT INTO document_signatories (role_code, title_ar, title_en, font_key, placement, sort_order)
VALUES
  ('principal',       'مدير المدرسة', 'School Principal', 'aref-ruqaa', 'every_page', 1),
  ('student_affairs', 'شؤون الطلبة',  'Student Affairs',  'amiri',      'every_page', 2)
ON CONFLICT (role_code) DO NOTHING;

-- Every table in the online deployment is protected by RLS; a new table that
-- forgets this would silently bypass the platform's row-level isolation.
ALTER TABLE public.document_signatories ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.document_signatories;
CREATE POLICY nizam_app_full_access ON public.document_signatories FOR ALL TO nizam_app USING (true) WITH CHECK (true);
