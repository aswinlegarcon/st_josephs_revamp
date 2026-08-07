-- C1 — contact-form hardening (SECURITY.md SEC-23).
-- Server-side contact submissions: every enquiry is stored (inbox + audit),
-- and the (ip, created_at) index powers the per-IP hourly rate limit.
-- Additive-only (deploy rule): safe to apply to a live database.

CREATE TABLE IF NOT EXISTS contact_submissions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80)  NOT NULL,
  last_name  VARCHAR(80)  NOT NULL,
  email      VARCHAR(160) NOT NULL,
  mobile     VARCHAR(20)  NOT NULL,
  message    TEXT         NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  sent       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
