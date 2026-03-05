# cyberpunk-roller — Cyberpunk 2020 Combat Calculator

A browser-based PHP web app for automating combat calculations in the **Cyberpunk 2020** tabletop RPG.

## What It Does

- Calculates whether shots hit based on a character's to-hit skill and a difficulty score
- Supports three fire modes: **Single Shot**, **3-Round Burst**, and **Automatic**
- Rolls hit location (Head, Torso, Arms, Legs) for each successful hit
- Calculates damage per hit using standard dice notation (e.g., `3D6+4`)
- Tracks armor **Stopping Power (SP)** per location across multiple targets
- Degrades armor SP permanently on each penetrating hit

## Hosting

### Docker

Pull the pre-built image from Docker Hub:

```bash
docker compose pull && docker compose up -d
```

Or build from source:

```bash
docker build -t febrile42/cyberpunk-roller:latest . && docker compose up -d
```

App available at `http://localhost:8080`. MariaDB and the schema initialize automatically on first run. Default credentials are in `compose.yaml`; override with `DB_PASS` / `DB_ROOT_PASS` in a `.env` file if desired.

### Native (Apache / Nginx / PHP built-in server)

Requirements: PHP 8.0+, `pdo_mysql` extension. The `apcu` extension is optional — when present it enables per-IP rate limiting on the roll API.

```bash
php -S localhost:8000
```

Open `http://localhost:8000`. Roll results work without a database — the fire log requires MariaDB:

1. Edit credentials at the top of `src/db.php`
2. Point your web server's document root at the project with `AllowOverride All` (Apache) or equivalent

The `fire_events` table is created automatically on first connection. `db/schema.sql` is provided for reference.

## Game Rules Summary

### Dice Notation
- `xDn` — roll x dice with n sides, sum the results
- `xDn+mod` — sum plus a flat modifier
- Skill check (1D10) — if a 10 is rolled, roll an additional 0–9 and add it (critical success)

### Fire Modes
| Mode | Behavior |
|---|---|
| Single Shot | One to-hit roll; one hit if successful |
| 3-Round Burst | One to-hit roll; if successful, roll 1D3 for number of hits |
| Automatic | Each shot is an independent to-hit roll (skill + D10); specify total shot count |

### Hit Locations (D10)
| Roll | Location |
|---|---|
| 1 | Head |
| 2–4 | Torso |
| 5 | Right Arm |
| 6 | Left Arm |
| 7–8 | Right Leg |
| 9–10 | Left Leg |

### Armor
- Each location has a Stopping Power (SP) value
- Damage that exceeds SP passes through to the target
- Each penetrating hit reduces that location's SP by 1 permanently
- Targets can be generic (shared SP values) or unique (individually configured)

## Storage

Armor and target state is saved in browser **localStorage** — no server-side database required.
