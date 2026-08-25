-- K7 (2026-08-25): two additive changes.
-- 1) staff_blocks: the Staffs page's photo+text blocks become a creatable,
--    orderable list (they were two fixed profile rows) — new cards alternate
--    image left/right automatically like the shipped pair.
-- 2) academies.fancy_gold: per-academy switch for the legacy "large gold"
--    highlight styling inside write-ups (seeded content carries inline
--    styles that predate the sanitizer); 1 = render as shipped, 0 = neutral.
-- Additive only. Apply via mPanel's DB tool on prod; schema.sql mirrors this
-- for fresh installs. The seeder copies the two shipped profile blocks into
-- staff_blocks once (empty-table-only).

CREATE TABLE IF NOT EXISTS staff_blocks (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title     VARCHAR(120) NOT NULL,
  body_html TEXT NOT NULL,
  image_id  INT UNSIGNED NULL,
  position  INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_staffblk_img FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE academies
  ADD COLUMN fancy_gold TINYINT(1) NOT NULL DEFAULT 1;
