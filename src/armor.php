<?php

/**
 * Apply damage to an armor location.
 *
 * Damage is compared against Stopping Power (SP). Any damage exceeding SP
 * passes through. If damage penetrates (exceeds SP), the SP is reduced by 1.
 *
 * SP of 0 means no armor — all damage passes through, SP stays at 0.
 *
 * @param int $damage  Raw damage from a successful hit
 * @param int $sp      Current Stopping Power of the hit location
 * @return array {
 *   passthrough: int   Damage delivered to the target (0 if blocked)
 *   newSP:       int   SP value after this hit
 *   penetrated:  bool  Whether the armor was penetrated
 * }
 */
function applyDamage(int $damage, int $sp): array
{
    if ($sp <= 0 || $damage > $sp) {
        $passthrough = max(0, $damage - $sp);
        $newSP       = max(0, $sp - ($damage > $sp ? 1 : 0));
        $penetrated  = true;

        // If SP was already 0, no degradation occurs (nothing to degrade)
        if ($sp <= 0) {
            $newSP      = 0;
            $penetrated = true;
        }

        return [
            'passthrough' => $passthrough,
            'newSP'       => $newSP,
            'penetrated'  => $penetrated,
        ];
    }

    // Damage does not exceed SP — fully blocked
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
