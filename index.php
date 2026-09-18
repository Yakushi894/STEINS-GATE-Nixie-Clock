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
      overflow: hidden;
      background-color: #070300;
      background-image:
        linear-gradient(rgba(255, 120, 40, 0.045) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 120, 40, 0.045) 1px, transparent 1px),
        radial-gradient(ellipse at center, rgba(40, 12, 0, 0.35) 0%, rgba(0, 0, 0, 0.88) 72%);
      background-size: 28px 28px, 28px 28px, 100% 100%;
      background-position: center center, center center, center;
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
      align-items: flex-end;
      justify-content: center;
      transform-origin: center center;
      font-size: 120px;
      line-height: 1;
      filter:
        drop-shadow(0 0 10px rgba(255, 110, 20, 0.45))
        drop-shadow(0 0 28px rgba(255, 70, 0, 0.28));
    }

    /* Frame/Medium/Silhouette は全て advance 520/1000em。左揃えで原点を一致させる */
    .tube {
      position: relative;
      width: 0.52em;
      height: 1.7em;
      font-kerning: none;
      font-variant-ligatures: none;
    }

    .layer {
      position: absolute;
      left: 0;
      bottom: 0.2em;
      width: 0.52em;
      height: 1em;
      line-height: 1;
      text-align: left;
      font-size: 1em;
    }

    .bloom {
      font-family: "BO NX Medium", sans-serif;
      color: #ff7a18;
      z-index: 1;
      filter: blur(16px);
      opacity: 0.5;
      pointer-events: none;
    }

    .silhouette {
      font-family: "BO NX Silhouette", sans-serif;
      color: #3a1806;
      z-index: 2;
      transform: translateY(-0.013em);
    }

    .medium {
      font-family: "BO NX Medium", sans-serif;
      color: #ff861c;
      z-index: 3;
      text-shadow:
        0 0 6px #ffb24a,
        0 0 14px #ff6a00,
        0 0 28px #ff4500,
        0 0 56px rgba(204, 51, 0, 0.8);
    }

    .frame {
      font-family: "BO NX Frame", sans-serif;
      color: rgba(255, 176, 88, 0.34);
      z-index: 4;
    }

    .tube.sep .silhouette,
    .tube.sep .bloom {
      visibility: hidden;
    }

    .tube.spinning .medium {
      color: #ffc070;
    }

    .tube.locking .medium,
    .tube.locking .bloom {
      animation: lockFlash 0.2s ease-out;
    }

    @keyframes lockFlash {
      0%   { color: #fff3d0; filter: blur(8px); }
      100% { color: #ff861c; }
    }

    .hint {
      position: fixed;
      bottom: 10px;
      left: 0;
      right: 0;
      text-align: center;
      font-family: sans-serif;
      font-size: 11px;
      color: #5a3a10;
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
      slot.bloom.textContent = ch;
      slot.sil.textContent = '0';
      slot.medium.textContent = ch;
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

        const bloom = makeLayer('bloom', ch);
        const sil = makeLayer('silhouette', '0');
        const medium = makeLayer('medium', ch);
        const frame = makeLayer('frame', ch);

        tube.appendChild(bloom);
        tube.appendChild(sil);
        tube.appendChild(medium);
        tube.appendChild(frame);
        tubesEl.appendChild(tube);

        slots.push({
          tube: tube,
          bloom: bloom,
          sil: sil,
          medium: medium,
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
      tubesEl.style.transform = 'scale(' + (scale * 0.92) + ')';
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
      setTimeout(fitToPage, 80);
      setInterval(syncNtp, 10 * 60 * 1000);
    })();
  </script>
</body>
</html>
