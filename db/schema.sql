-- CP2020 Combat Calculator — fire event log
-- Run once against your MariaDB database to create the table.
--   mysql -u USER -p DBNAME < db/schema.sql

CREATE TABLE IF NOT EXISTS fire_events (
  id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  fired_at      DATETIME(3)      NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  mode          ENUM('single','auto','burst') NOT NULL,
  params_json   TEXT             NOT NULL,   -- {skill, difficulty, damage, shots?, bursts?}
  hits          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  misses        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_shots   SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- shots fired (auto/single) or bursts attempted
  total_bullets SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- burst mode: bullets that landed
  results_json  MEDIUMTEXT       NOT NULL,             -- shots[] or bursts[] array
  INDEX idx_fired_at (fired_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
