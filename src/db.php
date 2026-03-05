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

/**
 * Remove entries older than 18 minutes — 3 minutes beyond the 15-minute
 * display window, ensuring no event still visible to a user is deleted.
 * Called on every insert so the table stays bounded without a cron job.
 */
function pruneOldEvents(): void
{
    getDB()->exec("DELETE FROM fire_events WHERE fired_at < datetime('now', '-18 minutes')");
}

/**
 * Safety backstop against runaway DB growth.
 *
 * Threshold rationale: worst-case small group (8 users, rate-limited,
 * one roll every 10 s over the 18-min retention window) produces ~864
 * events × ~3 KB avg ≈ 2.6 MB. 5 MB gives roughly 2× headroom.
 *
 * When exceeded, entries already outside the visible 15-min window are
 * dropped first. If still over threshold (extreme edge case), the oldest
 * half of remaining rows is removed.
 */
function pruneOversizedDB(): void
{
    $db        = getDB();
    $pageCount = (int)$db->query('PRAGMA page_count')->fetchColumn();
    $pageSize  = (int)$db->query('PRAGMA page_size')->fetchColumn();
    if ($pageCount * $pageSize < 5_242_880) return; // under 5 MB — nothing to do

    // First pass: drop entries already outside the visible window
    $db->exec("DELETE FROM fire_events WHERE fired_at < datetime('now', '-10 minutes')");

    // Second pass: if still over threshold, drop the oldest half
    $pageCount = (int)$db->query('PRAGMA page_count')->fetchColumn();
    if ($pageCount * $pageSize >= 5_242_880) {
        $half = (int)$db->query('SELECT COUNT(*) / 2 FROM fire_events')->fetchColumn();
        if ($half > 0) {
            $db->exec(
                "DELETE FROM fire_events WHERE id IN
                 (SELECT id FROM fire_events ORDER BY fired_at ASC LIMIT $half)"
            );
        }
    }
}
