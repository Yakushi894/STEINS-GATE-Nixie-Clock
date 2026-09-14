<?php
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ダイバージェンスメーター風時計</title>
  <style>
    @font-face {
      font-family: "BO NX Frame";
      src: url("font/BONX-Frame.otf") format("opentype");
      font-display: block;
    }
    @font-face {
      font-family: "BO NX Medium";
      src: url("font/BONX-Medium.otf") format("opentype");
      font-display: block;
    }
    @font-face {
      font-family: "BO NX Silhouette";
      src: url("font/BONX-Silhouette.otf") format("opentype");
      font-display: block;
    }
    @font-face {
      font-family: "BO NX Tube Medium";
      src: url("font/BONX-TubeMedium.otf") format("opentype");
      font-display: block;
    }
    @font-face {
      font-family: "BO NX Tube Medium Reverse";
      src: url("font/BONX-TubeMediumReverse.otf") format("opentype");
      font-display: block;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      width: 100%;
      height: 100%;
      background: #000;
      overflow: hidden;
    }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .meter-wrap {
      width: 94vw;
      height: calc(100vh - 48px);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .tubes {
      display: flex;
      align-items: center;
      justify-content: center;
      transform-origin: center center;
      font-size: 100px;
      line-height: 1.7;
    }

    .tube {
      display: grid;
      grid-template: 1.7em / 1.05em;
      width: 1.05em;
      height: 1.7em;
      line-height: 1.7;
      font-kerning: none;
      font-variant-ligatures: none;
    }

    .layer {
      grid-area: 1 / 1;
      width: 1.05em;
      height: 1.7em;
      line-height: 1.7;
      text-align: center;
      overflow: hidden;
    }

    .silhouette {
      font-family: "BO NX Silhouette", sans-serif;
      color: #2a1204;
      z-index: 1;
    }

    .tube-rev {
      font-family: "BO NX Tube Medium Reverse", sans-serif;
      color: rgba(255, 90, 10, 0.16);
      z-index: 2;
    }

    .medium {
      font-family: "BO NX Medium", sans-serif;
      color: #ff7a18;
      z-index: 3;
      text-shadow:
        0 0 4px #ff9a30,
        0 0 10px #ff6a00,
        0 0 22px #ff4500,
        0 0 44px #cc3300;
    }

    .tube-body {
      font-family: "BO NX Tube Medium", sans-serif;
      color: rgba(255, 186, 96, 0.5);
      z-index: 4;
    }

    .frame {
      font-family: "BO NX Frame", sans-serif;
      color: rgba(255, 170, 80, 0.3);
      z-index: 5;
    }

    .tube.sep .silhouette,
    .tube.sep .tube-rev {
      visibility: hidden;
    }

    .tube.spinning .medium {
      color: #ffb060;
      text-shadow: 0 0 3px #ffcc88, 0 0 8px #ff6a00, 0 0 18px #ff4500;
    }

    .tube.locking .medium {
      animation: lockFlash 0.18s ease-out;
    }

    @keyframes lockFlash {
      0%   { color: #fff4d0; text-shadow: 0 0 8px #fff, 0 0 24px #ffaa44, 0 0 48px #ff6600; }
      100% { color: #ff7a18; }
    }

    .hint {
      position: fixed;
      bottom: 10px;
      left: 0;
      right: 0;
      text-align: center;
      font-family: sans-serif;
      font-size: 11px;
      color: #4a3300;
      line-height: 1.7;
    }
    .hint button {
      font-family: sans-serif;
      font-size: 11px;
      color: #8a6a20;
      background: transparent;
      border: 0;
      cursor: pointer;
      text-decoration: underline;
      text-underline-offset: 3px;
    }
  </style>
</head>
<body>
  <div class="meter-wrap" id="meterWrap">
    <div class="tubes" id="tubes"></div>
  </div>

  <div class="hint">
    <button type="button" id="modeBtn">時計 0•時分秒</button>
    ·
    <button type="button" id="resyncBtn">NTP再同期</button>
    ·
    <button type="button" id="rerollBtn">再回転</button>
    <div id="status">時刻同期中…</div>
  </div>

  <script>
    const NTP_HOSTS = [
      'ntp.nict.jp',
      'ntp.jst.mfeed.ad.jp',
      'jp.pool.ntp.org',
      'time.google.com',
      'time.cloudflare.com'
    ];

    const wrapEl = document.getElementById('meterWrap');
    const tubesEl = document.getElementById('tubes');
    const btn = document.getElementById('modeBtn');
    const statusEl = document.getElementById('status');

    let mode = 0;
    const modes = ['時計 0•時分秒', '時計 時•分•秒', '世界線変動率'];

    let spinning = false;
    let clockTimer = null;
    let spinTimer = null;
    let cachedDiv = randomDivergence();
    let lastMin = -1;
    let slots = [];
    let timeOffset = 0;
    let lastSync = { host: '-', source: 'local', rttMs: null };

    function pickHost() {
      return NTP_HOSTS[Math.floor(Math.random() * NTP_HOSTS.length)];
    }

    function nowDate() {
      return new Date(Date.now() + timeOffset);
    }

    async function syncNtp() {
      const host = pickHost();
      const t0 = Date.now();
      statusEl.textContent = 'NTP問い合わせ中… ' + host;
      try {
        const res = await fetch('ntp.php?host=' + encodeURIComponent(host) + '&_=' + t0, { cache: 'no-store' });
        const t1 = Date.now();
        const data = await res.json();
        const mid = (t0 + t1) / 2;
        if (data && data.nowMs) {
          timeOffset = data.nowMs - mid;
          lastSync = {
            host: data.host || host,
            source: data.source || 'ntp',
            rttMs: data.rttMs != null ? data.rttMs : (t1 - t0)
          };
        } else {
          throw new Error('empty');
        }
      } catch (e) {
        timeOffset = 0;
        lastSync = { host: host, source: 'local-fallback', rttMs: null };
      }
      renderStatus();
    }

    function renderStatus() {
      const off = Math.round(timeOffset);
      const rtt = lastSync.rttMs != null ? Math.round(lastSync.rttMs) + 'ms' : '-';
      statusEl.textContent = lastSync.source + ' / ' + lastSync.host + ' / RTT ' + rtt + ' / 補正 ' + off + 'ms';
    }

    function pad(n, len) {
      len = len || 2;
      return String(n).padStart(len, '0');
    }

    function randomDivergence() {
      const head = Math.random() < 0.12 ? '1' : '0';
      let frac = '';
      for (let i = 0; i < 6; i++) frac += Math.floor(Math.random() * 10);
      return head + '•' + frac;
    }

    function formatTime() {
      const d = nowDate();
      const h = pad(d.getHours());
      const m = pad(d.getMinutes());
      const s = pad(d.getSeconds());

      if (mode === 0) return '0•' + h + m + s;
      if (mode === 1) return h + '•' + m + '•' + s;

      if (d.getMinutes() !== lastMin) {
        lastMin = d.getMinutes();
        cachedDiv = randomDivergence();
      }
      return cachedDiv;
    }

    function randDigit() {
      return String(Math.floor(Math.random() * 10));
    }

    function setSlot(slot, ch) {
      slot.sil.textContent = ch;
      slot.tubeRev.textContent = ch;
      slot.medium.textContent = ch;
      slot.tubeBody.textContent = ch;
      slot.frame.textContent = ch;
    }

    function makeLayer(cls, ch) {
      const el = document.createElement('div');
      el.className = 'layer ' + cls;
      el.textContent = ch;
      return el;
    }

    function buildTubes(text) {
      tubesEl.innerHTML = '';
      slots = [];
      for (let i = 0; i < text.length; i++) {
        const ch = text.charAt(i);
        const tube = document.createElement('div');
        tube.className = 'tube' + (ch === '•' ? ' sep' : '');

        const sil = makeLayer('silhouette', ch);
        const tubeRev = makeLayer('tube-rev', ch);
        const medium = makeLayer('medium', ch);
        const tubeBody = makeLayer('tube-body', ch);
        const frame = makeLayer('frame', ch);

        tube.appendChild(sil);
        tube.appendChild(tubeRev);
        tube.appendChild(medium);
        tube.appendChild(tubeBody);
        tube.appendChild(frame);
        tubesEl.appendChild(tube);

        slots.push({
          tube: tube,
          sil: sil,
          tubeRev: tubeRev,
          medium: medium,
          tubeBody: tubeBody,
          frame: frame,
          locked: false,
          isSep: ch === '•'
        });
      }
      fitToPage();
    }

    function applyText(text) {
      if (slots.length !== text.length) {
        buildTubes(text);
        return;
      }
      for (let i = 0; i < text.length; i++) setSlot(slots[i], text.charAt(i));
    }

    function fitToPage() {
      tubesEl.style.transform = 'none';
      const box = wrapEl.getBoundingClientRect();
      const meter = tubesEl.getBoundingClientRect();
      if (!meter.width || !meter.height) return;
      const scale = Math.min(box.width / meter.width, box.height / meter.height);
      tubesEl.style.transform = 'scale(' + (scale * 0.96) + ')';
    }

    function startClock() {
      if (clockTimer) clearInterval(clockTimer);
      clockTimer = setInterval(function () {
        if (spinning) return;
        applyText(formatTime());
      }, 200);
    }

    function rollTo(target) {
      spinning = true;
      if (spinTimer) clearInterval(spinTimer);

      buildTubes(target);
      for (let i = 0; i < slots.length; i++) {
        slots[i].locked = slots[i].isSep;
        slots[i].tube.classList.toggle('spinning', !slots[i].isSep);
      }

      const spinStart = performance.now();
      const minSpin = 700;
      const stagger = 160;

      spinTimer = setInterval(function () {
        const now = performance.now();
        let allLocked = true;

        for (let i = 0; i < slots.length; i++) {
          const slot = slots[i];
          if (slot.isSep) continue;

          const lockAt = spinStart + minSpin + i * stagger;
          if (!slot.locked && now >= lockAt) {
            slot.locked = true;
            slot.tube.classList.remove('spinning');
            slot.tube.classList.add('locking');
            setSlot(slot, target.charAt(i));
            (function (el) {
              setTimeout(function () { el.classList.remove('locking'); }, 200);
            })(slot.tube);
          }

          if (!slot.locked) {
            allLocked = false;
            setSlot(slot, randDigit());
          }
        }

        if (allLocked) {
          clearInterval(spinTimer);
          spinTimer = null;
          spinning = false;
          applyText(formatTime());
        }
      }, 28);
    }

    btn.addEventListener('click', function () {
      mode = (mode + 1) % 3;
      btn.textContent = modes[mode];
      lastMin = -1;
      if (mode === 2) cachedDiv = randomDivergence();
      rollTo(formatTime());
    });

    document.getElementById('resyncBtn').addEventListener('click', async function () {
      await syncNtp();
      rollTo(formatTime());
    });

    document.getElementById('rerollBtn').addEventListener('click', function () {
      rollTo(formatTime());
    });

    window.addEventListener('resize', fitToPage);

    (async function init() {
      await document.fonts.ready;
      await syncNtp();
      rollTo(formatTime());
      startClock();
      requestAnimationFrame(fitToPage);
      setInterval(syncNtp, 10 * 60 * 1000);
    })();
  </script>
</body>
</html>
