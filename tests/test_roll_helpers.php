<?php

/**
 * Tests for applyArmorToHit() (src/armor.php) and formatShot() (src/combat.php).
 */

require_once __DIR__ . '/../src/armor.php';
require_once __DIR__ . '/../src/dice.php';
require_once __DIR__ . '/../src/combat.php';

// ── applyArmorToHit ───────────────────────────────────────────────────────────

$sp = defaultSP(10);
$result = applyArmorToHit('Torso', 5, $sp);
assert_equal($result['passthrough'], 0,    'applyArmorToHit: torso dmg 5 vs SP 10 → passthrough 0');
assert_equal($result['penetrated'],  false, 'applyArmorToHit: torso dmg 5 vs SP 10 → not penetrated');
assert_equal($sp['torso'],           10,    'applyArmorToHit: torso dmg 5 vs SP 10 → SP unchanged');

$sp = defaultSP(10);
$result = applyArmorToHit('Torso', 10, $sp);
assert_equal($result['passthrough'], 0,    'applyArmorToHit: torso dmg 10 vs SP 10 → passthrough 0 (equal = blocked)');
assert_equal($result['penetrated'],  false, 'applyArmorToHit: torso dmg 10 vs SP 10 → not penetrated');
assert_equal($sp['torso'],           10,    'applyArmorToHit: torso dmg 10 vs SP 10 → SP unchanged');

$sp = defaultSP(10);
$result = applyArmorToHit('Torso', 11, $sp);
assert_equal($result['passthrough'], 1,    'applyArmorToHit: torso dmg 11 vs SP 10 → passthrough 1');
assert_equal($result['penetrated'],  true,  'applyArmorToHit: torso dmg 11 vs SP 10 → penetrated');
assert_equal($result['spBefore'],    10,    'applyArmorToHit: torso dmg 11 → spBefore is 10');
assert_equal($result['spAfter'],     9,     'applyArmorToHit: torso dmg 11 → spAfter is 9');
assert_equal($sp['torso'],           9,     'applyArmorToHit: torso dmg 11 → workingSP mutated to 9');

$sp = defaultSP(10);
$result = applyArmorToHit('Head', 15, $sp);
assert_equal($result['passthrough'], 5,    'applyArmorToHit: head dmg 15 vs SP 10 → passthrough 5');
assert_equal($result['penetrated'],  true,  'applyArmorToHit: head dmg 15 vs SP 10 → penetrated');
assert_equal($sp['head'],            9,     'applyArmorToHit: head dmg 15 → head SP drops to 9');
assert_equal($sp['torso'],           10,    'applyArmorToHit: head hit → torso SP unchanged');

// SP already 0 on location — all damage passes through, SP stays 0
$sp = defaultSP(0);
$result = applyArmorToHit('Left Arm', 8, $sp);
assert_equal($result['passthrough'], 8,    'applyArmorToHit: leftArm dmg 8 vs SP 0 → passthrough 8');
assert_equal($result['penetrated'],  true,  'applyArmorToHit: leftArm dmg 8 vs SP 0 → penetrated');
assert_equal($sp['leftArm'],         0,     'applyArmorToHit: leftArm SP 0 → stays at 0');

// Returned null when workingSP is null
$nullSP = null;
$result = applyArmorToHit('Torso', 10, $nullSP);
assert_equal($result, null, 'applyArmorToHit: null workingSP → returns null');

// ── formatShot ────────────────────────────────────────────────────────────────

// Miss
$missShot = [
    'hit'       => false,
    'skillRoll' => ['rolls' => [7], 'total' => 7, 'critical' => false],
    'total'     => 17,
    'location'  => null,
    'damage'    => null,
];
$formatted = formatShot($missShot, 1);
assert_equal($formatted['num'],       1,     'formatShot miss: num is 1');
assert_equal($formatted['hit'],       false, 'formatShot miss: hit is false');
assert_equal($formatted['total'],     17,    'formatShot miss: total is 17');
assert_equal($formatted['location'],  null,  'formatShot miss: location is null');
assert_equal($formatted['rawDamage'], null,  'formatShot miss: rawDamage is null');
assert_equal($formatted['armor'],     null,  'formatShot miss: armor is null');

// Hit, no armorInfo
$hitShot = [
    'hit'       => true,
    'skillRoll' => ['rolls' => [9], 'total' => 9, 'critical' => false],
    'total'     => 19,
    'location'  => ['location' => 'Torso', 'roll' => 5],
    'damage'    => ['rolls' => [4, 3], 'total' => 7],
];
$formatted = formatShot($hitShot, 2);
assert_equal($formatted['hit'],       true,   'formatShot hit no armor: hit is true');
assert_equal($formatted['location'],  'Torso','formatShot hit no armor: location is Torso');
assert_equal($formatted['rawDamage'], 7,      'formatShot hit no armor: rawDamage is 7');
assert_equal($formatted['armor'],     null,   'formatShot hit no armor: armor is null');

// Hit, with armorInfo
$armorInfo = ['spBefore' => 10, 'passthrough' => 2, 'spAfter' => 9, 'penetrated' => true];
$formatted = formatShot($hitShot, 3, $armorInfo);
assert_equal($formatted['armor'], $armorInfo, 'formatShot hit with armor: armor matches passed info');
assert_equal($formatted['num'],   3,          'formatShot hit with armor: num is 3');
