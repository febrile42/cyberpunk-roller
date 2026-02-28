# CLAUDE.md — dice2 Project Conventions

## Project Overview
A PHP web app for automating Cyberpunk 2020 TTRPG combat calculations: to-hit rolls, damage, hit location, and armor tracking.

## Tech Stack
- **Language:** PHP (server-side logic)
- **Frontend:** Standard HTML/CSS/JavaScript (no frameworks)
- **Storage:** Browser localStorage (no database, no Docker, no CI/CD)
- **Server:** Any standard PHP-capable web server (e.g., `php -S localhost:8000`)

## File Structure
```
dice2/
├── CLAUDE.md           # This file
├── README.md           # Project overview and usage
├── index.php           # Main entry point / UI
├── api/
│   └── roll.php        # JSON API endpoint (POST, returns combat results)
├── src/
│   ├── dice.php        # rollDice(), skillCheck(), rollLocation()
│   ├── combat.php      # evaluateHit(), processShot(), processBurst()
│   └── armor.php       # applyDamage(), locationKey(), defaultSP()
├── tests/
│   ├── run.php         # Test runner: php tests/run.php
│   ├── test_dice.php
│   ├── test_combat.php
│   └── test_armor.php
├── css/
│   └── style.css       # Styles
└── js/
    └── app.js          # Client-side logic (fetch, localStorage, UI)
```

## Coding Conventions
- PHP files use 4-space indentation
- HTML uses 2-space indentation
- CSS uses 2-space indentation
- JavaScript uses 2-space indentation
- No external libraries or CDN dependencies
- All dice rolls use PHP's `random_int()` for cryptographically secure randomness
- PHP logic is kept server-side; localStorage is used only for persisting armor/target state between sessions

## Game Rules Encoded
- Dice notation: `xDn+mod` (e.g., `3D6+4`)
- Skill checks use 1D10 with critical success (roll of 10 triggers an additional 0–9 bonus roll)
- Hit locations: D10 → 1=Head, 2-4=Torso, 5=Right Arm, 6=Left Arm, 7-8=Right Leg, 9-10=Left Leg
- Armor SP reduces damage; each hit that penetrates armor reduces that location's SP by 1
- Fire modes:
  - Single Shot: one independent to-hit roll; one hit if successful
  - 3-Round Burst: one to-hit roll per burst; on success, roll 1D3 for number of hits
  - Automatic: each shot is an independent to-hit roll (same skill + D10 per shot); user specifies total shot count

## Key Constraints
- No Docker, no CI/CD pipelines
- No external PHP packages or Composer
- No JavaScript frameworks (no React, Vue, etc.)
- Integers only for all game values unless explicitly noted
