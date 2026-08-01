-- St. Joseph's MHSS — dynamic site schema (per DYNAMIC_MIGRATION_PLAN.md §3)
-- Loaded automatically by the MariaDB container on first init.
SET NAMES utf8mb4;

-- ============ core / auth ============
CREATE TABLE IF NOT EXISTS admin_users (
  id            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(100) NOT NULL DEFAULT 'Administrator',
  role                 VARCHAR(20) NOT NULL DEFAULT 'owner',   -- multi-user readiness (SEC-08/09)
  must_change_password TINYINT(1)  NOT NULL DEFAULT 0,         -- forced rotation on first login
  password_changed_at  DATETIME NULL,
  failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until  DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  skey   VARCHAR(64) PRIMARY KEY,
  svalue TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin activity trail (SECURITY.md SEC-19). No secrets are ever written here.
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id   TINYINT UNSIGNED NULL,
  action     VARCHAR(40)  NOT NULL,
  entity     VARCHAR(40)  NULL,
  entity_id  INT UNSIGNED NULL,
  detail     VARCHAR(500) NOT NULL DEFAULT '',
  ip         VARCHAR(45)  NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin_when (admin_id, created_at),
  KEY idx_when (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ media ============
CREATE TABLE IF NOT EXISTS images (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  legacy_path   VARCHAR(255) NULL UNIQUE,
  original_name VARCHAR(255) NULL,
  alt_text      VARCHAR(255) NOT NULL DEFAULT '',
  mime          VARCHAR(32)  NULL,
  width         SMALLINT UNSIGNED NULL,
  height        SMALLINT UNSIGNED NULL,
  preset_key    VARCHAR(40)  NULL,
  crop_rect     VARCHAR(64)  NULL,
  version       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS image_presets (
  preset_key VARCHAR(40) PRIMARY KEY,
  label      VARCHAR(80)  NOT NULL,
  max_w      SMALLINT UNSIGNED NOT NULL,
  max_h      SMALLINT UNSIGNED NOT NULL,
  aspect_w   TINYINT UNSIGNED NULL,
  aspect_h   TINYINT UNSIGNED NULL,
  mode       ENUM('cover','fit') NOT NULL DEFAULT 'cover',
  quality    TINYINT UNSIGNED NOT NULL DEFAULT 82
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS image_renditions (
  image_id   INT UNSIGNED NOT NULL,
  preset_key VARCHAR(40)  NOT NULL,
  format     ENUM('jpeg','webp') NOT NULL,
  width      SMALLINT UNSIGNED NOT NULL,
  height     SMALLINT UNSIGNED NOT NULL,
  bytes      INT UNSIGNED NOT NULL,
  PRIMARY KEY (image_id, preset_key, format),
  CONSTRAINT fk_rend_img FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS image_links (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type VARCHAR(24) NOT NULL,
  owner_id   INT UNSIGNED NOT NULL,
  role       VARCHAR(24) NOT NULL DEFAULT 'carousel',
  image_id   INT UNSIGNED NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member (owner_type, owner_id, role, image_id),
  KEY idx_owner (owner_type, owner_id, role, position),
  CONSTRAINT fk_link_img FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ pages & heroes ============
CREATE TABLE IF NOT EXISTS pages (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(40) NOT NULL UNIQUE,
  title         VARCHAR(120) NOT NULL DEFAULT 'St.Joseph''s MHSS, Ondipudur',
  heading_html  VARCHAR(255) NULL,
  heading2_html VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hero_slides (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id       SMALLINT UNSIGNED NOT NULL,
  image_id      INT UNSIGNED NOT NULL,
  caption_title VARCHAR(120) NOT NULL DEFAULT '',
  caption_text  VARCHAR(255) NOT NULL DEFAULT '',
  button_label  VARCHAR(40)  NULL,
  button_url    VARCHAR(255) NULL,
  position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_page (page_id, position),
  CONSTRAINT fk_hero_page FOREIGN KEY (page_id)  REFERENCES pages(id)  ON DELETE CASCADE,
  CONSTRAINT fk_hero_img  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ content: home page areas ============
CREATE TABLE IF NOT EXISTS profiles (
  id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_key     VARCHAR(32) NOT NULL UNIQUE,
  heading      VARCHAR(60)  NOT NULL,
  person_name  VARCHAR(120) NOT NULL,
  message_html MEDIUMTEXT   NOT NULL,
  image_id     INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profile_img FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unique_features (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(150) NOT NULL,
  body_html  MEDIUMTEXT   NOT NULL,
  image_id   INT UNSIGNED NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_uf_img FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticker_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label      VARCHAR(200) NOT NULL,
  url        VARCHAR(255) NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS update_slides (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(120) NOT NULL,
  subtitle   VARCHAR(200) NOT NULL,
  link_url   VARCHAR(255) NULL,
  link_label VARCHAR(40)  NOT NULL DEFAULT 'View More',
  image_id   INT UNSIGNED NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_upd_img FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mark_years (
  id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year       SMALLINT UNSIGNED NOT NULL UNIQUE,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mark_entries (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year_id      SMALLINT UNSIGNED NOT NULL,
  standard     ENUM('10','11','12') NOT NULL,
  rank_label   VARCHAR(8)   NOT NULL,
  student_name VARCHAR(120) NOT NULL,
  marks_scored SMALLINT UNSIGNED NOT NULL,
  marks_total  SMALLINT UNSIGNED NOT NULL,
  position     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_year_std (year_id, standard, position),
  CONSTRAINT fk_entry_year FOREIGN KEY (year_id) REFERENCES mark_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ content: remaining areas (created now, seeded when their pages convert) ============
CREATE TABLE IF NOT EXISTS achievements (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type       ENUM('achievement','award') NOT NULL,
  title      VARCHAR(150) NOT NULL,
  subtext    VARCHAR(255) NOT NULL DEFAULT '',
  image_id   INT UNSIGNED NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_type (type, position),
  CONSTRAINT fk_ach_img FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_sections (
  id               TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug             VARCHAR(20) NOT NULL UNIQUE,
  page_id          SMALLINT UNSIGNED NOT NULL,
  name             VARCHAR(80) NOT NULL,
  intro_heading    VARCHAR(80) NOT NULL,
  intro_html       MEDIUMTEXT  NOT NULL,
  timeline_heading VARCHAR(60) NOT NULL DEFAULT 'Timeline - 2024',
  card_title       VARCHAR(80) NOT NULL,
  card_range       VARCHAR(40) NOT NULL,
  card_image_id    INT UNSIGNED NULL,
  position         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sec_page FOREIGN KEY (page_id) REFERENCES pages(id),
  CONSTRAINT fk_sec_img  FOREIGN KEY (card_image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS timeline_entries (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id  TINYINT UNSIGNED NOT NULL,
  month_label VARCHAR(20) NOT NULL,
  time_label  VARCHAR(30) NOT NULL DEFAULT '2024 - present',
  events_text TEXT NOT NULL,
  position    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sec (section_id, position),
  CONSTRAINT fk_tl_sec FOREIGN KEY (section_id) REFERENCES school_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS section_events (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id TINYINT UNSIGNED NOT NULL,
  title      VARCHAR(120) NOT NULL,
  body_html  TEXT NOT NULL,
  image_id   INT UNSIGNED NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sec (section_id, position),
  CONSTRAINT fk_se_sec FOREIGN KEY (section_id) REFERENCES school_sections(id) ON DELETE CASCADE,
  CONSTRAINT fk_se_img FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academies (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(40) NOT NULL UNIQUE,
  card_title      VARCHAR(100) NOT NULL,
  card_subtitle   VARCHAR(120) NOT NULL,
  banner_title    VARCHAR(100) NOT NULL,
  banner_subtitle VARCHAR(120) NOT NULL,
  content_heading VARCHAR(100) NOT NULL,
  body_html       MEDIUMTEXT   NOT NULL,
  card_image_id   INT UNSIGNED NULL,
  bg_image_id     INT UNSIGNED NULL,
  position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ac_card FOREIGN KEY (card_image_id) REFERENCES images(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ac_bg   FOREIGN KEY (bg_image_id)   REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80)  NOT NULL,
  training_time VARCHAR(80)  NOT NULL,
  details_html  TEXT         NOT NULL,
  image_id      INT UNSIGNED NULL,
  position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sport_img FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facilities (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug             VARCHAR(40) NOT NULL UNIQUE,
  name             VARCHAR(80) NOT NULL,
  description_html TEXT NOT NULL,
  bg_image_id      INT UNSIGNED NULL,
  position         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fac_bg FOREIGN KEY (bg_image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_albums (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(40) NOT NULL UNIQUE,
  title         VARCHAR(100) NOT NULL,
  card_image_id INT UNSIGNED NULL,
  position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_alb_img FOREIGN KEY (card_image_id) REFERENCES images(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS album_years (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id   INT UNSIGNED NOT NULL,
  year_label VARCHAR(20) NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_album_year (album_id, year_label),
  CONSTRAINT fk_ay_album FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ preset + settings seed ============
INSERT IGNORE INTO image_presets (preset_key, label, max_w, max_h, aspect_w, aspect_h, mode, quality) VALUES
  ('hero_16x7',     'Page hero slide (16:7)',        1920,  840, 16, 7,  'cover', 80),
  ('gallery_tile',  'Gallery grid tile (5:7)',        500,  700,  5, 7,  'cover', 82),
  ('gallery_full',  'Gallery lightbox view',         1600, 1200, NULL, NULL, 'fit', 82),
  ('card_4x3',      'Card thumbnail (4:3)',           800,  600,  4, 3,  'cover', 82),
  ('content_slide', 'Content carousel slide (fit)',  1200,  900, NULL, NULL, 'fit', 82),
  ('portrait_4x5',  'Portrait (4:5)',                 800, 1000,  4, 5,  'cover', 82),
  ('feature_4x3',   'Feature image (4:3)',           1000,  750,  4, 3,  'cover', 82),
  ('update_16x9',   'Update slide (16:9)',           1280,  720, 16, 9,  'cover', 82),
  ('bg_wide',       'Section background (16:9)',     1920, 1080, 16, 9,  'cover', 74);

INSERT IGNORE INTO settings (skey, svalue) VALUES
  ('marks_years_shown', '3'),
  ('site_name', 'St.Joseph''s MHSS, Ondipudur');
