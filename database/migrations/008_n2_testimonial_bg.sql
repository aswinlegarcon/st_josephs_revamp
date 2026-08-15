-- N2 (2026-08-15): per-testimonial optional background photo.
-- NULL keeps the shipped card1/2/3 CSS statics (pixel-identical default);
-- a set image overrides that card's background via an inline style.
-- Additive only. Apply via mPanel's DB tool on prod; schema.sql mirrors this
-- for fresh installs.

ALTER TABLE testimonials
  ADD COLUMN bg_image_id INT UNSIGNED NULL AFTER body_html,
  ADD CONSTRAINT fk_testi_bg FOREIGN KEY (bg_image_id) REFERENCES images(id) ON DELETE RESTRICT;
