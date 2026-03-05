<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0
// ── Database configuration ─────────────────────────────────────────────────────
// For Docker: set DB_HOST, DB_NAME, DB_USER, DB_PASS as environment variables.
// For native hosting: edit the defaults below.

const DB_HOST_DEFAULT = 'localhost';
const DB_NAME_DEFAULT = 'cyberpunk_roller';
const DB_USER_DEFAULT = 'root';
const DB_PASS_DEFAULT = '';
// ──────────────────────────────────────────────────────────────────────────────

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . (getenv('DB_HOST') ?: DB_HOST_DEFAULT)
                . ';dbname=' . (getenv('DB_NAME') ?: DB_NAME_DEFAULT)
                . ';charset=utf8mb4',
            getenv('DB_USER') ?: DB_USER_DEFAULT,
            getenv('DB_PASS') ?: DB_PASS_DEFAULT,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS fire_events (
                id            INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
                fired_at      DATETIME(3)       NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                mode          ENUM('single','auto','burst') NOT NULL,
                params_json   TEXT              NOT NULL,
                hits          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                misses        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                total_shots   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                total_bullets SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                results_json  MEDIUMTEXT        NOT NULL,
                INDEX idx_fired_at (fired_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    return $pdo;
}
