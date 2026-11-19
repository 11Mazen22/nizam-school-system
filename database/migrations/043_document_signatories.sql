-- Nizam -- authorized document signatories, configured once and applied to
-- every document that declares their role.
--
-- The school's real documents are not valid without the two signatures the
-- institution authorises: the principal and student affairs. Before this
-- table each printed document carried only a blank dotted line and a role
-- label, so every export had to be signed by hand (or worse, printed without
-- a signature at all).
--
-- This table stores the institution's own configuration -- never a hard-coded
-- name, a fabricated signature image or a sample mark. A new installation
-- starts with both rows present but empty, so the settings screen can explain
-- exactly which two signatures are missing; documents keep printing the role
-- line until the school fills them in.
--
-- `font_key` selects one of the self-hosted Arabic typefaces bundled with the
-- application, so the two signatories are visually distinct and the PDF is
-- self-contained. `placement` defaults to every_page: the school asked for
-- the signature to repeat on every applicable page, not just the last one.
-- Image columns point at files UploadService stored outside public/; they are
-- streamed/embedded by the document engine, never linked by client path.

CREATE TABLE document_signatories (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_code      VARCHAR(40)  NOT NULL COMMENT 'principal / student_affairs / deputy / staff_affairs / financial_affairs',
  name_ar        VARCHAR(150) NOT NULL DEFAULT '',
  name_en        VARCHAR(150) NOT NULL DEFAULT '',
  title_ar       VARCHAR(100) NOT NULL DEFAULT '',
  title_en       VARCHAR(100) NOT NULL DEFAULT '',
  font_key       VARCHAR(40)  NOT NULL DEFAULT 'aref-ruqaa',
  signature_path VARCHAR(255) NULL,
  stamp_path     VARCHAR(255) NULL,
  placement      VARCHAR(20)  NOT NULL DEFAULT 'every_page' COMMENT 'every_page / first_page / last_page',
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order     INT          NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_document_signatories_role (role_code),
  KEY idx_document_signatories_order (sort_order, id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO document_signatories (role_code, title_ar, title_en, font_key, placement, sort_order)
VALUES
  ('principal',       'مدير المدرسة', 'School Principal', 'aref-ruqaa', 'every_page', 1),
  ('student_affairs', 'شؤون الطلبة',  'Student Affairs',  'amiri',      'every_page', 2);
