<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0

/**
 * Integration tests for saveFireEvent() (src/fire_log.php).
 * Skipped gracefully when src/db.php is not present.
 * All inserts are wrapped in a transaction that is rolled back at the end.
 */

if (!file_exists(__DIR__ . '/../src/db.php')) {
    echo "  [SKIP] test_fire_log.php — src/db.php not found\n";
    return;
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/fire_log.php';

$db = getDB();
$db->beginTransaction();

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeParams(string $mode, int $skill = 10, int $difficulty = 15, string $damage = '2D6'): array
{
    $p = ['skill' => $skill, 'difficulty' => $difficulty, 'damage' => $damage];
    if ($mode === 'auto')  $p['shots']  = 3;
    if ($mode === 'burst') $p['bursts'] = 2;
    return $p;
}

function singleResponse(): array
{
    return [
        'mode'   => 'single',
        'params' => makeParams('single'),
        'shots'  => [
            ['num' => 1, 'hit' => true,  'skillRoll' => [], 'total' => 18, 'location' => 'Torso', 'rawDamage' => 7, 'armor' => null],
        ],
        'hits'   => 1,
        'misses' => 0,
    ];
}

function autoResponse(): array
{
    return [
        'mode'   => 'auto',
        'params' => makeParams('auto'),
        'shots'  => [
            ['num' => 1, 'hit' => true,  'skillRoll' => [], 'total' => 17, 'location' => 'Head',  'rawDamage' => 5,  'armor' => null],
            ['num' => 2, 'hit' => false, 'skillRoll' => [], 'total' => 12, 'location' => null,    'rawDamage' => null, 'armor' => null],
            ['num' => 3, 'hit' => true,  'skillRoll' => [], 'total' => 19, 'location' => 'Torso', 'rawDamage' => 8,  'armor' => null],
        ],
        'hits'   => 2,
        'misses' => 1,
    ];
}

function burstResponse(): array
{
    return [
        'mode'         => 'burst',
        'params'       => makeParams('burst'),
        'bursts'       => [
            ['num' => 1, 'hit' => true,  'skillRoll' => [], 'total' => 18, 'bulletCount' => 2, 'bullets' => []],
            ['num' => 2, 'hit' => false, 'skillRoll' => [], 'total' => 11, 'bulletCount' => 0, 'bullets' => []],
        ],
        'hits'         => 1,
        'misses'       => 1,
        'totalBullets' => 2,
    ];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

$id = saveFireEvent(singleResponse());
assert_true($id > 0, 'saveFireEvent: single-mode returns positive ID');

$id2 = saveFireEvent(autoResponse());
assert_true($id2 > 0, 'saveFireEvent: auto-mode returns positive ID');
assert_true($id2 > $id, 'saveFireEvent: auto-mode ID is greater than previous');

$id3 = saveFireEvent(burstResponse());
assert_true($id3 > 0, 'saveFireEvent: burst-mode returns positive ID');

// Verify the rows exist within the transaction
$count = (int)$db->query('SELECT COUNT(*) FROM fire_events')->fetchColumn();
assert_true($count >= 3, 'saveFireEvent: at least 3 rows inserted in transaction');

// Pruning: insert a row with fired_at 20 minutes ago, then call saveFireEvent again
// and verify the old row is gone.
$db->exec(
    "INSERT INTO fire_events
        (mode, params_json, hits, misses, total_shots, total_bullets, results_json, fired_at)
     VALUES ('single', '{}', 0, 1, 1, 0, '[]', datetime('now', '-20 minutes'))"
);
$oldId = (int)$db->lastInsertId();
assert_true($oldId > 0, 'saveFireEvent pruning: old row inserted');

saveFireEvent(singleResponse());

$stillExists = (int)$db->query(
    "SELECT COUNT(*) FROM fire_events WHERE id = $oldId"
)->fetchColumn();
assert_equal($stillExists, 0, 'saveFireEvent: row older than 18 min is pruned');

// ── Roll back all test data ───────────────────────────────────────────────────
$db->rollBack();
