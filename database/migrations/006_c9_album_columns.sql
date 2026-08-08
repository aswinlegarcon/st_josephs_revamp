-- C9 — gallery_albums: the hub card carries a sub-line ("2023 & 2024") and its
-- own title casing distinct from the album page's h1 (e.g. "Annual Day" on the
-- hub vs "annual Day" on the page — both shipped strings are preserved).
-- Additive-only (deploy rule): safe to apply to a live database.

ALTER TABLE gallery_albums
  ADD COLUMN card_sub VARCHAR(60)  NOT NULL DEFAULT '' AFTER title,
  ADD COLUMN heading  VARCHAR(100) NOT NULL DEFAULT '' AFTER title;
