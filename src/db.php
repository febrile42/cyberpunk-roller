<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0
// ── Database configuration ─────────────────────────────────────────────────────
// For Docker: set DB_HOST, DB_NAME, DB_USER, DB_PASS as environment variables.
// For native hosting: edit the defaults below.

const DB_HOST_DEFAULT = 'localhost';
const DB_NAME_DEFAULT = 'cyberpunk_roller';
const DB_USER_DEFAULT = 'root';
const DB_PASS_DEFAULT = '';
// ──────────────────────────────────────────────────────────────────────────────

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . (getenv('DB_HOST') ?: DB_HOST_DEFAULT)
                . ';dbname=' . (getenv('DB_NAME') ?: DB_NAME_DEFAULT)
                . ';charset=utf8mb4',
            getenv('DB_USER') ?: DB_USER_DEFAULT,
            getenv('DB_PASS') ?: DB_PASS_DEFAULT,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}
