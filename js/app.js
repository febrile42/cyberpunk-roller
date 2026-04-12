// SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0
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

  const LOCATION_KEY_MAP = {
    'Head':      'head',
    'Torso':     'torso',
    'Right Arm': 'rightArm',
    'Left Arm':  'leftArm',
    'Right Leg': 'rightLeg',
    'Left Leg':  'leftLeg',
  };

  // ── Combat form references ───────────────────────────────────────────────

  const form        = document.getElementById('combat-form');
  const fireBtn     = document.getElementById('fire-btn');
  const clearBtn    = document.getElementById('clear-btn');
  const fieldShots  = document.getElementById('field-shots');
  const fieldBursts = document.getElementById('field-bursts');
  const fireLog     = document.getElementById('fire-log');

  // ── Fire log state ───────────────────────────────────────────────────────
  let _logEvents   = [];          // last poll result, used for re-render on toggle
  let _expandedIds = new Set();   // event IDs currently expanded
  let _initialLoad = true;        // auto-expand newest event on first fetch

  // ── localStorage state ───────────────────────────────────────────────────

  function defaultSP() {
    return { head: 0, torso: 0, rightArm: 0, leftArm: 0, rightLeg: 0, leftLeg: 0 };
  }

  function defaultDamage() {
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
    const state  = loadState();
    const target = state.targets.find(t => t.id === state.activeTargetId);
    if (!target) return;
    target.sp = finalSP;
    saveState(state);
    // renderTargets() is deferred to updateActiveDamage which always follows
  }

  // Computes per-location passthrough damage from a combat response.
  function computeRunDamage(data) {
    const runDmg = defaultDamage();

    function tally(shot) {
      if (shot.hit === false || !shot.location) return;
      const key = LOCATION_KEY_MAP[shot.location];
      if (!key) return;
      if (shot.armor) {
        if (shot.armor.penetrated) runDmg[key] += shot.armor.passthrough;
      } else {
        // No armor tracked — full raw damage goes through
        runDmg[key] += shot.rawDamage || 0;
      }
    }

    if (data.mode === 'single' || data.mode === 'auto') {
      (data.shots  || []).forEach(tally);
    } else if (data.mode === 'burst') {
      (data.bursts || []).forEach(burst => (burst.bullets || []).forEach(tally));
    }

    return runDmg;
  }

  // Adds runDamage to the active target's cumulative damage and stores
  // runDamage as lastRunDamage so the per-run delta can be displayed.
  function updateActiveDamage(runDamage) {
    const state  = loadState();
    const target = state.targets.find(t => t.id === state.activeTargetId);
    if (!target) return;
    if (!target.damage) target.damage = defaultDamage();
    SP_LOCATIONS.forEach(loc => {
      target.damage[loc.key] = (target.damage[loc.key] || 0) + (runDamage[loc.key] || 0);
    });
    target.lastRunDamage = { ...runDamage };
    saveState(state);
    renderTargets();
  }

  // ── Target CRUD ──────────────────────────────────────────────────────────

  function addTarget(name, isGeneric) {
    const state        = loadState();
    const maxGeneric = state.targets.filter(t =>  t.generic)
      .reduce((max, t) => { const m = t.name.match(/^Generic (\d+)$/); return m ? Math.max(max, +m[1]) : max; }, 0);
    const maxUnique  = state.targets.filter(t => !t.generic)
      .reduce((max, t) => { const m = t.name.match(/^Unique (\d+)$/);  return m ? Math.max(max, +m[1]) : max; }, 0);
    const autoName   = name.trim() ||
      (isGeneric ? `Generic ${maxGeneric + 1}` : `Unique ${maxUnique + 1}`);

    const target = { id: 't' + Date.now(), name: autoName, generic: isGeneric };
    target.sp            = isGeneric ? { ...state.genericSP } : defaultSP();
    target.damage        = defaultDamage();
    target.lastRunDamage = defaultDamage();

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
        .forEach(el => { el.value = value; el.classList.toggle('sp-input-zero', value === 0); });
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

  // ── Clear button — collapse all expanded rows ─────────────────────────────

  clearBtn.addEventListener('click', () => {
    _expandedIds.clear();
    renderFireLog(_logEvents);
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

    // Include active target's current SP and name if one is selected
    const activeSP = getActiveTargetSP();
    if (activeSP !== null) payload.armorSP = activeSP;
    const _state    = loadState();
    const _activeTgt = _state.targets.find(t => t.id === _state.activeTargetId);
    if (_activeTgt) payload.targetName = _activeTgt.name;

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

      // Track passthrough damage on the active target (clears previous run delta too)
      const activeId = loadState().activeTargetId;
      if (activeId) updateActiveDamage(computeRunDamage(data));

      fetchFireLog(true);

    } catch (err) {
      renderError('Could not reach the server. Is PHP running? (php -S localhost:8000)');
    } finally {
      fireBtn.disabled    = false;
      fireBtn.textContent = 'FIRE';
    }
  });

  // ── Fire log — polling & rendering ──────────────────────────────────────

  const POLL_MS = 3000;

  // Extracts HH:MM:SS from an ISO timestamp string (e.g. "2026-03-02T14:30:45Z").
  // Slices the string directly to avoid timezone misinterpretation.
  function firedAtTime(firedAt) {
    const t = firedAt.indexOf('T');
    return t !== -1 ? firedAt.slice(t + 1, t + 9) : firedAt;
  }

  // Builds the tally portion of a log row header.
  function buildLogTally(ev) {
    if (ev.mode === 'burst') {
      const bulletNote = ev.total_bullets > 0
        ? `&ensp;<span class="tally-total">${ev.total_bullets} bullet${ev.total_bullets !== 1 ? 's' : ''} landed</span>`
        : '';
      return `<span class="tally-hits">${ev.hits}&nbsp;BURST${ev.hits !== 1 ? 'S' : ''}&nbsp;HIT</span>` +
             `&ensp;/&ensp;` +
             `<span class="tally-misses">${ev.misses}&nbsp;MISS${ev.misses !== 1 ? 'ES' : ''}</span>` +
             `&ensp;<span class="tally-total">(${ev.total_shots} burst${ev.total_shots !== 1 ? 's' : ''})</span>` +
             bulletNote;
    }
    return `<span class="tally-hits">${ev.hits}&nbsp;HIT${ev.hits !== 1 ? 'S' : ''}</span>` +
           `&ensp;/&ensp;` +
           `<span class="tally-misses">${ev.misses}&nbsp;MISS${ev.misses !== 1 ? 'ES' : ''}</span>` +
           `&ensp;<span class="tally-total">(${ev.total_shots} shot${ev.total_shots !== 1 ? 's' : ''})</span>`;
  }

  // Renders the full list of fire events into #fire-log.
  function renderFireLog(events) {
    if (!events || events.length === 0) {
      fireLog.innerHTML = '<p class="empty-state">No fire events in the last 15 minutes.</p>';
      return;
    }

    const modeLabel = { single: 'SINGLE', auto: 'AUTO', burst: 'BURST' };
    let html = '';

    events.forEach(ev => {
      const expanded = _expandedIds.has(ev.id);
      const toggle   = expanded ? '&#x25BC;' : '&#x25B6;';
      const p        = ev.params;
      const tgt      = p.targetName ? ` · ${escapeHtml(p.targetName)}` : '';
      const params   = `${escapeHtml(p.damage)} · sk:${p.skill} dif:${p.difficulty}${tgt}`;

      html += `<div class="log-event">`;
      html += `<div class="log-row${expanded ? ' expanded' : ''}" data-id="${ev.id}">`;
      html += `<span class="log-toggle">${toggle}</span>`;
      html += `<span class="log-mode">${modeLabel[ev.mode] || escapeHtml(ev.mode).toUpperCase()}</span>`;
      html += `<span class="log-tally">${buildLogTally(ev)}</span>`;
      html += `<span class="log-params">${params}</span>`;
      html += `<span class="log-time">${firedAtTime(ev.fired_at)}</span>`;
      html += `</div>`;

      if (expanded) {
        html += `<div class="log-detail">`;
        if (ev.mode === 'single' || ev.mode === 'auto') {
          (ev.shots || []).forEach(shot => { html += renderShotCard(shot, p); });
        } else if (ev.mode === 'burst') {
          (ev.bursts || []).forEach(burst => { html += renderBurstCard(burst, p); });
        }
        html += `</div>`;
      }

      html += `</div>`; // .log-event
    });

    fireLog.innerHTML = html;
    attachLogListeners();
  }

  // Wires click-to-expand on each log row (called after every renderFireLog).
  function attachLogListeners() {
    fireLog.querySelectorAll('.log-row').forEach(row => {
      row.addEventListener('click', () => {
        const id = parseInt(row.dataset.id, 10);
        if (_expandedIds.has(id)) {
          _expandedIds.delete(id);
        } else {
          _expandedIds.add(id);
        }
        renderFireLog(_logEvents);
      });
    });
  }

  // Fetches the last 15 minutes of fire events from the server and re-renders.
  // Pass scrollToTop=true after firing to jump to the newest entry.
  async function fetchFireLog(scrollToTop = false) {
    try {
      const res  = await fetch('api/events.php');
      _logEvents = await res.json();
      if (scrollToTop || _initialLoad) {
        _initialLoad = false;
        if (_logEvents.length > 0) {
          _expandedIds.clear();
          _expandedIds.add(_logEvents[0].id);
        }
      }
      renderFireLog(_logEvents);
      if (scrollToTop) {
        const panel = document.getElementById('results-panel');
        if (panel) panel.scrollTop = 0;
      }
    } catch {
      /* Silent — poll errors don't disrupt the UI */
    }
  }

  setInterval(fetchFireLog, POLL_MS);

  function renderShotCard(shot, params) {
    const rollBreakdown = buildRollBreakdown(shot.skillRoll);
    const critBadge     = shot.skillRoll.critical
      ? ' <span class="badge-crit">CRITICAL</span>'
      : '';

    if (!shot.hit) {
      return `<div class="shot miss compact">` +
        `<span class="shot-num">SHOT ${shot.num}</span>` +
        `<span class="shot-outcome">MISS</span>` +
        `<span class="shot-roll-compact">${rollBreakdown} + ${params.skill} = ${shot.total} vs. ${params.difficulty}</span>` +
        `</div>`;
    }

    const isBlocked = shot.armor && !shot.armor.penetrated;
    const cls       = isBlocked ? 'shot hit blocked compact' : 'shot hit compact';
    const outcome   = isBlocked ? 'BLOCKED' : 'HIT';

    return `<div class="${cls}">` +
      `<span class="shot-num">SHOT ${shot.num}</span>` +
      `<span class="shot-outcome">${outcome}</span>` +
      `<span class="shot-roll-compact">${rollBreakdown} + ${params.skill} = <strong>${shot.total}</strong> vs. ${params.difficulty}${critBadge}</span>` +
      `<span class="shot-location-compact">${escapeHtml(shot.location)}</span>` +
      renderDamageCompact(shot.rawDamage, shot.armor) +
      `</div>`;
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

    const allBlocked  = burst.bullets.length > 0 && burst.bullets.every(b => b.armor && !b.armor.penetrated);
    const headerCls   = allBlocked ? 'shot hit blocked compact burst-hdr' : 'shot hit compact burst-hdr';
    const bulletsCls  = allBlocked ? 'burst-bullets blocked' : 'burst-bullets hit';
    const outcome     = allBlocked ? 'BLOCKED' : 'HIT';
    const bulletCount = `${burst.bulletCount} bullet${burst.bulletCount !== 1 ? 's' : ''}`;

    let html = `<div class="burst-group">`;

    html += `<div class="${headerCls}">`;
    html += `<span class="shot-num">BURST ${burst.num}</span>`;
    html += `<span class="shot-outcome">${outcome}</span>`;
    html += `<span class="shot-roll-compact">${rollBreakdown} + ${params.skill} = <strong>${burst.total}</strong> vs. ${params.difficulty}`;
    html += ` <span class="shot-bullet-count">${bulletCount}</span>${critBadge}</span>`;
    html += `</div>`;

    html += `<div class="${bulletsCls}">`;
    burst.bullets.forEach((bullet, i) => {
      const bulletBlocked = bullet.armor && !bullet.armor.penetrated;
      html += `<div class="bullet-hit${bulletBlocked ? ' blocked' : ''}">`;
      html += `<span class="bullet-num">Bullet ${i + 1}</span>`;
      html += `<span class="bullet-location-compact">${escapeHtml(bullet.location)}</span>`;
      html += renderDamageCompact(bullet.rawDamage, bullet.armor);
      html += `</div>`;
    });
    html += `</div></div>`;
    return html;
  }

  // Renders the damage detail as an inline span (used in compact shot and bullet rows).
  function renderDamageCompact(rawDamage, armor) {
    if (!armor) {
      return `<span class="shot-damage-compact">Dmg: <strong>${rawDamage}</strong></span>`;
    }

    if (!armor.penetrated) {
      return `<span class="shot-damage-compact">` +
        `Dmg: <strong>${rawDamage}</strong> ` +
        `<span class="armor-blocked">blocked (SP ${armor.spBefore})</span>` +
        `</span>`;
    }

    return `<span class="shot-damage-compact">` +
      `Dmg: <strong>${rawDamage}</strong> &rarr; <strong class="passthrough">${armor.passthrough}</strong> through ` +
      `<span class="armor-detail">(SP: ${armor.spBefore}&thinsp;&#x2192;&thinsp;<span class="${armor.spAfter === 0 ? 'sp-zero' : ''}">${armor.spAfter}</span>)</span>` +
      `</span>`;
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
    fireLog.innerHTML = `<div class="error-msg">${escapeHtml(msg)}</div>`;
  }

  // ── Target panel rendering ───────────────────────────────────────────────

  function renderDamageRow(target) {
    const damage     = target.damage        || defaultDamage();
    const lastRunDmg = target.lastRunDamage || defaultDamage();
    let html = `<div class="target-dmg-row">`;
    SP_LOCATIONS.forEach(loc => {
      const total    = damage[loc.key]     || 0;
      const runDelta = lastRunDmg[loc.key] || 0;
      const hasDmg   = total > 0;
      html += `<div class="dmg-field">` +
        `<label>${loc.label}</label>` +
        `<div class="dmg-value-wrap">` +
        `<span class="dmg-total${hasDmg ? ' has-damage' : ''}">${hasDmg ? total : '&mdash;'}</span>` +
        (runDelta > 0 ? `<sup class="dmg-delta">+${runDelta}</sup>` : '') +
        `</div>` +
        `</div>`;
    });
    html += `</div>`;
    return html;
  }

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
        html += renderDamageRow(target);

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
        html += renderDamageRow(target);

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

  // ── Theme cycling ────────────────────────────────────────────────────────
  // Secret: click the "// CYBERPUNK 2020" header text to cycle themes.
  // Active theme is persisted in a session cookie (cleared when browser closes).

  const THEMES      = ['', 'theme-neural', 'theme-grit', 'theme-phosphor'];
  const THEME_COOKIE = 'cp2020theme';

  function currentThemeIndex() {
    for (let i = 1; i < THEMES.length; i++) {
      if (document.documentElement.classList.contains(THEMES[i])) return i;
    }
    return 0;
  }

  function applyTheme(theme) {
    document.documentElement.classList.remove(...THEMES.filter(Boolean));
    if (theme) document.documentElement.classList.add(theme);
    document.cookie = THEME_COOKIE + '=' + encodeURIComponent(theme) + ';path=/';
  }

  const headerSub = document.querySelector('.header-sub');
  if (headerSub) {
    headerSub.addEventListener('click', () => {
      applyTheme(THEMES[(currentThemeIndex() + 1) % THEMES.length]);
    });
  }

  // ── Initialise ───────────────────────────────────────────────────────────

  updateFireBtnText();
  renderTargets();
  fetchFireLog();
});
