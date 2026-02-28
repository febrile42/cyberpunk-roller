<?php

require_once __DIR__ . '/../src/combat.php';

// --- evaluateHit: boundary conditions ---

assert_true(evaluateHit(18, 15),  'evaluateHit: total > difficulty = hit');
assert_true(evaluateHit(18, 18),  'evaluateHit: total == difficulty = hit');
assert_false(evaluateHit(17, 18), 'evaluateHit: total < difficulty = miss');
assert_false(evaluateHit(0, 1),   'evaluateHit: 0 vs 1 = miss');
assert_true(evaluateHit(1, 1),    'evaluateHit: 1 vs 1 = hit');

// --- processShot: miss path ---
// skillCheck returns 3, total = 3 + 10 = 13, difficulty = 20 => miss

$result = processShot(10, 20, '2D6', fn($a, $b) => 3);
assert_false($result['hit'],          'processShot miss: hit=false');
assert_equal($result['total'], 13,    'processShot miss: total is skill(10) + roll(3)');
assert_equal($result['location'], null, 'processShot miss: location is null');
assert_equal($result['damage'], null,   'processShot miss: damage is null');

// --- processShot: hit path ---
// skillCheck returns 8, total = 8 + 15 = 23, difficulty = 20 => hit
// Location roll = 1 (Head), damage roll = 3 (each die)

$seq = [8, 1, 3, 3]; // skillCheck, location, damage die 1, damage die 2
$i = 0;
$seqRoller = function($min, $max) use (&$seq, &$i) { return $seq[$i++]; };

$result = processShot(15, 20, '2D6', $seqRoller);
assert_true($result['hit'],                       'processShot hit: hit=true');
assert_equal($result['total'], 23,                'processShot hit: total = 15 + 8');
assert_equal($result['location']['location'], 'Head', 'processShot hit: location resolved');
assert_equal($result['damage']['total'], 6,       'processShot hit: damage = 3+3');

// --- processShot: critical success into hit ---
// Rolls: 10 (crit trigger), 5 (crit bonus) = skillCheck total 15
// total = 15 + 10 = 25, difficulty = 20 => hit
// Location roll = 3 (Torso), damage die = 4

$seq2 = [10, 5, 3, 4];
$j = 0;
$seqRoller2 = function($min, $max) use (&$seq2, &$j) { return $seq2[$j++]; };

$result = processShot(10, 20, '1D6', $seqRoller2);
assert_true($result['hit'],                         'processShot critical hit: hit=true');
assert_true($result['skillRoll']['critical'],        'processShot critical hit: skillRoll.critical=true');
assert_equal($result['total'], 25,                   'processShot critical hit: total = 10 + 15');
assert_equal($result['location']['location'], 'Torso', 'processShot critical hit: location=Torso');
assert_equal($result['damage']['total'], 4,          'processShot critical hit: damage=4');

// --- processBurst: miss path ---

$result = processBurst(5, 25, '1D6', fn($a, $b) => 2);
// skillCheck = 2, total = 7, difficulty = 25 => miss
assert_false($result['hit'],            'processBurst miss: hit=false');
assert_equal($result['bulletCount'], null, 'processBurst miss: bulletCount=null');
assert_equal($result['hits'], [],       'processBurst miss: hits=[]');

// --- processBurst: hit path with 2 bullets ---
// skillCheck=9, total=9+15=24 >= 20 => hit; bullet roll=2; two hits
// Hit 1: location=5 (Right Arm), damage=4
// Hit 2: location=9 (Left Leg), damage=6

$seq3 = [9, 2, 5, 4, 9, 6];
$k = 0;
$seqRoller3 = function($min, $max) use (&$seq3, &$k) { return $seq3[$k++]; };

$result = processBurst(15, 20, '1D6', $seqRoller3);
assert_true($result['hit'],             'processBurst hit: hit=true');
assert_equal($result['bulletCount'], 2, 'processBurst hit: bulletCount=2');
assert_equal(count($result['hits']), 2, 'processBurst hit: 2 hits returned');
assert_equal($result['hits'][0]['location']['location'], 'Right Arm', 'processBurst hit 1: Right Arm');
assert_equal($result['hits'][0]['damage']['total'], 4,               'processBurst hit 1: damage=4');
assert_equal($result['hits'][1]['location']['location'], 'Left Leg', 'processBurst hit 2: Left Leg');
assert_equal($result['hits'][1]['damage']['total'], 6,               'processBurst hit 2: damage=6');
