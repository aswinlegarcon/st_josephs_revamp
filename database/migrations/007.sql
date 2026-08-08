-- 007 — F3 SEO pack: per-URL title + meta description, editable in the admin
-- panel. Additive only. Keyed by the page's URL slug (basename without .php;
-- home = 'index'), because the 42 public URLs span several entities
-- (pages, academies, gallery_albums) — one flat table covers them all.
CREATE TABLE IF NOT EXISTS seo_meta (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(64)  NOT NULL,
  title       VARCHAR(160) NOT NULL DEFAULT '',
  description VARCHAR(300) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seo_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
