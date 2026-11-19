-- Nizam -- signature appearance controls (PostgreSQL twin of migration 046).
-- See database/migrations/046_signature_appearance.sql for the reasoning.

ALTER TABLE document_signatories
  ADD COLUMN font_size NUMERIC(4,1) NOT NULL DEFAULT 10.5,
  ADD COLUMN font_weight SMALLINT NOT NULL DEFAULT 600,
  ADD COLUMN text_color VARCHAR(7) NOT NULL DEFAULT '#1F2937',
  ADD COLUMN letter_spacing NUMERIC(3,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN alignment VARCHAR(10) NOT NULL DEFAULT 'center';
