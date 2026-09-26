<?php
/**
 * canliyayin.php — Canlı Yayın İzleme Sayfası
 * TV8 canlı yayını oynatır.
 */
require_once __DIR__ . '/config.php';

$channels = [
    ['id'=>'tv8', 'name'=>'TV8', 'desc'=>'TV8 Canlı Yayın', 'logo'=>'📺', 'm3u8'=>'https://tv8-live.daioncdn.net/tv8/tv8_1080p.m3u8'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Canlı Yayınlar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  :root {
    --bg:#0a0e1a; --card:#1e293b; --border:rgba(148,163,184,.12);
    --text:#f1f5f9; --text-dim:#94a3b8; --primary:#6366f1; --primary-2:#8b5cf6;
    --danger:#ef4444; --success:#10b981;
  }
  body {
    font-family:'Inter',sans-serif; background:var(--bg); color:var(--text);
    min-height:100vh; padding:1rem;
    background-image:linear-gradient(rgba(10,14,26,.92),rgba(10,14,26,.96)), url('https://i.hizliresim.com/loreuqk4.jpg');
    background-size:cover; background-position:center; background-attachment:fixed;
  }
  .container { max-width:1400px; margin:0 auto; }
  .header {
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;
    margin-bottom:1.5rem; padding:1rem 1.25rem; background:rgba(30,41,59,.85);
    backdrop-filter:blur(20px); border:1px solid var(--border); border-radius:1rem;
  }
  .header h1 { font-size:1.2rem; font-weight:800; display:flex; align-items:center; gap:.5rem; }
  .header h1 span { color:var(--danger); }
  .btn { background:var(--card); border:1px solid var(--border); border-radius:.6rem; padding:.5rem 1rem; cursor:pointer; font-weight:600; font-size:.82rem; color:var(--text); font-family:inherit; }
  .btn:hover { background:#334155; }
  .btn.danger { color:#fca5a5; border-color:rgba(239,68,68,.35); }
  .btn.primary { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white; border:none; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:1rem; margin-bottom:1.5rem; }
  .channel-card {
    background:rgba(30,41,59,.85); backdrop-filter:blur(20px); border:1px solid var(--border);
    border-radius:1rem; overflow:hidden; cursor:pointer; transition:all .2s;
  }
  .channel-card:hover { transform:translateY(-3px); border-color:var(--primary); box-shadow:0 10px 30px rgba(99,102,241,.2); }
  .channel-card.active { border-color:var(--primary); box-shadow:0 0 0 2px var(--primary); }
  .channel-thumb {
    width:100%; height:160px; background:linear-gradient(135deg,#1e293b,#0f172a);
    display:flex; align-items:center; justify-content:center; font-size:3rem; position:relative;
  }
  .channel-thumb::after { content:''; position:absolute; inset:0; background:linear-gradient(180deg,transparent 50%,rgba(0,0,0,.6)); }
  .channel-logo { position:absolute; bottom:10px; left:10px; right:10px; font-size:.9rem; font-weight:800; z-index:2; }
  .channel-info { padding:.75rem 1rem; }
  .channel-name { font-weight:700; font-size:.9rem; }
  .channel-desc { font-size:.72rem; color:var(--text-dim); margin-top:.2rem; }
  .player-wrap {
    background:rgba(30,41,59,.85); backdrop-filter:blur(20px); border:1px solid var(--border);
    border-radius:1rem; overflow:hidden; margin-bottom:1.5rem; display:none;
  }
  .player-wrap.active { display:block; }
  .player-header {
    padding:.85rem 1.25rem; border-bottom:1px solid var(--border);
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem;
  }
  .player-title { font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.5rem; }
  .player-title .live-dot { width:8px; height:8px; background:var(--danger); border-radius:50%; animation:pulse 1.5s infinite; }
  @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }
  .player-body { position:relative; background:#000; width:100%; aspect-ratio:16/9; }
  .player-body video { width:100%; height:100%; display:block; background:#000; }
  .player-loading {
    position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    background:rgba(0,0,0,.7); color:var(--text-dim); font-size:.9rem; flex-direction:column; gap:.75rem;
  }
  .spinner { width:32px; height:32px; border:3px solid rgba(255,255,255,.15); border-top-color:var(--primary); border-radius:50%; animation:spin .8s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }
  .toast {
    position:fixed; bottom:24px; right:24px; padding:.85rem 1.2rem; background:var(--card);
    border:1px solid var(--primary); border-radius:.75rem; color:var(--text); font-size:.85rem;
    font-weight:600; opacity:0; transform:translateY(12px); transition:all .3s; pointer-events:none; z-index:999;
  }
  .toast.show { opacity:1; transform:translateY(0); }
  .toast.error { border-color:var(--danger); }
  .empty-state { text-align:center; padding:3rem 1rem; color:var(--text-dim); }
</style>
</head>
<body>

<div class="container">
  <div class="header">
    <h1>📺 Canlı Yayınlar <span>●</span></h1>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <button class="btn" onclick="loadChannels()">🔄 Yenile</button>
      <button class="btn primary" onclick="location.href='index.html'">🏠 Ana Sayfa</button>
      <button class="btn danger" onclick="stopPlayer()">⏹️ Durdur</button>
    </div>
  </div>

  <div class="player-wrap" id="playerWrap">
    <div class="player-header">
      <div class="player-title" id="playerTitle">Kanal</div>
      <button class="btn danger" onclick="stopPlayer()">✕ Kapat</button>
    </div>
    <div class="player-body" id="playerBody"></div>
  </div>

  <div class="grid" id="channelsGrid"></div>
</div>

<div class="toast" id="toast"></div>

<script>
const CHANNELS = <?php echo json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

let currentPlayer = null;

const toastEl = document.getElementById('toast');
function toast(msg, type='') {
  toastEl.textContent = msg;
  toastEl.className = 'toast show ' + type;
  clearTimeout(toastEl._t);
  toastEl._t = setTimeout(() => toastEl.className = 'toast', 2500);
}

function loadChannels() {
  const grid = document.getElementById('channelsGrid');
  let html = '';
  for (const ch of CHANNELS) {
    html += `
      <div class="channel-card" id="card_${ch.id}" onclick="playChannel('${ch.id}')">
        <div class="channel-thumb">
          <div style="font-size:3rem;">${ch.logo}</div>
          <div class="channel-logo">${ch.name}</div>
        </div>
        <div class="channel-info">
          <div class="channel-name">${ch.name}</div>
          <div class="channel-desc">${ch.desc}</div>
        </div>
      </div>`;
  }
  grid.innerHTML = html || '<div class="empty-state">Kanal yok</div>';
}

function stopPlayer() {
  const wrap = document.getElementById('playerWrap');
  const body = document.getElementById('playerBody');
  wrap.classList.remove('active');
  body.innerHTML = '';
  currentPlayer = null;
  document.querySelectorAll('.channel-card').forEach(c => c.classList.remove('active'));
}

function playChannel(id) {
  const ch = CHANNELS.find(c => c.id === id);
  if (!ch) return;

  document.querySelectorAll('.channel-card').forEach(c => c.classList.remove('active'));
  document.getElementById('card_' + id)?.classList.add('active');

  const wrap = document.getElementById('playerWrap');
  const body = document.getElementById('playerBody');
  const title = document.getElementById('playerTitle');

  title.innerHTML = `<span class="live-dot"></span> ${ch.name} — Canlı`;
  wrap.classList.add('active');
  body.innerHTML = '<div class="player-loading"><div class="spinner"></div><span>Yükleniyor...</span></div>';

  playM3U8(ch);
}

function playM3U8(ch) {
  const body = document.getElementById('playerBody');
  const video = document.createElement('video');
  video.controls = true;
  video.autoplay = true;
  video.playsInline = true;
  video.setAttribute('webkit-playsinline', 'true');

  if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = ch.m3u8;
    body.innerHTML = '';
    body.appendChild(video);
    video.play().catch(() => toast('Oynatmak için tıkla', 'error'));
    currentPlayer = video;
    return;
  }

  if (window.Hls && Hls.isSupported()) {
    const hls = new Hls({ enableWorker: true, lowLatencyMode: true, backBufferLength: 90 });
    hls.loadSource(ch.m3u8);
    hls.attachMedia(video);
    hls.on(Hls.Events.MANIFEST_PARSED, () => {
      video.play().catch(() => console.warn('Autoplay engellendi'));
    });
    hls.on(Hls.Events.ERROR, (event, data) => {
      if (data.fatal) {
        switch (data.type) {
          case Hls.ErrorTypes.NETWORK_ERROR: hls.startLoad(); break;
          case Hls.ErrorTypes.MEDIA_ERROR:   hls.recoverMediaError(); break;
          default: toast('Yayın oynatılamadı', 'error'); hls.destroy(); break;
        }
      }
    });
    body.innerHTML = '';
    body.appendChild(video);
    video.play().catch(() => console.warn('Autoplay engellendi'));
    currentPlayer = video;
    return;
  }

  body.innerHTML = `<iframe src="${ch.m3u8}" allowfullscreen allow="autoplay; encrypted-media" style="width:100%;height:100%;border:none;"></iframe>`;
}

function loadHls(callback) {
  if (window.Hls) { callback(); return; }
  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js';
  script.onload = callback;
  script.onerror = () => toast('HLS kütüphanesi yüklenemedi', 'error');
  document.head.appendChild(script);
}

loadHls(() => { loadChannels(); });
loadChannels();

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') stopPlayer(); });
</script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js"></script>
</body>
</html>