<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0
// ── Database configuration ─────────────────────────────────────────────────────
// For Docker: set DB_PATH as an environment variable.
// For native hosting: edit the default path below.

const DB_PATH_DEFAULT = '/var/lib/cyberpunk-roller/fire.db';
// ──────────────────────────────────────────────────────────────────────────────

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $path = getenv('DB_PATH') ?: DB_PATH_DEFAULT;
        $pdo  = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS fire_events (
                id            INTEGER  PRIMARY KEY AUTOINCREMENT,
                fired_at      TEXT     NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f', 'now')),
                mode          TEXT     NOT NULL CHECK(mode IN ('single','auto','burst')),
                params_json   TEXT     NOT NULL,
                hits          INTEGER  NOT NULL DEFAULT 0,
                misses        INTEGER  NOT NULL DEFAULT 0,
                total_shots   INTEGER  NOT NULL DEFAULT 0,
                total_bullets INTEGER  NOT NULL DEFAULT 0,
                results_json  TEXT     NOT NULL
            )"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_fired_at ON fire_events(fired_at)');
    }
    return $pdo;
}
