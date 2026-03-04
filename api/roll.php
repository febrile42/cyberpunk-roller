<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../src/dice.php';
require_once __DIR__ . '/../src/combat.php';
require_once __DIR__ . '/../src/armor.php';
require_once __DIR__ . '/../src/fire_log.php';
require_once __DIR__ . '/../src/rate_limit.php';

enforceRateLimit(clientIp());

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
$targetName = isset($input['targetName']) ? trim((string)$input['targetName']) : null;
if ($targetName !== null && strlen($targetName) > 100) {
    $targetName = substr($targetName, 0, 100);
}

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
if ($targetName !== null && $targetName !== '') {
    $params['targetName'] = $targetName;
}

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
        if ($shotCount < 1 || $shotCount > 200) {
            http_response_code(400);
            echo json_encode(['error' => '"shots" must be between 1 and 200.']);
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
        if ($burstCount < 1 || $burstCount > 10) {
            http_response_code(400);
            echo json_encode(['error' => '"bursts" must be between 1 and 10.']);
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

// Helper functions formatShot(), applyArmorToHit(), and saveFireEvent()
// are defined in src/combat.php, src/armor.php, and src/fire_log.php respectively.
