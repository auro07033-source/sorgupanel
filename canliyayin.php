<?php
/**
 * canliyayin.php — Canlı Yayın İzleme + Proxy
 */
require_once __DIR__ . '/config.php';

$channels = [
    ['id'=>'tv8',      'name'=>'TV8',       'desc'=>'TV8 Canlı Yayın',       'logo'=>'📺', 'page'=>'https://www.tv8.com.tr/canli-yayin',       'm3u8'=>'https://tv8-live.daioncdn.net/tv8/tv8_1080p.m3u8'],
    ['id'=>'atv',      'name'=>'ATV',       'desc'=>'ATV Canlı Yayın',       'logo'=>'📺', 'page'=>'https://www.atv.com.tr/canli-yayin',        'm3u8'=>'https://trkvz.daioncdn.net/atv/atv_720p.m3u8'],
    ['id'=>'showturk', 'name'=>'Show TV',   'desc'=>'Show TV Canlı Yayın',   'logo'=>'📺', 'page'=>'https://www.showtv.com.tr/canli-yayin',     'm3u8'=>'https://ciner-live.ercdn.net/showturk/showturk_720p.m3u8'],
    ['id'=>'startv',   'name'=>'Star TV',   'desc'=>'Star TV Canlı Yayın',   'logo'=>'📺', 'page'=>'https://www.startv.com.tr/canli-yayin',     'm3u8'=>'https://dogus.daioncdn.net/startv/startv_720p.m3u8'],
    ['id'=>'cnnturk',  'name'=>'CNN Türk',  'desc'=>'CNN Türk Canlı Yayın',  'logo'=>'📰', 'page'=>'https://www.cnnturk.com/canli-yayin',       'm3u8'=>'https://live.duhnet.tv/S2/HLS_LIVE/cnnturknp/track_4_1000/playlist.m3u8'],
    ['id'=>'ahaber',   'name'=>'A Haber',   'desc'=>'A Haber Canlı Yayın',   'logo'=>'📰', 'page'=>'https://www.ahaber.com.tr/canli-yayin',     'm3u8'=>'https://trkvz.daioncdn.net/ahaber/ahaber_720p.m3u8'],
    ['id'=>'trt1',     'name'=>'TRT 1',     'desc'=>'TRT 1 Canlı Yayın',     'logo'=>'📺', 'page'=>'https://www.trt1.com.tr/canli-yayin',       'm3u8'=>'https://tv-trt1.medya.trt.com.tr/master.m3u8'],
    ['id'=>'fox',      'name'=>'FOX TV',    'desc'=>'FOX TV Canlı Yayın',    'logo'=>'📺', 'page'=>'https://www.fox.com.tr/canli-yayin',        'm3u8'=>'https://fox-live.daioncdn.net/fox/fox_720p.m3u8'],
    ['id'=>'tv100',    'name'=>'TV100',     'desc'=>'TV100 Canlı Yayın',     'logo'=>'📰', 'page'=>'https://www.tv100.com/canli-yayin',         'm3u8'=>'https://tv100-live.daioncdn.net/tv100/tv100_720p.m3u8'],
    ['id'=>'haberturk','name'=>'Habertürk', 'desc'=>'Habertürk Canlı Yayın', 'logo'=>'📰', 'page'=>'https://www.haberturk.com/canli-yayin',     'm3u8'=>'https://haberturk-live.daioncdn.net/haberturk/haberturk_720p.m3u8'],
];

// ═══════════ PROXY ═══════════
if (isset($_GET['proxy'])) {
    $url = base64_decode($_GET['proxy'] ?? '');
    if (!$url || !preg_match('#^https?://#i', $url)) {
        http_response_code(400); exit('Bad URL');
    }

    // Güvenlik: sadece izinli domainler
    $allowed = ['daioncdn.net','ercdn.net','duhnet.tv','trt.com.tr','medya.trt.com.tr','litix.io','akamaized.net','ciner.com.tr'];
    $host = parse_url($url, PHP_URL_HOST);
    $ok = false;
    foreach ($allowed as $a) if (stripos($host, $a) !== false) { $ok = true; break; }
    if (!$ok) { http_response_code(403); exit('Domain not allowed'); }

    // İmzalı URL ise taze token al (referer gönder)
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Referer: https://www.tv8.com.tr/',
            'Origin: https://www.tv8.com.tr',
            'Accept: */*',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
    curl_close($ch);

    if ($code !== 200 || !$body) { http_response_code($code ?: 502); exit('Proxy error'); }

    header('Access-Control-Allow-Origin: *');
    header('Content-Type: ' . $type);

    // m3u8 ise içindeki segment URL'lerini de proxy'le
    if (stripos($type, 'mpegurl') !== false || preg_match('/\.m3u8/i', $url)) {
        $base = substr($url, 0, strrpos($url, '/') + 1);
        $lines = explode("\n", $body);
        foreach ($lines as &$l) {
            $l = rtrim($l, "\r");
            if ($l === '' || $l[0] === '#') continue;
            if (!preg_match('#^https?://#i', $l)) {
                if (preg_match('#^//#', $l)) $l = 'https:' . $l;
                else $l = $base . $l;
            }
            $l = 'proxy.php?proxy=' . base64_encode($l);
        }
        echo implode("\n", $lines);
    } else {
        echo $body;
    }
    exit;
}
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
  :root { --bg:#0a0e1a; --card:#1e293b; --border:rgba(148,163,184,.12);
    --text:#f1f5f9; --text-dim:#94a3b8; --primary:#6366f1; --danger:#ef4444; }
  body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding:1rem;
    background-image:linear-gradient(rgba(10,14,26,.92),rgba(10,14,26,.96)), url('https://i.hizliresim.com/loreuqk4.jpg');
    background-size:cover; background-position:center; background-attachment:fixed; }
  .container { max-width:1400px; margin:0 auto; }
  .header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;
    margin-bottom:1.5rem; padding:1rem 1.25rem; background:rgba(30,41,59,.85);
    backdrop-filter:blur(20px); border:1px solid var(--border); border-radius:1rem; }
  .header h1 { font-size:1.2rem; font-weight:800; display:flex; align-items:center; gap:.5rem; }
  .header h1 span { color:var(--danger); }
  .btn { background:var(--card); border:1px solid var(--border); border-radius:.6rem; padding:.5rem 1rem; cursor:pointer; font-weight:600; font-size:.82rem; color:var(--text); font-family:inherit; }
  .btn:hover { background:#334155; }
  .btn.danger { color:#fca5a5; border-color:rgba(239,68,68,.35); }
  .btn.primary { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white; border:none; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1rem; margin-bottom:1.5rem; }
  .channel-card { background:rgba(30,41,59,.85); backdrop-filter:blur(20px); border:1px solid var(--border);
    border-radius:1rem; overflow:hidden; cursor:pointer; transition:all .2s; }
  .channel-card:hover { transform:translateY(-3px); border-color:var(--primary); }
  .channel-card.active { border-color:var(--primary); box-shadow:0 0 0 2px var(--primary); }
  .channel-thumb { width:100%; height:130px; background:linear-gradient(135deg,#1e293b,#0f172a);
    display:flex; align-items:center; justify-content:center; font-size:3rem; position:relative; }
  .channel-thumb::after { content:''; position:absolute; inset:0; background:linear-gradient(180deg,transparent 50%,rgba(0,0,0,.6)); }
  .channel-logo { position:absolute; bottom:10px; left:10px; right:10px; font-size:.9rem; font-weight:800; z-index:2; }
  .channel-info { padding:.75rem 1rem; }
  .channel-name { font-weight:700; font-size:.9rem; }
  .channel-desc { font-size:.72rem; color:var(--text-dim); margin-top:.2rem; }
  .player-wrap { background:rgba(30,41,59,.85); backdrop-filter:blur(20px); border:1px solid var(--border);
    border-radius:1rem; overflow:hidden; margin-bottom:1.5rem; display:none; }
  .player-wrap.active { display:block; }
  .player-header { padding:.85rem 1.25rem; border-bottom:1px solid var(--border);
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; }
  .player-title { font-weight:700; font-size:1rem; display:flex; align-items:center; gap:.5rem; }
  .player-title .live-dot { width:8px; height:8px; background:var(--danger); border-radius:50%; animation:pulse 1.5s infinite; }
  @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }
  .player-body { position:relative; background:#000; width:100%; aspect-ratio:16/9; }
  .player-body video { width:100%; height:100%; display:block; background:#000; }
  .player-loading { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    background:rgba(0,0,0,.7); color:var(--text-dim); font-size:.9rem; flex-direction:column; gap:.75rem; }
  .spinner { width:32px; height:32px; border:3px solid rgba(255,255,255,.15); border-top-color:var(--primary); border-radius:50%; animation:spin .8s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }
  .toast { position:fixed; bottom:24px; right:24px; padding:.85rem 1.2rem; background:var(--card);
    border:1px solid var(--primary); border-radius:.75rem; color:var(--text); font-size:.85rem;
    font-weight:600; opacity:0; transform:translateY(12px); transition:all .3s; pointer-events:none; z-index:999; }
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
const PROXY = (url) => 'canliyayin.php?proxy=' + btoa(url);

let currentHls = null;
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
  if (currentHls) { try { currentHls.destroy(); } catch(e){} currentHls = null; }
  if (currentPlayer) { try { currentPlayer.pause(); currentPlayer.src=''; currentPlayer.load(); } catch(e){} currentPlayer = null; }
  document.getElementById('playerWrap').classList.remove('active');
  document.getElementById('playerBody').innerHTML = '';
  document.querySelectorAll('.channel-card').forEach(c => c.classList.remove('active'));
}

function playChannel(id) {
  const ch = CHANNELS.find(c => c.id === id);
  if (!ch) return;

  stopPlayer();
  document.getElementById('card_' + id)?.classList.add('active');

  const wrap = document.getElementById('playerWrap');
  const body = document.getElementById('playerBody');
  const title = document.getElementById('playerTitle');

  title.innerHTML = `<span class="live-dot"></span> ${ch.name} — Canlı`;
  wrap.classList.add('active');
  body.innerHTML = '<div class="player-loading"><div class="spinner"></div><span>Yükleniyor...</span></div>';

  const proxied = PROXY(ch.m3u8);
  const video = document.createElement('video');
  video.controls = true;
  video.autoplay = true;
  video.muted = true;
  video.playsInline = true;
  video.setAttribute('webkit-playsinline', 'true');

  if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = proxied;
    body.innerHTML = '';
    body.appendChild(video);
    video.play().catch(() => toast('Oynatmak için tıkla', 'error'));
    currentPlayer = video;
    return;
  }

  if (window.Hls && Hls.isSupported()) {
    const hls = new Hls({ enableWorker: true, lowLatencyMode: true, backBufferLength: 90 });
    hls.loadSource(proxied);
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
    currentHls = hls;
    return;
  }

  body.innerHTML = '<div class="player-loading">Tarayıcı HLS desteklemiyor</div>';
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