-- K4 (2026-08-23): the About page's "School timings" table becomes DB-driven
-- (it was hardcoded markup in the rules block). One row per table line,
-- editable/orderable/creatable in the admin (registry entity rules_timing).
-- Additive only. Apply via mPanel's DB tool on prod; schema.sql mirrors this
-- for fresh installs. Seeded with the five shipped rows (idempotent).

CREATE TABLE IF NOT EXISTS rules_timings (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  timing   VARCHAR(60)  NOT NULL,
  activity VARCHAR(120) NOT NULL,
  position INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
