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
        echo json_encode($response);
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
        echo json_encode($response);
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
        echo json_encode($response);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Mode "' . htmlspecialchars($mode) . '" is not supported.']);
        exit;
}

/**
 * Format a processShot() result for the JSON response.
 * rawDamage is the roll total before armor. armor is null when no SP was tracked.
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
 * Apply armor to a single bullet hit, mutating $workingSP in place.
 * Returns the armor interaction details.
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
