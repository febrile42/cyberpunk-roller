<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0

/**
 * Tests for clientIp() and enforceRateLimit() in src/rate_limit.php.
 *
 * APCu notes:
 *   - enforceRateLimit() is a no-op when APCu is absent (function_exists check).
 *   - In CLI, APCu requires apc.enable_cli=1 in php.ini; apcu_enabled() detects this.
 *   - The "over-limit → exit()" case must run in a subprocess because exit() would
 *     terminate the test runner itself.
 *   - IDEs may flag apcu_* calls as undefined (P1010); this is a false positive —
 *     APCu is a C extension with no PHP-side declaration.
 */

require_once __DIR__ . '/../src/rate_limit.php';

// ── clientIp() ────────────────────────────────────────────────────────────────

$_SERVER['HTTP_CF_CONNECTING_IP'] = '1.2.3.4';
$_SERVER['HTTP_X_FORWARDED_FOR']  = '5.6.7.8';
$_SERVER['REMOTE_ADDR']           = '9.10.11.12';
assert_equal(clientIp(), '1.2.3.4',    'clientIp: CF-Connecting-IP wins when set');

unset($_SERVER['HTTP_CF_CONNECTING_IP']);
assert_equal(clientIp(), '5.6.7.8',    'clientIp: falls back to X-Forwarded-For');

unset($_SERVER['HTTP_X_FORWARDED_FOR']);
assert_equal(clientIp(), '9.10.11.12', 'clientIp: falls back to REMOTE_ADDR');

unset($_SERVER['REMOTE_ADDR']);
assert_equal(clientIp(), '',           'clientIp: returns empty string when nothing set');

// ── enforceRateLimit() — APCu tests ─────────────────────────────────────────

if (!function_exists('apcu_enabled') || !apcu_enabled()) {
    echo "  [SKIP] enforceRateLimit (APCu) — apc.enable_cli not enabled\n";
    echo "         Run with: php -d apc.enable_cli=1 tests/run.php\n";
    return;
}

// Each test uses a unique IP to avoid leaking state between runs.
$ip = '192.0.2.' . mt_rand(1, 254);  // TEST-NET-1 block, not routable

// First call: stores key with count 1, does NOT exit
enforceRateLimit($ip, 5, 10);
$count = apcu_fetch('rl:' . $ip, $stored);
assert_true($stored,       'enforceRateLimit: first call stores key in APCu');
assert_equal($count, 1,    'enforceRateLimit: first call stores count of 1');

// Subsequent calls within limit: each increments the counter, none exit
enforceRateLimit($ip, 5, 10);
enforceRateLimit($ip, 5, 10);
enforceRateLimit($ip, 5, 10);
$count = apcu_fetch('rl:' . $ip, $stored);
assert_equal($count, 4,    'enforceRateLimit: increments count on each call within limit');

// Empty IP: function should return without touching APCu
$emptyKey = 'rl:';
apcu_delete($emptyKey);
enforceRateLimit('', 5, 10);
apcu_fetch($emptyKey, $touched);
assert_false($touched,     'enforceRateLimit: empty IP is a no-op (no APCu key stored)');

// Over-limit: run in a subprocess so exit() doesn't kill the test runner.
// The subprocess pre-fills the APCu counter to maxCalls, then calls enforceRateLimit.
// Expected: JSON error output, and execution stops before the sentinel "NO_EXIT" string.
$srcPath = realpath(__DIR__ . '/../src/rate_limit.php');
$ip2     = '198.51.100.' . mt_rand(1, 254);  // TEST-NET-2 block
$key2    = 'rl:' . $ip2;
$sentinel = 'NO_EXIT';

// Write to a temp file to avoid shell-escaping issues with -r and single-quoted strings.
$tmpFile = tempnam(sys_get_temp_dir(), 'rl_test_') . '.php';
file_put_contents($tmpFile, implode("\n", [
    '<?php',
    'require ' . var_export($srcPath, true) . ';',
    'apcu_store(' . var_export($key2, true) . ', 5, 10);',
    'enforceRateLimit(' . var_export($ip2, true) . ', 5, 10);',
    'echo ' . var_export($sentinel, true) . ';',
]));

$raw = (string)shell_exec('php -d apc.enable_cli=1 ' . escapeshellarg($tmpFile) . ' 2>/dev/null');
unlink($tmpFile);
$decoded = json_decode($raw, true);

assert_equal(
    $decoded['error'] ?? null,
    'Too many requests. Please wait before firing again.',
    'enforceRateLimit: outputs 429 JSON when limit exceeded'
);
assert_false(
    str_contains($raw, $sentinel),
    'enforceRateLimit: calls exit() when limit exceeded (execution stops)'
);

// Cleanup
apcu_delete('rl:' . $ip);
