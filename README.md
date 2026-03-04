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

### Docker (recommended)

```bash
cp .env.example .env     # set DB_PASS and DB_ROOT_PASS
docker compose up --build -d
```

App available at `http://localhost:8080`. MariaDB and the schema are initialized automatically on first run. Data persists in a named Docker volume (`db_data`).

### Native (Apache / Nginx / PHP built-in server)

Requirements: PHP 8.0+, `pdo_mysql` extension, optionally `apcu`.

**Quick start (no database, no fire log):**

```bash
cd cyberpunk-roller
php -S localhost:8000
```

Open `http://localhost:8000`. Roll results work immediately — the fire log requires a database.

**With MariaDB fire log:**

1. Import the schema: `mariadb -u root -p < db/schema.sql`
2. Edit the credentials at the top of `src/db.php`
3. Point your web server's document root at the project directory with `AllowOverride All` (Apache) or equivalent (Nginx)
4. Reload your server

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
