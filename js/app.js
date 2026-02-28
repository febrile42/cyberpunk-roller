'use strict';

document.addEventListener('DOMContentLoaded', () => {

  // ── Constants ───────────────────────────────────────────────────────────

  const LS_KEY = 'cp2020';

  const SP_LOCATIONS = [
    { key: 'head',     label: 'HEAD'  },
    { key: 'torso',    label: 'TORSO' },
    { key: 'rightArm', label: 'R.ARM' },
    { key: 'leftArm',  label: 'L.ARM' },
    { key: 'rightLeg', label: 'R.LEG' },
    { key: 'leftLeg',  label: 'L.LEG' },
  ];

  // ── Combat form references ───────────────────────────────────────────────

  const form        = document.getElementById('combat-form');
  const fireBtn     = document.getElementById('fire-btn');
  const clearBtn    = document.getElementById('clear-btn');
  const fieldShots  = document.getElementById('field-shots');
  const fieldBursts = document.getElementById('field-bursts');
  const summary     = document.getElementById('results-summary');
  const content     = document.getElementById('results-content');

  // ── localStorage state ───────────────────────────────────────────────────

  function defaultSP() {
    return { head: 0, torso: 0, rightArm: 0, leftArm: 0, rightLeg: 0, leftLeg: 0 };
  }

  function loadState() {
    try {
      const raw = localStorage.getItem(LS_KEY);
      if (!raw) return { targets: [], genericSP: defaultSP(), activeTargetId: null };
      return JSON.parse(raw);
    } catch {
      return { targets: [], genericSP: defaultSP(), activeTargetId: null };
    }
  }

  function saveState(state) {
    localStorage.setItem(LS_KEY, JSON.stringify(state));
  }

  function getActiveTargetSP() {
    const state = loadState();
    if (!state.activeTargetId) return null;
    const target = state.targets.find(t => t.id === state.activeTargetId);
    if (!target) return null;
    return target.sp || defaultSP();
  }

  function updateActiveSP(finalSP) {
    const state = loadState();
    const target = state.targets.find(t => t.id === state.activeTargetId);
    if (!target) return;
    target.sp = finalSP;
    saveState(state);
    renderTargets();
  }

  // ── Target CRUD ──────────────────────────────────────────────────────────

  function addTarget(name, isGeneric) {
    const state        = loadState();
    const genericCount = state.targets.filter(t =>  t.generic).length;
    const uniqueCount  = state.targets.filter(t => !t.generic).length;
    const autoName     = name.trim() ||
      (isGeneric ? `Generic ${genericCount + 1}` : `Unique ${uniqueCount + 1}`);

    const target = { id: 't' + Date.now(), name: autoName, generic: isGeneric };
    target.sp = isGeneric ? { ...state.genericSP } : defaultSP();

    state.targets.push(target);
    if (!state.activeTargetId) state.activeTargetId = target.id;
    saveState(state);
    renderTargets();
  }

  function deleteTarget(id) {
    const state    = loadState();
    state.targets  = state.targets.filter(t => t.id !== id);
    if (state.activeTargetId === id) {
      state.activeTargetId = state.targets.length > 0 ? state.targets[0].id : null;
    }
    saveState(state);
    renderTargets();
  }

  function setActiveTarget(id) {
    const state          = loadState();
    state.activeTargetId = id;
    saveState(state);
    renderTargets();
  }

  function updateSP(targetId, location, value, isGeneric) {
    const state = loadState();
    if (isGeneric) {
      // Update the template so new generic targets inherit this value
      state.genericSP[location] = value;
      // Batch-apply to all existing generic targets
      state.targets.filter(t => t.generic).forEach(t => {
        if (!t.sp) t.sp = defaultSP();
        t.sp[location] = value;
      });
      // Sync individual generic target SP inputs in the DOM
      document.querySelectorAll(`.sp-input[data-generic-target][data-location="${location}"]`)
        .forEach(el => { el.value = value; });
    } else {
      const target = state.targets.find(t => t.id === targetId);
      if (target) {
        if (!target.sp) target.sp = defaultSP();
        target.sp[location] = value;
      }
    }
    saveState(state);
  }

  // ── Mode switching & fire button label ──────────────────────────────────

  form.querySelectorAll('input[name="mode"]').forEach(radio => {
    radio.addEventListener('change', () => {
      updateModeFields(radio.value);
      updateFireBtnText();
    });
  });

  form.shots.addEventListener('input',  updateFireBtnText);
  form.bursts.addEventListener('input', updateFireBtnText);

  function updateModeFields(mode) {
    fieldShots.hidden  = (mode !== 'auto');
    fieldBursts.hidden = (mode !== 'burst');
  }

  function updateFireBtnText() {
    const mode = form.querySelector('input[name="mode"]:checked').value;
    if (mode === 'auto') {
      const n = parseInt(form.shots.value, 10);
      fireBtn.textContent = n > 0 ? `FIRE ${n} SHOTS` : 'FIRE';
    } else if (mode === 'burst') {
      const n = parseInt(form.bursts.value, 10);
      fireBtn.textContent = n > 0 ? `FIRE ${n} BURSTS` : 'FIRE';
    } else {
      fireBtn.textContent = 'FIRE';
    }
  }

  // ── Clear button ─────────────────────────────────────────────────────────

  clearBtn.addEventListener('click', () => {
    summary.hidden    = true;
    summary.innerHTML = '';
    content.innerHTML = '<p class="empty-state">Fire to see results.</p>';
  });

  // ── Add-target form ──────────────────────────────────────────────────────

  const addTargetBtn  = document.getElementById('add-target-btn');
  const newTargetName = document.getElementById('new-target-name');

  addTargetBtn.addEventListener('click', () => {
    const name      = newTargetName.value;
    const typeRadio = document.querySelector('input[name="new-target-type"]:checked');
    addTarget(name, typeRadio ? typeRadio.value === 'generic' : true);
    newTargetName.value = '';
    newTargetName.focus();
  });

  newTargetName.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); addTargetBtn.click(); }
  });

  // ── Form submit ──────────────────────────────────────────────────────────

  form.addEventListener('submit', async e => {
    e.preventDefault();

    const mode       = form.querySelector('input[name="mode"]:checked').value;
    const skill      = parseInt(form.skill.value, 10);
    const difficulty = parseInt(form.difficulty.value, 10);
    const damage     = form.damage.value.trim();

    if (isNaN(skill) || skill < 0)    return renderError('Skill must be a non-negative integer.');
    if (isNaN(difficulty) || difficulty < 1) return renderError('Difficulty must be a positive integer.');
    if (!damage)                       return renderError('Damage notation is required (e.g. 3D6+4).');

    const payload = { mode, skill, difficulty, damage };

    if (mode === 'auto') {
      const shots = parseInt(form.shots.value, 10);
      if (isNaN(shots) || shots < 1) return renderError('Shot count must be at least 1.');
      payload.shots = shots;
    } else if (mode === 'burst') {
      const bursts = parseInt(form.bursts.value, 10);
      if (isNaN(bursts) || bursts < 1) return renderError('Burst count must be at least 1.');
      payload.bursts = bursts;
    }

    // Include active target's current SP if one is selected
    const activeSP = getActiveTargetSP();
    if (activeSP !== null) payload.armorSP = activeSP;

    fireBtn.disabled    = true;
    fireBtn.textContent = 'FIRING...';

    try {
      const response = await fetch('api/roll.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
      });

      const data = await response.json();

      if (!response.ok) {
        renderError(data.error || 'Server error.');
        return;
      }

      // Persist SP changes caused by this combat round
      if (data.finalSP) updateActiveSP(data.finalSP);

      renderResults(data);

    } catch (err) {
      renderError('Could not reach the server. Is PHP running? (php -S localhost:8000)');
    } finally {
      fireBtn.disabled    = false;
      fireBtn.textContent = 'FIRE';
    }
  });

  // ── Render results ───────────────────────────────────────────────────────

  function renderResults(data) {
    const { mode, params, hits, misses } = data;
    const total = hits + misses;

    summary.hidden = false;

    if (mode === 'burst') {
      const bulletNote = data.totalBullets > 0
        ? `&ensp;<span class="tally-total">${data.totalBullets} bullet${data.totalBullets !== 1 ? 's' : ''} landed</span>`
        : '';
      summary.innerHTML =
        `<span class="tally-hits">${hits}&nbsp;BURST${hits !== 1 ? 'S' : ''}&nbsp;HIT</span>` +
        `&ensp;/&ensp;` +
        `<span class="tally-misses">${misses}&nbsp;MISS${misses !== 1 ? 'ES' : ''}</span>` +
        `&ensp;<span class="tally-total">(${total} burst${total !== 1 ? 's' : ''})</span>` +
        bulletNote;
    } else {
      summary.innerHTML =
        `<span class="tally-hits">${hits}&nbsp;HIT${hits !== 1 ? 'S' : ''}</span>` +
        `&ensp;/&ensp;` +
        `<span class="tally-misses">${misses}&nbsp;MISS${misses !== 1 ? 'ES' : ''}</span>` +
        `&ensp;<span class="tally-total">(${total} shot${total !== 1 ? 's' : ''})</span>`;
    }

    let html = '';
    if (mode === 'single' || mode === 'auto') {
      data.shots.forEach(shot => { html += renderShotCard(shot, params); });
    } else if (mode === 'burst') {
      data.bursts.forEach(burst => { html += renderBurstCard(burst, params); });
    }

    content.innerHTML = html || '<p class="empty-state">No shots to display.</p>';

    // Scroll the results panel back to the top so new results are immediately visible
    const resultsPanel = document.getElementById('results-panel');
    if (resultsPanel) resultsPanel.scrollTop = 0;
  }

  function renderShotCard(shot, params) {
    const rollBreakdown = buildRollBreakdown(shot.skillRoll);
    const critBadge     = shot.skillRoll.critical
      ? ' <span class="badge-crit">CRITICAL</span>'
      : '';

    // Compact single-line card for misses
    if (!shot.hit) {
      return `<div class="shot miss compact">` +
        `<span class="shot-num">SHOT ${shot.num}</span>` +
        `<span class="shot-outcome">MISS</span>` +
        `<span class="shot-roll-compact">${rollBreakdown} + ${params.skill} = ${shot.total} vs. ${params.difficulty}</span>` +
        `</div>`;
    }

    // Fully blocked by armor — amber tint, 'BLOCKED' outcome label
    const isBlocked = shot.armor && !shot.armor.penetrated;
    const cls       = isBlocked ? 'shot hit blocked' : 'shot hit';
    const outcome   = isBlocked ? 'BLOCKED' : 'HIT';

    let html = `<div class="${cls}">`;

    html += `<div class="shot-header">`;
    html += `<span class="shot-num">SHOT ${shot.num}</span>`;
    html += `<span class="shot-outcome">${outcome}</span>`;
    html += critBadge;
    html += `</div>`;

    html += `<div class="shot-body">`;
    html += `<div class="shot-roll">${rollBreakdown} + ${params.skill} = <strong>${shot.total}</strong> vs. ${params.difficulty}</div>`;
    html += `<div class="shot-location">Location: <strong>${escapeHtml(shot.location)}</strong></div>`;
    html += renderDamageLine(shot.rawDamage, shot.armor);
    html += `</div></div>`;
    return html;
  }

  function renderBurstCard(burst, params) {
    const rollBreakdown = buildRollBreakdown(burst.skillRoll);
    const critBadge     = burst.skillRoll.critical
      ? ' <span class="badge-crit">CRITICAL</span>'
      : '';

    // Compact single-line card for misses
    if (!burst.hit) {
      return `<div class="shot miss compact">` +
        `<span class="shot-num">BURST ${burst.num}</span>` +
        `<span class="shot-outcome">MISS</span>` +
        `<span class="shot-roll-compact">${rollBreakdown} + ${params.skill} = ${burst.total} vs. ${params.difficulty}</span>` +
        `</div>`;
    }

    const allBlocked = burst.bullets.length > 0 && burst.bullets.every(b => b.armor && !b.armor.penetrated);
    const outerCls   = allBlocked ? 'shot hit blocked' : 'shot hit';
    const outcome    = allBlocked ? 'BLOCKED' : 'HIT';

    let html = `<div class="${outerCls}">`;

    html += `<div class="shot-header">`;
    html += `<span class="shot-num">BURST ${burst.num}</span>`;
    html += `<span class="shot-outcome">${outcome}</span>`;
    html += ` <span class="shot-bullet-count">${burst.bulletCount} bullet${burst.bulletCount !== 1 ? 's' : ''}</span>`;
    html += critBadge;
    html += `</div>`;

    html += `<div class="shot-body">`;
    html += `<div class="shot-roll">${rollBreakdown} + ${params.skill} = <strong>${burst.total}</strong> vs. ${params.difficulty}</div>`;

    burst.bullets.forEach((bullet, i) => {
      const bulletBlocked = bullet.armor && !bullet.armor.penetrated;
      html += `<div class="bullet-hit${bulletBlocked ? ' blocked' : ''}">`;
      html += `<span class="bullet-num">&#x2022; Bullet ${i + 1}</span>`;
      html += ` &mdash; Location: <strong>${escapeHtml(bullet.location)}</strong>`;
      html += ` &nbsp; `;
      html += renderDamageLine(bullet.rawDamage, bullet.armor);
      html += `</div>`;
    });

    html += `</div></div>`;
    return html;
  }

  // Renders the damage line for a single hit (shared between shot cards and bullet entries).
  function renderDamageLine(rawDamage, armor) {
    if (!armor) {
      return `<div class="shot-damage">Damage: <strong>${rawDamage}</strong></div>`;
    }

    if (!armor.penetrated) {
      return `<div class="shot-damage">` +
        `Damage: <strong>${rawDamage}</strong> ` +
        `<span class="armor-blocked">&mdash; blocked (SP ${armor.spBefore})</span>` +
        `</div>`;
    }

    return `<div class="shot-damage">` +
      `Damage: <strong>${rawDamage}</strong> ` +
      `<span class="armor-detail">&mdash; <strong class="passthrough">${armor.passthrough}</strong> through &nbsp;` +
      `(SP: ${armor.spBefore}&thinsp;&#x2192;&thinsp;<span class="${armor.spAfter === 0 ? 'sp-zero' : ''}">${armor.spAfter}</span>)` +
      `</span>` +
      `</div>`;
  }

  // Build the roll part of the breakdown string.
  // Normal:   Roll: [8]
  // Critical: Roll: [10 → +5]
  function buildRollBreakdown(skillRoll) {
    if (skillRoll.critical) {
      const [first, bonus] = skillRoll.rolls;
      return `Roll: [${first} <span class="crit-arrow">&#x2192;</span> +${bonus}]`;
    }
    return `Roll: [${skillRoll.rolls[0]}]`;
  }

  // ── Error display ────────────────────────────────────────────────────────

  function renderError(msg) {
    summary.hidden    = true;
    summary.innerHTML = '';
    content.innerHTML = `<div class="error-msg">${escapeHtml(msg)}</div>`;
  }

  // ── Target panel rendering ───────────────────────────────────────────────

  function renderTargets() {
    const targetList = document.getElementById('target-list');
    if (!targetList) return;

    const state          = loadState();
    const genericTargets = state.targets.filter(t =>  t.generic);
    const uniqueTargets  = state.targets.filter(t => !t.generic);

    if (state.targets.length === 0) {
      targetList.innerHTML = '<p class="empty-state">No targets. Add one above.</p>';
      updateActiveTargetIndicator(null);
      return;
    }

    let html = '';

    // ── Generic targets section ──────────────────────────────────────────
    if (genericTargets.length > 0) {
      html += `<div class="target-section">`;
      html += `<div class="target-section-title">GENERIC (${genericTargets.length})</div>`;

      // Batch-set row: sets all generic targets to the same SP value per location
      html += `<div class="batch-set-label">batch set all</div>`;
      html += `<div class="shared-sp-row">`;
      SP_LOCATIONS.forEach(loc => {
        const bVal  = state.genericSP[loc.key] ?? 0;
        const bZero = bVal === 0 ? ' sp-input-zero' : '';
        html += `<div class="sp-field">` +
          `<label>${loc.label}</label>` +
          `<input type="number" class="sp-input${bZero}" data-generic="true" data-location="${loc.key}" ` +
          `value="${bVal}" min="0">` +
          `</div>`;
      });
      html += `</div>`;

      // Individual generic target rows — each with its own SP editor
      genericTargets.forEach(target => {
        const isActive = target.id === state.activeTargetId;
        const sp       = target.sp || defaultSP();

        html += `<div class="target-row has-sp${isActive ? ' active-target' : ''}">`;

        html += `<div class="target-row-header">` +
          `<button class="btn-select${isActive ? ' is-active' : ''}" data-id="${target.id}">${isActive ? '&#x25B6;' : '&#x25B7;'}</button>` +
          `<span class="target-name">${escapeHtml(target.name)}</span>` +
          `<span class="target-badge badge-generic">GENERIC</span>` +
          `<button class="btn-delete" data-id="${target.id}" title="Remove">&#x2715;</button>` +
          `</div>`;

        html += `<div class="target-sp-row">`;
        SP_LOCATIONS.forEach(loc => {
          const gVal  = sp[loc.key] ?? 0;
          const gZero = gVal === 0 ? ' sp-input-zero' : '';
          html += `<div class="sp-field">` +
            `<label>${loc.label}</label>` +
            `<input type="number" class="sp-input${gZero}" data-generic-target data-target-id="${target.id}" data-location="${loc.key}" ` +
            `value="${gVal}" min="0">` +
            `</div>`;
        });
        html += `</div>`;

        html += `</div>`; // .target-row
      });

      html += `</div>`;
    }

    // ── Unique targets section ───────────────────────────────────────────
    if (uniqueTargets.length > 0) {
      html += `<div class="target-section">`;
      html += `<div class="target-section-title">UNIQUE (${uniqueTargets.length})</div>`;

      uniqueTargets.forEach(target => {
        const isActive = target.id === state.activeTargetId;
        const sp       = target.sp || defaultSP();

        html += `<div class="target-row has-sp${isActive ? ' active-target' : ''}">`;

        html += `<div class="target-row-header">` +
          `<button class="btn-select${isActive ? ' is-active' : ''}" data-id="${target.id}">${isActive ? '&#x25B6;' : '&#x25B7;'}</button>` +
          `<span class="target-name">${escapeHtml(target.name)}</span>` +
          `<span class="target-badge badge-unique">UNIQUE</span>` +
          `<button class="btn-delete" data-id="${target.id}" title="Remove">&#x2715;</button>` +
          `</div>`;

        html += `<div class="target-sp-row">`;
        SP_LOCATIONS.forEach(loc => {
          const uVal  = sp[loc.key] ?? 0;
          const uZero = uVal === 0 ? ' sp-input-zero' : '';
          html += `<div class="sp-field">` +
            `<label>${loc.label}</label>` +
            `<input type="number" class="sp-input${uZero}" data-target-id="${target.id}" data-location="${loc.key}" ` +
            `value="${uVal}" min="0">` +
            `</div>`;
        });
        html += `</div>`;

        html += `</div>`; // .target-row
      });

      html += `</div>`;
    }

    targetList.innerHTML = html;
    attachTargetListeners();

    const activeTarget = state.targets.find(t => t.id === state.activeTargetId) || null;
    updateActiveTargetIndicator(activeTarget);
  }

  function attachTargetListeners() {
    const targetList = document.getElementById('target-list');
    if (!targetList) return;

    // Clicking the header row selects the target; btn-select handles its own click
    targetList.querySelectorAll('.target-row-header').forEach(header => {
      header.addEventListener('click', e => {
        if (e.target.closest('.btn-delete') || e.target.closest('.btn-select')) return;
        const btn = header.querySelector('.btn-select');
        if (btn) setActiveTarget(btn.dataset.id);
      });
    });

    targetList.querySelectorAll('.btn-select').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation(); // header listener already covered; prevent double-call
        setActiveTarget(btn.dataset.id);
      });
    });

    targetList.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', () => deleteTarget(btn.dataset.id));
    });

    targetList.querySelectorAll('.sp-input').forEach(input => {
      input.addEventListener('change', () => {
        const val        = Math.max(0, parseInt(input.value, 10) || 0);
        input.value      = val;
        input.classList.toggle('sp-input-zero', val === 0);
        // data-generic="true" → batch-set row; data-target-id → individual target
        const isBatchSet = input.dataset.generic === 'true';
        updateSP(input.dataset.targetId || null, input.dataset.location, val, isBatchSet);
      });
    });
  }

  function updateActiveTargetIndicator(target) {
    const el = document.getElementById('active-target-indicator');
    if (!el) return;
    if (!target) {
      el.innerHTML = '<span class="tgt-dim">No target — armor not tracked</span>';
    } else {
      const badgeCls = target.generic ? 'badge-generic' : 'badge-unique';
      const badgeTxt = target.generic ? 'GENERIC' : 'UNIQUE';
      el.innerHTML = `Target: <span class="tgt-name">${escapeHtml(target.name)}</span>` +
        ` <span class="target-badge ${badgeCls}">${badgeTxt}</span>`;
    }
  }

  // ── Utilities ────────────────────────────────────────────────────────────

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#039;');
  }

  // ── Initialise ───────────────────────────────────────────────────────────

  updateFireBtnText();
  renderTargets();
});
