-- CP2020 Combat Calculator — fire event log
-- SQLite reference schema. The table and index are created automatically
-- by src/db.php on first connection — no manual import needed.

CREATE TABLE IF NOT EXISTS fire_events (
  id            INTEGER  PRIMARY KEY AUTOINCREMENT,
  fired_at      TEXT     NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f', 'now')),
  mode          TEXT     NOT NULL CHECK(mode IN ('single','auto','burst')),
  params_json   TEXT     NOT NULL,   -- {skill, difficulty, damage, shots?, bursts?}
  hits          INTEGER  NOT NULL DEFAULT 0,
  misses        INTEGER  NOT NULL DEFAULT 0,
  total_shots   INTEGER  NOT NULL DEFAULT 0,  -- shots fired (auto/single) or bursts attempted
  total_bullets INTEGER  NOT NULL DEFAULT 0,  -- burst mode: bullets that landed
  results_json  TEXT     NOT NULL              -- shots[] or bursts[] array
);

CREATE INDEX IF NOT EXISTS idx_fired_at ON fire_events(fired_at);
