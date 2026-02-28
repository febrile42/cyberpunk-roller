<?php

require_once __DIR__ . '/dice.php';

/**
 * Determine whether a to-hit roll meets or exceeds the difficulty.
 *
 * @param int $total       The combined roll (skill check total + base skill)
 * @param int $difficulty  The target number to meet or beat
 * @return bool
 */
function evaluateHit(int $total, int $difficulty): bool
{
    return $total >= $difficulty;
}

/**
 * Resolve a single shot attempt.
 *
 * Rolls a skill check, adds the base skill value, evaluates against difficulty.
 * On a hit, also rolls location and damage.
 *
 * @param int      $skill           Base to-hit skill value
 * @param int      $difficulty      Difficulty score needed to hit
 * @param string   $damageNotation  Dice notation for damage, e.g. "3D6+4"
 * @param callable $roller          Optional injectable RNG (for testing)
 * @return array {
 *   hit:       bool
 *   skillRoll: array   Output of skillCheck()
 *   total:     int     skillRoll.total + skill
 *   location:  array|null  Output of rollLocation(), null on miss
 *   damage:    array|null  Output of rollDice(), null on miss
 * }
 */
function processShot(
    int $skill,
    int $difficulty,
    string $damageNotation,
    ?callable $roller = null
): array {
    $skillRoll = skillCheck($roller);
    $total     = $skillRoll['total'] + $skill;
    $hit       = evaluateHit($total, $difficulty);

    if (!$hit) {
        return [
            'hit'       => false,
            'skillRoll' => $skillRoll,
            'total'     => $total,
            'location'  => null,
            'damage'    => null,
        ];
    }

    $location = rollLocation($roller);
    $damage   = rollDice($damageNotation, $roller);

    return [
        'hit'       => true,
        'skillRoll' => $skillRoll,
        'total'     => $total,
        'location'  => $location,
        'damage'    => $damage,
    ];
}

/**
 * Resolve a 3-round burst attempt.
 *
 * One to-hit roll; on success, roll 1D3 for bullet count, then resolve
 * each bullet with location and damage.
 *
 * @param int      $skill           Base to-hit skill value
 * @param int      $difficulty      Difficulty score needed to hit
 * @param string   $damageNotation  Dice notation for damage per bullet
 * @param callable $roller          Optional injectable RNG (for testing)
 * @return array {
 *   hit:      bool
 *   skillRoll: array
 *   total:    int
 *   bulletCount: int|null   1D3 result on success, null on miss
 *   hits:     array[]       Per-bullet {location, damage} on success, [] on miss
 * }
 */
function processBurst(
    int $skill,
    int $difficulty,
    string $damageNotation,
    ?callable $roller = null
): array {
    if ($roller === null) {
        $roller = 'random_int';
    }

    $skillRoll = skillCheck($roller);
    $total     = $skillRoll['total'] + $skill;
    $hit       = evaluateHit($total, $difficulty);

    if (!$hit) {
        return [
            'hit'         => false,
            'skillRoll'   => $skillRoll,
            'total'       => $total,
            'bulletCount' => null,
            'hits'        => [],
        ];
    }

    $bulletCount = $roller(1, 3);
    $hits = [];
    for ($i = 0; $i < $bulletCount; $i++) {
        $hits[] = [
            'location' => rollLocation($roller),
            'damage'   => rollDice($damageNotation, $roller),
        ];
    }

    return [
        'hit'         => true,
        'skillRoll'   => $skillRoll,
        'total'       => $total,
        'bulletCount' => $bulletCount,
        'hits'        => $hits,
    ];
}
