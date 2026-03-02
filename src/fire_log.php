<?php

/**
 * Persist a fire event to the shared log and return its new row ID.
 * Old events (> 15 min) are pruned on each insert.
 *
 * Requires src/db.php to be loaded before calling this function.
 *
 * @param array $r  The full response array built by api/roll.php
 * @return int      The auto-increment ID of the new row
 */
function saveFireEvent(array $r): int
{
    $db   = getDB();
    $mode = $r['mode'];

    $totalShots   = $mode === 'burst' ? ($r['params']['bursts'] ?? 1) : ($r['params']['shots'] ?? 1);
    $totalBullets = $r['totalBullets'] ?? 0;
    $results      = json_encode($mode === 'burst' ? ($r['bursts'] ?? []) : ($r['shots'] ?? []));

    $stmt = $db->prepare(
        'INSERT INTO fire_events
            (mode, params_json, hits, misses, total_shots, total_bullets, results_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $mode,
        json_encode($r['params']),
        (int)$r['hits'],
        (int)$r['misses'],
        (int)$totalShots,
        (int)$totalBullets,
        $results,
    ]);

    // Prune events older than 15 minutes to keep the table tidy
    $db->exec("DELETE FROM fire_events WHERE fired_at < NOW() - INTERVAL 15 MINUTE");

    return (int)$db->lastInsertId();
}
