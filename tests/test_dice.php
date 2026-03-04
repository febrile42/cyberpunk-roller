<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0

require_once __DIR__ . '/../src/dice.php';

// --- rollDice: notation parsing and structure ---

$result = rollDice('3D6', fn($a, $b) => 3);
assert_equal(count($result['rolls']), 3, 'rollDice 3D6: produces 3 rolls');
assert_equal($result['modifier'], 0, 'rollDice 3D6: modifier is 0');
assert_equal($result['total'], 9, 'rollDice 3D6: total is 9 (3+3+3)');

$result = rollDice('3D6+4', fn($a, $b) => 3);
assert_equal($result['modifier'], 4, 'rollDice 3D6+4: modifier is 4');
assert_equal($result['total'], 13, 'rollDice 3D6+4: total is 13 (9+4)');

$result = rollDice('3D6-2', fn($a, $b) => 3);
assert_equal($result['modifier'], -2, 'rollDice 3D6-2: modifier is -2');
assert_equal($result['total'], 7, 'rollDice 3D6-2: total is 7 (9-2)');

$result = rollDice('D10', fn($a, $b) => 7);
assert_equal(count($result['rolls']), 1, 'rollDice D10 (no count): produces 1 roll');
assert_equal($result['total'], 7, 'rollDice D10: total is 7');

$result = rollDice('1d6', fn($a, $b) => 5);
assert_equal($result['total'], 5, 'rollDice lowercase 1d6: works');

// Invalid notation throws
$threw = false;
try {
    rollDice('invalid');
} catch (InvalidArgumentException $e) {
    $threw = true;
}
assert_true($threw, 'rollDice invalid notation: throws InvalidArgumentException');

// --- skillCheck: normal roll ---

$result = skillCheck(fn($a, $b) => 7);
assert_equal($result['total'], 7, 'skillCheck: total matches roll on normal result');
assert_false($result['critical'], 'skillCheck: no critical on roll < 10');
assert_equal(count($result['rolls']), 1, 'skillCheck: only 1 roll on normal result');

// --- skillCheck: critical success (roll of 10) ---

$seq = [10, 5];
$i = 0;
$seqRoller = function($min, $max) use (&$seq, &$i) { return $seq[$i++]; };

$result = skillCheck($seqRoller);
assert_true($result['critical'], 'skillCheck: critical=true when first roll is 10');
assert_equal($result['total'], 15, 'skillCheck: total is 15 (10+5) on critical');
assert_equal(count($result['rolls']), 2, 'skillCheck: 2 rolls recorded on critical');
assert_equal($result['rolls'][0], 10, 'skillCheck: first roll is 10');
assert_equal($result['rolls'][1], 5, 'skillCheck: second roll is bonus');

// Critical with bonus 0 (minimum)
$seq2 = [10, 0];
$j = 0;
$seqRoller2 = function($min, $max) use (&$seq2, &$j) { return $seq2[$j++]; };
$result = skillCheck($seqRoller2);
assert_equal($result['total'], 10, 'skillCheck: critical + 0 bonus = total 10');

// Critical with bonus 9 (maximum)
$seq3 = [10, 9];
$k = 0;
$seqRoller3 = function($min, $max) use (&$seq3, &$k) { return $seq3[$k++]; };
$result = skillCheck($seqRoller3);
assert_equal($result['total'], 19, 'skillCheck: critical + 9 bonus = total 19');

// --- locationFromRoll: full mapping ---

assert_equal(locationFromRoll(1),  'Head',      'locationFromRoll: 1=Head');
assert_equal(locationFromRoll(2),  'Torso',     'locationFromRoll: 2=Torso');
assert_equal(locationFromRoll(3),  'Torso',     'locationFromRoll: 3=Torso');
assert_equal(locationFromRoll(4),  'Torso',     'locationFromRoll: 4=Torso');
assert_equal(locationFromRoll(5),  'Right Arm', 'locationFromRoll: 5=Right Arm');
assert_equal(locationFromRoll(6),  'Left Arm',  'locationFromRoll: 6=Left Arm');
assert_equal(locationFromRoll(7),  'Right Leg', 'locationFromRoll: 7=Right Leg');
assert_equal(locationFromRoll(8),  'Right Leg', 'locationFromRoll: 8=Right Leg');
assert_equal(locationFromRoll(9),  'Left Leg',  'locationFromRoll: 9=Left Leg');
assert_equal(locationFromRoll(10), 'Left Leg',  'locationFromRoll: 10=Left Leg');

// --- rollLocation: structure ---

$result = rollLocation(fn($a, $b) => 1);
assert_equal($result['roll'], 1,      'rollLocation: roll field returned');
assert_equal($result['location'], 'Head', 'rollLocation: location resolved from roll');
