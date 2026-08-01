-- Migration 002 — audit_log (PHASES.md S4 / SECURITY.md SEC-19)
-- Additive. Apply once against an existing production DB via mPanel; fresh
-- installs get this table from schema.sql.

CREATE TABLE IF NOT EXISTS audit_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id   TINYINT UNSIGNED NULL,                 -- NULL for failed logins (unknown user)
  action     VARCHAR(40)  NOT NULL,                 -- login.ok | login.fail | logout | password.change
                                                    -- | field.save | item.create|update|delete | order.save | upload
  entity     VARCHAR(40)  NULL,
  entity_id  INT UNSIGNED NULL,
  detail     VARCHAR(500) NOT NULL DEFAULT '',
  ip         VARCHAR(45)  NULL,                     -- IPv4/IPv6; never any secret
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin_when (admin_id, created_at),
  KEY idx_when (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
