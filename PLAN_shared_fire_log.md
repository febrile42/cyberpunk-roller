# Plan: Shared Fire Log with MariaDB

## Goal

Turn the center column from a local, ephemeral results view into a **shared, live fire log** visible to all page visitors, backed by MariaDB.  Target/SP/damage tracking stays entirely local (localStorage, right column, unchanged).

---

## What Changes

| Area | Change |
|---|---|
| Center column | Replaced by shared fire log (live, polled) |
| `api/roll.php` | INSERT event to DB before responding; return `eventId` |
| `api/events.php` | New GET endpoint — last 15 min of events, newest-first |
| `src/db.php` | New — PDO connection factory (user fills in credentials) |
| `db/schema.sql` | New — one-time DDL to create the table |
| `index.php` | Panel title: RESULTS → FIRE LOG; CLEAR button (see below) |
| `js/app.js` | Polling, fire log state, rendering; submit handler updated |
| `css/style.css` | Styles for log rows and expanded detail panels |

## What Does Not Change

- `src/combat.php`, `src/armor.php`, `src/dice.php`
- Right column (targets panel) — all localStorage, untouched
- Left column (attack form) — untouched except attribution option (see §Decisions)

---

## Database

### Table: `fire_events`

```sql
CREATE TABLE fire_events (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fired_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  mode          ENUM('single','auto','burst') NOT NULL,
  params_json   TEXT NOT NULL,          -- {skill, difficulty, damage, shots?, bursts?}
  hits          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  misses        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_shots   SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- shots fired or bursts attempted
  total_bullets SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- burst mode: bullets that landed
  results_json  MEDIUMTEXT NOT NULL,    -- shots[] or bursts[] array from roll.php
  INDEX idx_fired_at (fired_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Pruning

Events are **filtered on read** (`WHERE fired_at > NOW() - INTERVAL 15 MINUTE`).
No DELETE is needed; rows accumulate but remain small. An optional `DELETE` on each INSERT (purge rows older than 15 min) keeps the table clean for long sessions — this would be a one-liner added to `saveFireEvent()`.

---

## New File: `src/db.php`

PDO singleton. **This file is gitignored** — the user creates it manually with real credentials.

```php
<?php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4',
            'YOUR_USER',
            'YOUR_PASS',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}
```

A `src/db.php.example` (safe to commit) shows the structure with placeholder values.

---

## Modified: `api/roll.php`

After the `$response` array is fully assembled (in each switch case, just before `echo json_encode($response)`):

```php
require_once __DIR__ . '/../src/db.php';

$response['eventId'] = saveFireEvent($response);
echo json_encode($response);
```

`saveFireEvent()` is added at the bottom of `roll.php`:

```php
function saveFireEvent(array $r): int
{
    $db     = getDB();
    $mode   = $r['mode'];
    $shots  = $mode === 'burst' ? ($r['params']['bursts'] ?? 1) : ($r['params']['shots'] ?? 1);
    $bults  = $r['totalBullets'] ?? 0;
    $detail = json_encode($mode === 'burst' ? ($r['bursts'] ?? []) : ($r['shots'] ?? []));

    $stmt = $db->prepare(
        'INSERT INTO fire_events
            (mode, params_json, hits, misses, total_shots, total_bullets, results_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$mode, json_encode($r['params']), $r['hits'], $r['misses'], $shots, $bults, $detail]);

    // Optional: prune events older than 15 min to keep the table tidy
    $db->exec("DELETE FROM fire_events WHERE fired_at < NOW() - INTERVAL 15 MINUTE");

    return (int)$db->lastInsertId();
}
```

---

## New File: `api/events.php`

Simple GET endpoint — no auth required.

```php
<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/db.php';

$stmt = getDB()->query(
    "SELECT id, fired_at, mode, params_json, hits, misses,
            total_shots, total_bullets, results_json
     FROM fire_events
     WHERE fired_at > NOW() - INTERVAL 15 MINUTE
     ORDER BY fired_at DESC"
);

$events = array_map(function (array $row): array {
    $results = json_decode($row['results_json'], true);
    return [
        'id'           => (int)$row['id'],
        'fired_at'     => $row['fired_at'],
        'mode'         => $row['mode'],
        'params'       => json_decode($row['params_json'], true),
        'hits'         => (int)$row['hits'],
        'misses'       => (int)$row['misses'],
        'total_shots'  => (int)$row['total_shots'],
        'total_bullets'=> (int)$row['total_bullets'],
        'shots'        => $row['mode'] !== 'burst' ? $results : null,
        'bursts'       => $row['mode'] === 'burst' ? $results : null,
    ];
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

echo json_encode($events);
```

---

## JS Changes (`js/app.js`)

### New state

```js
let _expandedIds  = new Set();  // event IDs currently expanded
let _logEvents    = [];         // cached events from last poll
```

### Polling

```js
const POLL_MS = 3000;

async function fetchFireLog() {
  try {
    const res  = await fetch('api/events.php');
    _logEvents = await res.json();
    renderFireLog(_logEvents);
  } catch { /* silent — poll errors don't break the UI */ }
}

// Start polling on page load (alongside renderTargets())
setInterval(fetchFireLog, POLL_MS);
fetchFireLog();
```

### Submit handler changes

After the current `updateActiveDamage(...)` call, replace `renderResults(data)` with:

```js
// Auto-expand the event the user just fired
if (data.eventId) _expandedIds.add(data.eventId);

// Immediately refresh the log (no wait for next poll)
fetchFireLog();
```

`renderResults()` and the summary bar update code are **removed** (the fire log subsumes them).

### Fire log rendering

Each event renders as two divs: a collapsed `.log-row` header and a hidden `.log-detail` body.

**Collapsed header anatomy (one line, flex grid):**

```
[▶]  [AUTO]  [3 HIT / 2 MISS · 5 shots]  [3D6+2 · sk:10 dif:15]  [2m ago]
```

Color rules:
- Mode badge: accent color (`--accent`)
- Hit count: green (`--hit-fg`)
- Miss count: red (`--miss-fg`)
- Params + time: dim (`--text-dim`)

**Expanded body:**
The existing `renderShotCard` / `renderBurstCard` output, indented inside a `.log-detail` container. Reuses all existing card CSS — no changes needed to the card styles.

**Toggle behavior:**
Clicking a `.log-row` adds/removes its event ID from `_expandedIds`, then re-renders the log.  Since the log is re-rendered from `_logEvents` (no re-fetch needed for toggle), this is instant.

### Relative time helper

```js
function relativeTime(firedAt) {
  const diff = Math.floor((Date.now() - new Date(firedAt + 'Z')) / 1000);
  if (diff < 10)  return 'just now';
  if (diff < 60)  return `${diff}s ago`;
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  return `${Math.floor(diff / 3600)}h ago`;
}
```

### CLEAR button

**Proposed behavior change:** CLEAR collapses all expanded rows (`_expandedIds.clear(); renderFireLog(_logEvents);`) rather than clearing the results content. This is a sensible action on a shared log — "collapse everything" — without wiping data others can see.

---

## HTML Changes (`index.php`)

- Change `<h2 class="panel-title">RESULTS</h2>` → `FIRE LOG`
- Remove or retitle `#results-summary` (the tally bar is no longer needed as a standalone div; each log row carries its own tally)
- `#results-content` becomes `#fire-log` (rename the element and its references in JS)

---

## CSS Additions (`css/style.css`)

New rules for the fire log — existing shot card rules are **unchanged**:

```css
/* ── Fire log rows ───────────────────────────────────────────── */

.log-event   { margin-bottom: 4px; }

.log-row {
  display: grid;
  grid-template-columns: 20px 58px 1fr auto auto;
  align-items: center;
  column-gap: 10px;
  padding: 5px 10px;
  background: var(--bg-input);
  border: 1px solid var(--border-hi);
  cursor: pointer;
  transition: background 0.1s;
}
.log-row:hover { background: #1a1a1a; }

.log-toggle  { color: var(--text-dim); font-size: 9px; }
.log-mode    { color: var(--accent);   font-size: 10px; font-weight: bold; letter-spacing: 0.2em; }
.log-tally   { font-size: 11px; }
.log-params  { color: var(--text-dim); font-size: 10px; text-align: right; }
.log-time    { color: var(--text-dim); font-size: 10px; white-space: nowrap; }

.log-detail {
  border: 1px solid var(--border);
  border-top: none;
  padding: 8px 10px;
  background: var(--bg);
}
```

---

## Decisions to Confirm

### 1 · DB credentials
`src/db.php` needs host, database name, username, and password.
→ **User supplies these when creating the file from the provided example.**

### 2 · Attribution (optional "caller" name)
Should each fire event record who fired it? Since there's no login, this would be a free-text "Caller" input on the attack form (e.g., "Nomad", "GM"). It would appear in the collapsed row header and be stored in `params_json`.

- **Option A — No attribution** (simpler; all events are anonymous)
- **Option B — Optional caller field** (one new text input in the attack form; label like "Caller" or "Name")

### 3 · CLEAR button behavior
- **Option A — Repurpose:** "Collapse all" (clears `_expandedIds`, re-renders log collapsed)
- **Option B — Remove:** No CLEAR button; the 15-min window is self-managing
- **Option C — Keep local clear:** Hides the fire log panel content until next poll (3s delay before it reappears)

### 4 · Auto-scroll on poll
When the log re-renders after a poll, should the results panel scroll to the top?
- **Option A — Always scroll to top** (matches current behavior; you see new events immediately)
- **Option B — Only scroll to top if user hasn't scrolled** (preserves reading position)

### 5 · Caller label in log header (if attribution is chosen)
E.g., `[▶] GM  AUTO  3D6+2 · sk:10 dif:15   3 HIT / 2 MISS   2m ago`
