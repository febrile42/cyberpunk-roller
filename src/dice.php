<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0

/**
 * Parse and evaluate a dice notation string.
 *
 * Supports: xDn, xDn+mod, xDn-mod (case-insensitive)
 * e.g. "3D6+4", "D10", "2d8-1"
 *
 * @param string   $notation  Dice notation string
 * @param callable $roller    Optional injectable RNG: fn(int $min, int $max): int
 *                            Defaults to random_int(). Used for testing.
 * @return array {
 *   rolls:    int[]   Individual die results
 *   modifier: int     Flat modifier (0 if none)
 *   total:    int     Sum of rolls + modifier
 * }
 */
function rollDice(string $notation, ?callable $roller = null): array
{
    if ($roller === null) {
        $roller = 'random_int';
    }

    // Parse: optional count, D, sides, optional +/- modifier
    if (!preg_match('/^(\d+)?[Dd](\d+)([+-]\d+)?$/', trim($notation), $m)) {
        throw new InvalidArgumentException("Invalid dice notation: $notation");
    }

    $count    = isset($m[1]) && $m[1] !== '' ? (int)$m[1] : 1;
    $sides    = (int)$m[2];
    $modifier = isset($m[3]) && $m[3] !== '' ? (int)$m[3] : 0;

    if ($count < 1 || $sides < 1) {
        throw new InvalidArgumentException("Dice count and sides must be >= 1: $notation");
    }

    $rolls = [];
    for ($i = 0; $i < $count; $i++) {
        $rolls[] = $roller(1, $sides);
    }

    $total = array_sum($rolls) + $modifier;

    return [
        'rolls'    => $rolls,
        'modifier' => $modifier,
        'total'    => $total,
    ];
}

/**
 * Perform a skill check: roll 1D10 with critical success rule.
 *
 * If a 10 is rolled, roll an additional value 0-9 and add it.
 *
 * @param callable $roller  Optional injectable RNG (for testing)
 * @return array {
 *   rolls:    int[]   All individual rolls made (1 normally, 2 on critical)
 *   total:    int     Final total (10-19 on critical success)
 *   critical: bool    Whether a critical success occurred
 * }
 */
function skillCheck(?callable $roller = null): array
{
    if ($roller === null) {
        $roller = 'random_int';
    }

    $first = $roller(1, 10);
    $rolls = [$first];

    if ($first === 10) {
        $bonus = $roller(0, 9);
        $rolls[] = $bonus;
        return [
            'rolls'    => $rolls,
            'total'    => 10 + $bonus,
            'critical' => true,
        ];
    }

    return [
        'rolls'    => $rolls,
        'total'    => $first,
        'critical' => false,
    ];
}

/**
 * Roll a D10 and map to a hit location.
 *
 * 1=Head, 2-4=Torso, 5=Right Arm, 6=Left Arm, 7-8=Right Leg, 9-10=Left Leg
 *
 * @param callable $roller  Optional injectable RNG (for testing)
 * @return array {
 *   roll:     int     The raw D10 result
 *   location: string  Location name
 * }
 */
function rollLocation(?callable $roller = null): array
{
    if ($roller === null) {
        $roller = 'random_int';
    }

    $roll = $roller(1, 10);
    $location = locationFromRoll($roll);

    return [
        'roll'     => $roll,
        'location' => $location,
    ];
}

/**
 * Map a D10 value to a hit location string.
 * Separated so tests can verify the mapping directly without RNG.
 */
function locationFromRoll(int $roll): string
{
    return match(true) {
        $roll === 1        => 'Head',
        $roll <= 4         => 'Torso',
        $roll === 5        => 'Right Arm',
        $roll === 6        => 'Left Arm',
        $roll <= 8         => 'Right Leg',
        default            => 'Left Leg',
    };
}
