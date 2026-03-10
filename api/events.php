<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../src/db.php';
    $stmt = getDB()->query(
        "SELECT id, fired_at, mode, params_json, hits, misses,
                total_shots, total_bullets, results_json
         FROM fire_events
         WHERE fired_at > datetime('now', '-15 minutes')
         ORDER BY fired_at DESC"
    );

    $events = array_map(function (array $row): array {
        $results = json_decode($row['results_json'], true) ?? [];
        $mode    = $row['mode'];
        return [
            'id'           => (int)$row['id'],
            'fired_at'     => str_replace(' ', 'T', $row['fired_at']) . 'Z',
            'mode'         => $mode,
            'params'       => json_decode($row['params_json'], true) ?? [],
            'hits'         => (int)$row['hits'],
            'misses'       => (int)$row['misses'],
            'total_shots'  => (int)$row['total_shots'],
            'total_bullets'=> (int)$row['total_bullets'],
            'shots'        => $mode !== 'burst' ? $results : null,
            'bursts'       => $mode === 'burst' ? $results  : null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode($events);
} catch (Throwable $e) {
    error_log('cyberpunk-roller events: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Fire log unavailable.']);
}
