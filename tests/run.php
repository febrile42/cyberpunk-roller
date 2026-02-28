<?php

/**
 * Test runner — executes all test files and prints a summary.
 * Usage: php tests/run.php
 */

$passed = 0;
$failed = 0;
$errors = [];

function assert_equal($actual, $expected, string $label): void
{
    global $passed, $failed, $errors;
    if ($actual === $expected) {
        $passed++;
        echo "  [PASS] $label\n";
    } else {
        $failed++;
        $msg = "  [FAIL] $label\n         Expected: " . var_export($expected, true) . "\n         Got:      " . var_export($actual, true);
        $errors[] = $msg;
        echo $msg . "\n";
    }
}

function assert_true($actual, string $label): void
{
    assert_equal($actual, true, $label);
}

function assert_false($actual, string $label): void
{
    assert_equal($actual, false, $label);
}

function assert_range($actual, int $min, int $max, string $label): void
{
    global $passed, $failed, $errors;
    if (is_int($actual) && $actual >= $min && $actual <= $max) {
        $passed++;
        echo "  [PASS] $label\n";
    } else {
        $failed++;
        $msg = "  [FAIL] $label\n         Expected int in [$min, $max], got: " . var_export($actual, true);
        $errors[] = $msg;
        echo $msg . "\n";
    }
}

$testFiles = [
    'test_dice.php',
    'test_combat.php',
    'test_armor.php',
];

foreach ($testFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "\n[SKIP] $file — not found\n";
        continue;
    }
    echo "\n=== $file ===\n";
    require $path;
}

echo "\n" . str_repeat('=', 40) . "\n";
echo "Results: $passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
