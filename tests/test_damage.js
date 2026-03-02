/**
 * Tests for computeRunDamage() — inlined from js/app.js.
 * Run with: node tests/test_damage.js
 * No dependencies required.
 */

'use strict';

// ── Inline the functions under test (verbatim from js/app.js) ────────────────

const LOCATION_KEY_MAP = {
  'Head':      'head',
  'Torso':     'torso',
  'Right Arm': 'rightArm',
  'Left Arm':  'leftArm',
  'Right Leg': 'rightLeg',
  'Left Leg':  'leftLeg',
};

function defaultDamage() {
  return { head: 0, torso: 0, rightArm: 0, leftArm: 0, rightLeg: 0, leftLeg: 0 };
}

function computeRunDamage(data) {
  const runDmg = defaultDamage();

  function tally(shot) {
    if (shot.hit === false || !shot.location) return;
    const key = LOCATION_KEY_MAP[shot.location];
    if (!key) return;
    if (shot.armor) {
      if (shot.armor.penetrated) runDmg[key] += shot.armor.passthrough;
    } else {
      runDmg[key] += shot.rawDamage || 0;
    }
  }

  if (data.mode === 'single' || data.mode === 'auto') {
    (data.shots  || []).forEach(tally);
  } else if (data.mode === 'burst') {
    (data.bursts || []).forEach(burst => (burst.bullets || []).forEach(tally));
  }

  return runDmg;
}

// ── Test runner ───────────────────────────────────────────────────────────────

let passed = 0;
let failed = 0;

function assertEqual(actual, expected, label) {
  if (actual === expected) {
    console.log(`  [PASS] ${label}`);
    passed++;
  } else {
    console.log(`  [FAIL] ${label}`);
    console.log(`         Expected: ${JSON.stringify(expected)}`);
    console.log(`         Got:      ${JSON.stringify(actual)}`);
    failed++;
  }
}

function assertDeepEqual(actual, expected, label) {
  const a = JSON.stringify(actual);
  const e = JSON.stringify(expected);
  if (a === e) {
    console.log(`  [PASS] ${label}`);
    passed++;
  } else {
    console.log(`  [FAIL] ${label}`);
    console.log(`         Expected: ${e}`);
    console.log(`         Got:      ${a}`);
    failed++;
  }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

// Single mode — hit with armor penetrated
let result = computeRunDamage({
  mode: 'single',
  shots: [{ hit: true, location: 'Torso', rawDamage: 12, armor: { penetrated: true, passthrough: 5 } }],
});
assertEqual(result.torso, 5, 'single hit armor penetrated: torso gets passthrough 5');
assertDeepEqual({ ...result, torso: 0 }, defaultDamage(), 'single hit armor penetrated: all other locations 0');

// Single mode — hit with armor that did NOT penetrate
result = computeRunDamage({
  mode: 'single',
  shots: [{ hit: true, location: 'Head', rawDamage: 8, armor: { penetrated: false, passthrough: 0 } }],
});
assertDeepEqual(result, defaultDamage(), 'single hit armor not penetrated: no damage tallied');

// Single mode — hit with no armor
result = computeRunDamage({
  mode: 'single',
  shots: [{ hit: true, location: 'Right Arm', rawDamage: 7, armor: null }],
});
assertEqual(result.rightArm, 7, 'single hit no armor: rightArm gets rawDamage 7');

// Single mode — miss (hit === false)
result = computeRunDamage({
  mode: 'single',
  shots: [{ hit: false, location: null, rawDamage: null, armor: null }],
});
assertDeepEqual(result, defaultDamage(), 'single miss: nothing tallied');

// Auto mode — multiple shots accumulate across locations
result = computeRunDamage({
  mode: 'auto',
  shots: [
    { hit: true,  location: 'Torso',     rawDamage: 10, armor: { penetrated: true,  passthrough: 4 } },
    { hit: false, location: null,         rawDamage: null, armor: null },
    { hit: true,  location: 'Torso',     rawDamage: 9,  armor: { penetrated: true,  passthrough: 3 } },
    { hit: true,  location: 'Left Leg',  rawDamage: 6,  armor: null },
  ],
});
assertEqual(result.torso,   7, 'auto multi-shot: torso accumulates 4+3');
assertEqual(result.leftLeg, 6, 'auto multi-shot: leftLeg gets rawDamage 6');
assertEqual(result.head,    0, 'auto multi-shot: head stays 0');

// Burst mode — bullets are iterated per burst
result = computeRunDamage({
  mode: 'burst',
  bursts: [
    {
      hit: true,
      bullets: [
        { location: 'Torso',    rawDamage: 8, armor: { penetrated: true,  passthrough: 3 } },
        { location: 'Left Arm', rawDamage: 6, armor: { penetrated: false, passthrough: 0 } },
      ],
    },
    {
      hit: true,
      bullets: [
        { location: 'Head', rawDamage: 10, armor: null },
      ],
    },
  ],
});
assertEqual(result.torso,   3, 'burst: torso gets passthrough 3');
assertEqual(result.leftArm, 0, 'burst: leftArm armor blocked, 0 damage');
assertEqual(result.head,    10,'burst: head no armor, gets rawDamage 10');

// Burst mode — bullet with undefined hit field (burst bullets have no hit key)
result = computeRunDamage({
  mode: 'burst',
  bursts: [
    {
      bullets: [
        { location: 'Torso', rawDamage: 5, armor: null },
      ],
    },
  ],
});
assertEqual(result.torso, 5, 'burst bullet no hit field: still tallied (undefined !== false)');

// Unknown location — ignored silently
result = computeRunDamage({
  mode: 'single',
  shots: [{ hit: true, location: 'Groin', rawDamage: 9, armor: null }],
});
assertDeepEqual(result, defaultDamage(), 'unknown location: silently ignored, all zeros');

// ── Summary ───────────────────────────────────────────────────────────────────

console.log('\n' + '='.repeat(40));
console.log(`Results: ${passed} passed, ${failed} failed`);
process.exit(failed > 0 ? 1 : 0);
