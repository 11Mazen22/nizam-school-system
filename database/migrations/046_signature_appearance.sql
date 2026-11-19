-- Nizam -- signature appearance controls (migration 046).
--
-- Migration 043 let the school configure *who* signs and *which Arabic
-- typeface* distinguishes each signatory. The institutional document still
-- needs the rest of the typographic decision to be the school's, not a value
-- hard-coded in the renderer: size, weight, colour, letter spacing and
-- alignment. All of them are optional presentation, never identity: the
-- authorized name and role remain the only facts the document asserts.
--
-- Defaults reproduce the reviewed design exactly (10.5pt, semibold, ink
-- colour, no extra tracking, centred), so an installation that never opens
-- the new controls prints the same document it printed before.

ALTER TABLE document_signatories
  ADD COLUMN font_size DECIMAL(4,1) NOT NULL DEFAULT 10.5 AFTER font_key,
  ADD COLUMN font_weight SMALLINT NOT NULL DEFAULT 600 AFTER font_size,
  ADD COLUMN text_color VARCHAR(7) NOT NULL DEFAULT '#1F2937' AFTER font_weight,
  ADD COLUMN letter_spacing DECIMAL(3,2) NOT NULL DEFAULT 0.00 AFTER text_color,
  ADD COLUMN alignment VARCHAR(10) NOT NULL DEFAULT 'center' AFTER letter_spacing;
