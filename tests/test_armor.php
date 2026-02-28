<?php

require_once __DIR__ . '/../src/armor.php';

// --- applyDamage: fully blocked (damage < SP) ---

$result = applyDamage(8, 12);
assert_equal($result['passthrough'], 0,   'applyDamage blocked: passthrough=0');
assert_equal($result['newSP'], 12,        'applyDamage blocked: SP unchanged');
assert_false($result['penetrated'],       'applyDamage blocked: penetrated=false');

// --- applyDamage: exactly equal (damage == SP) — still fully blocked ---

$result = applyDamage(12, 12);
assert_equal($result['passthrough'], 0,   'applyDamage equal: passthrough=0');
assert_equal($result['newSP'], 12,        'applyDamage equal: SP unchanged');
assert_false($result['penetrated'],       'applyDamage equal: penetrated=false');

// --- applyDamage: penetrating hit (damage > SP) ---
// Per spec: passthrough = damage - SP; SP reduced by 1

$result = applyDamage(15, 12);
assert_equal($result['passthrough'], 3,   'applyDamage penetrating: passthrough=3 (15-12)');
assert_equal($result['newSP'], 11,        'applyDamage penetrating: SP reduced to 11');
assert_true($result['penetrated'],        'applyDamage penetrating: penetrated=true');

// --- applyDamage: large damage vs small SP ---

$result = applyDamage(20, 3);
assert_equal($result['passthrough'], 17,  'applyDamage large: passthrough=17 (20-3)');
assert_equal($result['newSP'], 2,         'applyDamage large: SP reduced to 2');
assert_true($result['penetrated'],        'applyDamage large: penetrated=true');

// --- applyDamage: SP already 0 (no armor) ---
// All damage passes through; SP stays at 0, no further degradation

$result = applyDamage(10, 0);
assert_equal($result['passthrough'], 10,  'applyDamage no armor: all 10 damage passes through');
assert_equal($result['newSP'], 0,         'applyDamage no armor: SP stays at 0');
assert_true($result['penetrated'],        'applyDamage no armor: penetrated=true');

// --- applyDamage: SP would drop to 1 (from 2, penetrating) ---

$result = applyDamage(5, 2);
assert_equal($result['passthrough'], 3,   'applyDamage SP=2: passthrough=3');
assert_equal($result['newSP'], 1,         'applyDamage SP=2: SP reduced to 1');

// --- locationKey: all mappings ---

assert_equal(locationKey('Head'),      'head',     'locationKey: Head');
assert_equal(locationKey('Torso'),     'torso',    'locationKey: Torso');
assert_equal(locationKey('Right Arm'), 'rightArm', 'locationKey: Right Arm');
assert_equal(locationKey('Left Arm'),  'leftArm',  'locationKey: Left Arm');
assert_equal(locationKey('Right Leg'), 'rightLeg', 'locationKey: Right Leg');
assert_equal(locationKey('Left Leg'),  'leftLeg',  'locationKey: Left Leg');

$threw = false;
try {
    locationKey('Neck');
} catch (InvalidArgumentException $e) {
    $threw = true;
}
assert_true($threw, 'locationKey: unknown location throws');

// --- defaultSP ---

$sp = defaultSP(5);
assert_equal($sp['head'],     5, 'defaultSP: head=5');
assert_equal($sp['torso'],    5, 'defaultSP: torso=5');
assert_equal($sp['rightArm'], 5, 'defaultSP: rightArm=5');
assert_equal($sp['leftArm'],  5, 'defaultSP: leftArm=5');
assert_equal($sp['rightLeg'], 5, 'defaultSP: rightLeg=5');
assert_equal($sp['leftLeg'],  5, 'defaultSP: leftLeg=5');

$sp0 = defaultSP();
assert_equal($sp0['torso'], 0, 'defaultSP: defaults to 0');
