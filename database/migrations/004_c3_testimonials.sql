-- C3 — home-page student testimonials become an editable list.
-- name_html is a sanitized rich field (whitelist b/strong/i/em/br/p/span.hl-gold)
-- because the shipped headings contain <br> line breaks that must render.
-- Additive-only (deploy rule): safe to apply to a live database.

CREATE TABLE IF NOT EXISTS testimonials (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_html  VARCHAR(200) NOT NULL,
  body_html  MEDIUMTEXT   NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pos (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
