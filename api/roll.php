<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../src/dice.php';
require_once __DIR__ . '/../src/combat.php';
require_once __DIR__ . '/../src/armor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$mode       = trim((string)($input['mode'] ?? ''));
$skill      = array_key_exists('skill', $input)      ? (int)$input['skill']      : null;
$difficulty = array_key_exists('difficulty', $input) ? (int)$input['difficulty'] : null;
$damage     = trim((string)($input['damage'] ?? ''));

$missing = [];
if ($mode === '')         $missing[] = 'mode';
if ($skill === null)      $missing[] = 'skill';
if ($difficulty === null) $missing[] = 'difficulty';
if ($damage === '')       $missing[] = 'damage';

if ($missing) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing)]);
    exit;
}

// Validate damage notation
try {
    rollDice($damage, fn($a, $b) => 1);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid damage notation: ' . htmlspecialchars($damage)]);
    exit;
}

// Optional armor SP — normalize all six location keys to non-negative ints
$armorSP = null;
if (isset($input['armorSP']) && is_array($input['armorSP'])) {
    $spKeys  = ['head', 'torso', 'rightArm', 'leftArm', 'rightLeg', 'leftLeg'];
    $armorSP = [];
    foreach ($spKeys as $key) {
        $armorSP[$key] = max(0, (int)($input['armorSP'][$key] ?? 0));
    }
}

$params = [
    'skill'      => $skill,
    'difficulty' => $difficulty,
    'damage'     => $damage,
];

// ── Build $response per mode, then save + echo once ─────────────────────────

switch ($mode) {
    case 'single':
        $workingSP = $armorSP;
        $result    = processShot($skill, $difficulty, $damage);
        $armor     = null;

        if ($result['hit'] && $workingSP !== null) {
            $armor = applyArmorToHit($result['location']['location'], $result['damage']['total'], $workingSP);
        }

        $shots    = [formatShot($result, 1, $armor)];
        $hits     = $result['hit'] ? 1 : 0;
        $response = [
            'mode'   => 'single',
            'params' => $params,
            'shots'  => $shots,
            'hits'   => $hits,
            'misses' => 1 - $hits,
        ];
        if ($workingSP !== null) $response['finalSP'] = $workingSP;
        break;

    case 'auto':
        $shotCount = array_key_exists('shots', $input) ? (int)$input['shots'] : 0;
        if ($shotCount < 1) {
            http_response_code(400);
            echo json_encode(['error' => '"shots" must be a positive integer for automatic mode.']);
            exit;
        }

        $workingSP = $armorSP;
        $shots     = [];
        $hits      = 0;

        for ($i = 0; $i < $shotCount; $i++) {
            $result = processShot($skill, $difficulty, $damage);
            $armor  = null;

            if ($result['hit'] && $workingSP !== null) {
                $armor = applyArmorToHit($result['location']['location'], $result['damage']['total'], $workingSP);
            }

            $shots[] = formatShot($result, $i + 1, $armor);
            if ($result['hit']) $hits++;
        }

        $params['shots'] = $shotCount;
        $response = [
            'mode'   => 'auto',
            'params' => $params,
            'shots'  => $shots,
            'hits'   => $hits,
            'misses' => $shotCount - $hits,
        ];
        if ($workingSP !== null) $response['finalSP'] = $workingSP;
        break;

    case 'burst':
        $burstCount = array_key_exists('bursts', $input) ? (int)$input['bursts'] : 0;
        if ($burstCount < 1) {
            http_response_code(400);
            echo json_encode(['error' => '"bursts" must be a positive integer for burst mode.']);
            exit;
        }

        $workingSP    = $armorSP;
        $bursts       = [];
        $hits         = 0;
        $totalBullets = 0;

        for ($i = 0; $i < $burstCount; $i++) {
            $result  = processBurst($skill, $difficulty, $damage);
            $bullets = [];

            foreach ($result['hits'] as $bullet) {
                $rawDmg    = $bullet['damage']['total'];
                $loc       = $bullet['location']['location'];
                $armorInfo = ($workingSP !== null) ? applyArmorToHit($loc, $rawDmg, $workingSP) : null;
                $bullets[] = [
                    'location'  => $loc,
                    'rawDamage' => $rawDmg,
                    'armor'     => $armorInfo,
                ];
            }

            $bursts[] = [
                'num'         => $i + 1,
                'hit'         => $result['hit'],
                'skillRoll'   => $result['skillRoll'],
                'total'       => $result['total'],
                'bulletCount' => $result['bulletCount'],
                'bullets'     => $bullets,
            ];

            if ($result['hit']) {
                $hits++;
                $totalBullets += $result['bulletCount'];
            }
        }

        $params['bursts'] = $burstCount;
        $response = [
            'mode'         => 'burst',
            'params'       => $params,
            'bursts'       => $bursts,
            'hits'         => $hits,
            'misses'       => $burstCount - $hits,
            'totalBullets' => $totalBullets,
        ];
        if ($workingSP !== null) $response['finalSP'] = $workingSP;
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Mode "' . htmlspecialchars($mode) . '" is not supported.']);
        exit;
}

// Persist the event to the shared fire log.
// Wrapped in try/catch so a missing or misconfigured DB never breaks the fire button.
try {
    require_once __DIR__ . '/../src/db.php';
    $response['eventId'] = saveFireEvent($response);
} catch (Throwable $_) {
    $response['eventId'] = null;
}

echo json_encode($response);

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Format a processShot() result for the JSON response.
 */
function formatShot(array $shot, int $num, ?array $armorInfo = null): array
{
    return [
        'num'       => $num,
        'hit'       => $shot['hit'],
        'skillRoll' => $shot['skillRoll'],
        'total'     => $shot['total'],
        'location'  => $shot['hit'] ? $shot['location']['location'] : null,
        'rawDamage' => $shot['hit'] ? $shot['damage']['total']      : null,
        'armor'     => $armorInfo,
    ];
}

/**
 * Apply armor to a single bullet/shot hit, mutating $workingSP in place.
 */
function applyArmorToHit(string $location, int $rawDamage, ?array &$workingSP): ?array
{
    if ($workingSP === null) return null;
    $locKey             = locationKey($location);
    $locSP              = (int)($workingSP[$locKey] ?? 0);
    $result             = applyDamage($rawDamage, $locSP);
    $workingSP[$locKey] = $result['newSP'];

    return [
        'spBefore'    => $locSP,
        'passthrough' => $result['passthrough'],
        'spAfter'     => $result['newSP'],
        'penetrated'  => $result['penetrated'],
    ];
}

/**
 * INSERT a fire event into the shared log and return its new ID.
 * Old events (> 15 min) are pruned on each insert.
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
