<?php

/**
 * Returns the real client IP, preferring Cloudflare's CF-Connecting-IP header.
 * Behind Cloudflare, REMOTE_ADDR is always a Cloudflare egress IP — using it
 * alone would put all visitors into a single rate-limit bucket.
 */
function clientIp(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
}

/**
 * APCu-based per-IP rate limiter.
 * Exits with HTTP 429 if $ip has exceeded $maxCalls within $windowSeconds.
 * No-op (passes through silently) if the APCu extension is unavailable.
 */
function enforceRateLimit(string $ip, int $maxCalls = 30, int $windowSeconds = 60): void
{
    if (!function_exists('apcu_fetch') || $ip === '') return;

    $key   = 'rl:' . $ip;
    $count = apcu_fetch($key, $success);

    if (!$success) {
        apcu_store($key, 1, $windowSeconds);
        return;
    }

    if ($count >= $maxCalls) {
        http_response_code(429);
        header('Retry-After: ' . $windowSeconds);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Too many requests. Please wait before firing again.']);
        exit;
    }

    apcu_inc($key);
}
