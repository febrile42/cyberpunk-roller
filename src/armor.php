<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0

/**
 * Apply damage to an armor location.
 *
 * Damage is compared against Stopping Power (SP). Any damage exceeding SP
 * passes through. If damage penetrates (exceeds SP), the SP is reduced by 1.
 *
 * SP of 0 means no armor — all damage passes through, SP stays at 0.
 *
 * $apMode controls the effective SP used for the penetration check:
 *   'reg'     — use full SP (standard rules)
 *   'ap'      — use ceil(SP/2) as the penetration threshold
 *   'quarter' — use ceil(SP/4) as the penetration threshold
 * SP degradation always applies to the real SP value.
 *
 * @param int    $damage  Raw damage from a successful hit
 * @param int    $sp      Current Stopping Power of the hit location
 * @param string $apMode  Armor-piercing mode: 'reg' | 'ap' | 'quarter'
 * @return array {
 *   passthrough: int   Damage delivered to the target (0 if blocked)
 *   newSP:       int   SP value after this hit
 *   penetrated:  bool  Whether the armor was penetrated
 * }
 */
function applyDamage(int $damage, int $sp, string $apMode = 'reg'): array
{
    $effectiveSP = match($apMode) {
        'ap'      => (int)ceil($sp / 2),
        'quarter' => (int)ceil($sp / 4),
        default   => $sp,
    };

    if ($effectiveSP <= 0 || $damage > $effectiveSP) {
        $passthrough = max(0, $damage - $effectiveSP);
        $penetrated  = true;

        // If real SP was already 0, no degradation occurs (nothing to degrade)
        if ($sp <= 0) {
            $newSP = 0;
        } else {
            $newSP = max(0, $sp - 1);
        }

        return [
            'passthrough' => $passthrough,
            'newSP'       => $newSP,
            'penetrated'  => $penetrated,
        ];
    }

    // Damage does not exceed effective SP — fully blocked
    return [
        'passthrough' => 0,
        'newSP'       => $sp,
        'penetrated'  => false,
    ];
}

/**
 * Return the location key used in SP arrays from a location name string.
 * e.g. "Right Arm" -> "rightArm"
 */
function locationKey(string $location): string
{
    return match($location) {
        'Head'      => 'head',
        'Torso'     => 'torso',
        'Right Arm' => 'rightArm',
        'Left Arm'  => 'leftArm',
        'Right Leg' => 'rightLeg',
        'Left Leg'  => 'leftLeg',
        default     => throw new InvalidArgumentException("Unknown location: $location"),
    };
}

/**
 * Return an SP array with all locations set to a given value.
 */
function defaultSP(int $value = 0): array
{
    return [
        'head'     => $value,
        'torso'    => $value,
        'rightArm' => $value,
        'leftArm'  => $value,
        'rightLeg' => $value,
        'leftLeg'  => $value,
    ];
}

/**
 * Apply armor to a single bullet/shot hit, mutating $workingSP in place.
 *
 * @param string   $location   Location name, e.g. "Torso"
 * @param int      $rawDamage  Damage value before armor
 * @param array   &$workingSP  Current SP array; mutated on penetration
 * @return array {
 *   spBefore:    int   SP before this hit
 *   passthrough: int   Damage that passed through armor
 *   spAfter:     int   SP after this hit
 *   penetrated:  bool  Whether armor was penetrated
 * }
 */
function applyArmorToHit(string $location, int $rawDamage, ?array &$workingSP, string $apMode = 'reg'): ?array
{
    if ($workingSP === null) return null;
    $locKey             = locationKey($location);
    $locSP              = (int)($workingSP[$locKey] ?? 0);
    $result             = applyDamage($rawDamage, $locSP, $apMode);
    $workingSP[$locKey] = $result['newSP'];

    return [
        'spBefore'    => $locSP,
        'passthrough' => $result['passthrough'],
        'spAfter'     => $result['newSP'],
        'penetrated'  => $result['penetrated'],
    ];
}
