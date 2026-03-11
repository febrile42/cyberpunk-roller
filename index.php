<?php
// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'self\'');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CP2020 Combat Calculator</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <header class="site-header">
    <div class="header-inner">
      <h1>COMBAT CALCULATOR</h1>
      <span class="header-sub">// CYBERPUNK 2020</span>
    </div>
  </header>

  <main class="app-layout">

    <section id="form-panel" class="panel" aria-label="Attack Parameters">
      <h2 class="panel-title">ATTACK</h2>
      <form id="combat-form" novalidate>

        <fieldset id="mode-fieldset">
          <legend>Fire Mode</legend>
          <label class="radio-option">
            <input type="radio" name="mode" value="auto" checked>
            <span class="radio-label">Automatic</span>
          </label>
          <label class="radio-option">
            <input type="radio" name="mode" value="burst">
            <span class="radio-label">3-Round Burst</span>
          </label>
          <label class="radio-option">
            <input type="radio" name="mode" value="single">
            <span class="radio-label">Single Shot</span>
          </label>
        </fieldset>

        <div id="field-shots" class="form-field">
          <label for="shots">Number of Shots</label>
          <input type="number" id="shots" name="shots" min="1" max="100" value="30">
        </div>

        <div id="field-bursts" class="form-field" hidden>
          <label for="bursts">Number of Bursts</label>
          <input type="number" id="bursts" name="bursts" min="1" max="30" value="1">
        </div>

        <div class="form-field">
          <label for="skill">Base To-Hit Skill</label>
          <input type="number" id="skill" name="skill" min="0" value="10">
        </div>

        <div class="form-field">
          <label for="difficulty">Difficulty Score</label>
          <input type="number" id="difficulty" name="difficulty" min="1" value="15">
        </div>

        <div class="form-field">
          <label for="damage">Damage Dice</label>
          <input type="text" id="damage" name="damage" placeholder="e.g. 3D6+4" value="2D6">
          <span class="field-hint">Format: xDn or xDn&plusmn;mod</span>
        </div>

        <div class="form-actions">
          <button type="submit" id="fire-btn">FIRE</button>
          <button type="button" id="clear-btn">CLEAR</button>
        </div>

        <div id="active-target-indicator" class="active-target-indicator"></div>

      </form>
      <div class="panel-credit">
        <a href="https://github.com/febrile42/cyberpunk-roller" target="_blank" rel="noopener noreferrer">github.com/febrile42
        · <a href="https://polyformproject.org/licenses/noncommercial/1.0.0/" target="_blank" rel="noopener noreferrer">PolyForm NC 1.0</a>
      </div>
    </section>

    <section id="results-panel" class="panel" aria-label="Fire Log" aria-live="polite">
      <h2 class="panel-title">FIRE LOG <span class="panel-title-sub">// last 15 min · live</span></h2>
      <div id="fire-log">
        <p class="empty-state">No fire events in the last 15 minutes.</p>
      </div>
    </section>

    <section id="targets-panel" class="panel" aria-label="Target Management">
    <h2 class="panel-title">TARGETS</h2>

    <div class="add-target-form">
      <input type="text" id="new-target-name" placeholder="Name (auto if blank)" autocomplete="off">
      <fieldset id="add-target-type-fieldset">
        <label class="radio-option">
          <input type="radio" name="new-target-type" value="generic" checked>
          <span class="radio-label">Generic</span>
        </label>
        <label class="radio-option">
          <input type="radio" name="new-target-type" value="unique">
          <span class="radio-label">Unique</span>
        </label>
      </fieldset>
      <button type="button" id="add-target-btn">+ ADD</button>
    </div>

    <div id="target-list">
      <p class="empty-state">No targets. Add one above.</p>
    </div>
  </section>

  </main>

  <script src="js/app.js"></script>
</body>
</html>
