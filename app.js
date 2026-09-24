/* ═══════════════════════════════════════════════════════════
   Forex Sorgulama - Uygulama Mantığı
   @cmrbaskani
   ═══════════════════════════════════════════════════════════ */

const API   = 'forexsystem.php';
const AUTH  = 'auth.php';
const CHAT  = 'chat.php';
const AIAPI = 'ai.php';
const DEFAULT_AVATAR = 'https://i.hizliresim.com/midnihxu.jpg';

// ═══════════ DEVTOOLS KORUMASI ═══════════
(function(){
  let ihlal = 0;
  function ban(){
    document.getElementById('banShield')?.classList.add('active');
    document.getElementById('authView')?.classList.add('hidden');
    document.getElementById('panelView')?.classList.add('hidden');
  }
  document.addEventListener('keydown', function(e){
    if (e.key === 'F12' || e.keyCode === 123) { e.preventDefault(); ihlal++; if (ihlal >= 3) ban(); return false; }
    if (e.ctrlKey && e.shiftKey && ['I','i','J','j','C','c'].includes(e.key)) { e.preventDefault(); ihlal++; if (ihlal >= 3) ban(); return false; }
    if (e.ctrlKey && ['U','u'].includes(e.key)) { e.preventDefault(); ihlal++; if (ihlal >= 3) ban(); return false; }
  }, true);
  document.addEventListener('contextmenu', e => e.preventDefault(), true);
})();

let CURRENT_USER = null;
let CHAT_TIMER = null;
let HEARTBEAT_TIMER = null;
let CURRENT_QUERY = 'tc';

// ═══════════ QUERIES ═══════════
const QUERIES = {
  tc:        { icon:'🆔', baslik:'TC Sorgulama',       alt:'Kimlik numarası ile kişi bilgisi', inputs:[{id:'tc', ph:'TC Kimlik No (11 hane)', max:11, label:'TC Kimlik Numarası'}], type:'tc', vip:false },
  tcpro:     { icon:'🆔', baslik:'TC Pro Sorgulama',   alt:'Detaylı kişi bilgisi',             inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'tcpro', vip:false },
  adsoyad:   { icon:'👥', baslik:'Ad Soyad Sorgulama', alt:'Ad ve soyad ile TC bulma',         inputs:[{id:'ad', ph:'Ad', label:'Ad'},{id:'soyad', ph:'Soyad', label:'Soyad'}], type:'adsoyad', vip:false },
  aile:      { icon:'👪', baslik:'Aile Sorgulama',     alt:'TC ile aile bireyleri',            inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'aile', vip:false },
  ailepro:   { icon:'👪', baslik:'Aile Pro Sorgulama', alt:'Detaylı aile bilgisi',             inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'ailepro', vip:false },
  tcgsm:     { icon:'📱', baslik:'TC → GSM Sorgulama', alt:'TC ile telefon numarası',          inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'tcgsm', vip:false },
  gsmtc:     { icon:'📞', baslik:'GSM → TC Sorgulama', alt:'Telefon ile TC',                   inputs:[{id:'gsm', ph:'GSM No (5XXXXXXXXX)', max:10, label:'GSM Numarası'}], type:'gsmtc', vip:false },
  eokul:     { icon:'🎓', baslik:'E-Okul Sorgulama',   alt:'Öğrenci bilgileri',                inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'eokul', vip:false },

  sulale:    { icon:'🌳', baslik:'Sülale Sorgulama',   alt:'Sülale kayıtları',                 inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'sulale', vip:true },
  adres:     { icon:'🏠', baslik:'Adres Sorgulama',    alt:'İkametgah adresi',                 inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'adres', vip:true },
  tapu:      { icon:'🏡', baslik:'Tapu Sorgulama',     alt:'Tapu kayıtları',                   inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'tapu', vip:true },
  adaparsel: { icon:'📐', baslik:'Ada Parsel',         alt:'İl/ilçe ile parsel',               inputs:[{id:'il', ph:'İl', label:'İl'},{id:'ilce', ph:'İlçe', label:'İlçe'}], type:'adaparsel', vip:true },

  new_adsoyad:   { icon:'🔎', baslik:'Detaylı Ad Soyad',   alt:'Ad + soyad + il',   inputs:[{id:'ad', ph:'Ad', label:'Ad'},{id:'soyad', ph:'Soyad', label:'Soyad'},{id:'il', ph:'İl (opsiyonel)', label:'İl'}], type:'new_adsoyad', vip:false },
  new_tc:        { icon:'🆔', baslik:'Detaylı TC',         alt:'TC + GSM + aile',   inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_tc', vip:false },
  new_aile:      { icon:'👪', baslik:'Detaylı Aile',       alt:'Tam aile ağacı',    inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_aile', vip:false },
  new_hane:      { icon:'🏘️', baslik:'Hane Sorgu',         alt:'Aynı hane',         inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_hane', vip:false },
  new_sulale:    { icon:'🌳', baslik:'Detaylı Sülale',     alt:'Büyükbaba+anne',    inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_sulale', vip:true },
  new_sokak:     { icon:'🛣️', baslik:'Sokak Sorgu',        alt:'Aynı sokak',        inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_sokak', vip:true },
  new_adres2009: { icon:'📍', baslik:'Eski Adres Geçmişi', alt:'2009-2024',        inputs:[{id:'tc', ph:'TC Kimlik No', max:11, label:'TC Kimlik Numarası'}], type:'new_adres2009', vip:true },
};

const INFO_TEXT = {
  tc:'TC kimlik numarası ile kişinin bilgilerini sorgular.',
  tcpro:'Detaylı kişi bilgisi.',
  adsoyad:'Ad ve soyad ile TC bulur.',
  aile:'Aile bireylerini listeler.',
  ailepro:'Detaylı aile bilgisi.',
  tcgsm:'TC ile GSM bulur.',
  gsmtc:'GSM ile TC bulur.',
  eokul:'E-okul bilgileri.',
  sulale:'💎 VIP — Sülale kayıtları.',
  adres:'💎 VIP — İkametgah adresi.',
  tapu:'💎 VIP — Tapu kayıtları.',
  adaparsel:'💎 VIP — Ada parsel sorgu.',
  new_adsoyad:'Ad+soyad+il ile toplu sorgu.',
  new_tc:'Detaylı TC bilgisi.',
  new_aile:'Tam aile ağacı.',
  new_hane:'Aynı hanedeki kişiler.',
  new_sulale:'💎 VIP — Detaylı sülale.',
  new_sokak:'💎 VIP — Sokaktaki kişiler.',
  new_adres2009:'💎 VIP — Eski adresler.',
};

// ═══════════ YARDIMCI ═══════════
const toastEl = document.getElementById('toast');
function toast(msg, type='') {
  if (!toastEl) return;
  toastEl.textContent = msg;
  toastEl.className = 'toast show ' + type;
  clearTimeout(toastEl._t);
  toastEl._t = setTimeout(() => toastEl.className = 'toast', 2200);
}
function esc(s) { return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function avatarOf(u) { const a = u && u.avatar; return (a && !a.includes('ui-avatars.com')) ? a : DEFAULT_AVATAR; }
function isVIP(u) { if (!u) return false; return u.is_vip || u.rank === 'VIP' || u.is_admin; }

// ═══════════ TEMA ═══════════
function setTheme(theme) {
  const ic = document.getElementById('themeIcon');
  const lb = document.getElementById('themeLabel');
  document.body.classList.add('custom-bg');
  if (theme === 'dark') {
    document.body.classList.add('dark'); document.body.classList.remove('light');
    localStorage.setItem('theme', 'dark');
    if (ic) ic.textContent = '🌙';
    if (lb) lb.textContent = 'Gece Modu';
  } else {
    document.body.classList.add('light'); document.body.classList.remove('dark');
    localStorage.setItem('theme', 'light');
    if (ic) ic.textContent = '☀️';
    if (lb) lb.textContent = 'Gündüz Modu';
  }
}
function toggleTheme() { setTheme(document.body.classList.contains('dark') ? 'light' : 'dark'); }
setTheme(localStorage.getItem('theme') === 'light' ? 'light' : 'dark');

// ═══════════ AUTH ═══════════
function switchTab(tab) {
  document.getElementById('authErr').classList.remove('active');
  if (tab === 'login') {
    document.getElementById('tabLogin').classList.add('active');
    document.getElementById('tabRegister').classList.remove('active');
    document.getElementById('formLogin').classList.remove('hidden');
    document.getElementById('formRegister').classList.add('hidden');
  } else {
    document.getElementById('tabRegister').classList.add('active');
    document.getElementById('tabLogin').classList.remove('active');
    document.getElementById('formRegister').classList.remove('hidden');
    document.getElementById('formLogin').classList.add('hidden');
  }
}
function showErr(m) { const e = document.getElementById('authErr'); e.textContent = m; e.classList.add('active'); }

async function doLogin() {
  const u = document.getElementById('loginUser').value.trim();
  const p = document.getElementById('loginPass').value;
  const btn = document.getElementById('loginBtn');
  document.getElementById('authErr').classList.remove('active');
  if (!u || !p) return showErr('❌ Tüm alanları doldur');
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Giriş...';
  try {
    const fd = new FormData();
    fd.append('action','login'); fd.append('username',u); fd.append('password',p);
    const r = await fetch(AUTH, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { toast('✓ Hoş geldin, ' + d.user.username, 'success'); enterPanel(d.user); }
    else showErr('❌ ' + (d.error || 'Giriş başarısız'));
  } catch (e) { showErr('❌ Bağlantı hatası'); }
  finally { btn.disabled = false; btn.textContent = 'Giriş Yap'; }
}

async function doRegister() {
  const u = document.getElementById('regUser').value.trim();
  const e = document.getElementById('regEmail').value.trim();
  const p = document.getElementById('regPass').value;
  const btn = document.getElementById('regBtn');
  document.getElementById('authErr').classList.remove('active');
  if (!u || !p) return showErr('❌ Kullanıcı adı ve şifre gerekli');
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Kayıt...';
  try {
    const fd = new FormData();
    fd.append('action','register'); fd.append('username',u); fd.append('email',e); fd.append('password',p);
    const r = await fetch(AUTH, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { toast('✓ Kayıt başarılı!', 'success'); enterPanel(d.user); }
    else showErr('❌ ' + (d.error || 'Kayıt başarısız'));
  } catch (e) { showErr('❌ Bağlantı hatası'); }
  finally { btn.disabled = false; btn.textContent = 'Kayıt Ol'; }
}

async function doLogout() {
  await fetch(AUTH + '?action=logout').catch(() => {});
  if (CHAT_TIMER) clearInterval(CHAT_TIMER);
  if (HEARTBEAT_TIMER) clearInterval(HEARTBEAT_TIMER);
  CURRENT_USER = null;
  document.getElementById('panelView').classList.add('hidden');
  document.getElementById('authView').classList.remove('hidden');
}

async function checkSession() {
  try {
    const r = await fetch(AUTH + '?action=check');
    const d = await r.json();
    if (d.success && d.logged) enterPanel(d.user);
  } catch (e) {}
}

// ═══════════ NAV ═══════════
function navInsa() {
  const nav = document.getElementById('sbNav');
  if (!nav) return;
  const vip = isVIP(CURRENT_USER);
  const vipBadge = '<span class="vip-badge">VIP</span>';

  nav.innerHTML = `
    <div class="sb-item" id="navChat" onclick="showView('chat')"><span class="ico">💬</span> Genel Sohbet</div>
    <div class="sb-item" id="navAI" onclick="showView('ai')"><span class="ico">🤖</span> AI Asistan</div>
    <div class="sb-item" id="navUsers" onclick="showView('users')"><span class="ico">👥</span> Kullanıcılar</div>

    <div class="sb-section">Ücretsiz Sorgular</div>
    <div class="sb-item" onclick="openQuery('tc')"><span class="ico">🆔</span> TC Sorgulama</div>
    <div class="sb-item" onclick="openQuery('tcpro')"><span class="ico">🆔</span> TC Pro</div>
    <div class="sb-item" onclick="openQuery('adsoyad')"><span class="ico">👥</span> Ad Soyad</div>
    <div class="sb-item" onclick="openQuery('aile')"><span class="ico">👪</span> Aile</div>
    <div class="sb-item" onclick="openQuery('ailepro')"><span class="ico">👪</span> Aile Pro</div>
    <div class="sb-item" onclick="openQuery('tcgsm')"><span class="ico">📱</span> TC → GSM</div>
    <div class="sb-item" onclick="openQuery('gsmtc')"><span class="ico">📞</span> GSM → TC</div>
    <div class="sb-item" onclick="openQuery('eokul')"><span class="ico">🎓</span> E-Okul</div>

    <div class="sb-section" style="color:#f59e0b;">💎 VIP Sorgular</div>
    <div class="sb-item" onclick="openQuery('sulale')"><span class="ico">🌳</span> Sülale ${!vip ? vipBadge : ''}</div>
    <div class="sb-item" onclick="openQuery('adres')"><span class="ico">🏠</span> Adres ${!vip ? vipBadge : ''}</div>
    <div class="sb-item" onclick="openQuery('tapu')"><span class="ico">🏡</span> Tapu ${!vip ? vipBadge : ''}</div>
    <div class="sb-item" onclick="openQuery('adaparsel')"><span class="ico">📐</span> Ada Parsel ${!vip ? vipBadge : ''}</div>

    <div class="sb-section">🔥 Diğer</div>
    <div class="sb-item" onclick="openQuery('new_adsoyad')"><span class="ico">🔎</span> Detaylı Ad Soyad</div>
    <div class="sb-item" onclick="openQuery('new_tc')"><span class="ico">🆔</span> Detaylı TC</div>
    <div class="sb-item" onclick="openQuery('new_aile')"><span class="ico">👪</span> Detaylı Aile</div>
    <div class="sb-item" onclick="openQuery('new_hane')"><span class="ico">🏘️</span> Hane Sorgu</div>
    <div class="sb-item" onclick="openQuery('new_sulale')"><span class="ico">🌳</span> Detaylı Sülale ${!vip ? vipBadge : ''}</div>
    <div class="sb-item" onclick="openQuery('new_sokak')"><span class="ico">🛣️</span> Sokak Sorgu ${!vip ? vipBadge : ''}</div>
    <div class="sb-item" onclick="openQuery('new_adres2009')"><span class="ico">📍</span> Eski Adres ${!vip ? vipBadge : ''}</div>
  `;
}

// ═══════════ PANEL ═══════════
function enterPanel(user) {
  CURRENT_USER = user;
  document.getElementById('authView').classList.add('hidden');
  document.getElementById('panelView').classList.remove('hidden');
  document.getElementById('sideAvatar').src = avatarOf(user);
  document.getElementById('sideUsername').textContent = user.username;
  document.getElementById('sideTick').classList.toggle('hidden', !user.verified);

  const st = document.getElementById('sideStatus');
  if (user.is_vip || user.rank === 'VIP') {
    st.className = 'st vip';
    st.innerHTML = '<span class="dot"></span> 💎 VIP';
  } else {
    st.className = 'st on';
    st.innerHTML = '<span class="dot"></span> Çevrimiçi';
  }

  if (user.is_admin) {
    document.getElementById('adminLink').style.display = 'flex';
    document.getElementById('adminDivider').style.display = 'block';
  }

  navInsa();
  showView('chat');
  loadChat();
  if (CHAT_TIMER) clearInterval(CHAT_TIMER);
  CHAT_TIMER = setInterval(loadChat, 4000);
  if (HEARTBEAT_TIMER) clearInterval(HEARTBEAT_TIMER);
  HEARTBEAT_TIMER = setInterval(() => fetch(AUTH + '?action=heartbeat').catch(() => {}), 120000);
}

// ═══════════ SIDEBAR ═══════════
function toggleSidebar() {
  document.getElementById('sidebar')?.classList.toggle('open');
  document.getElementById('overlay')?.classList.toggle('active');
}
function toggleProfileMenu(e) {
  e.stopPropagation();
  const d = document.getElementById('profileDropdown');
  const b = document.getElementById('profileBtn');
  const opening = !d.classList.contains('open');
  d.classList.toggle('open', opening);
  b.classList.toggle('open', opening);
}
document.addEventListener('click', () => {
  document.getElementById('profileDropdown')?.classList.remove('open');
  document.getElementById('profileBtn')?.classList.remove('open');
});

// ═══════════ SHOW VIEW (DÜZELTİLDİ) ═══════════
function showView(view) {
  const viewMap = {
    query:    'viewQuery',
    chat:     'viewChat',
    users:    'viewUsers',
    profile:  'viewProfile',
    settings: 'viewSettings',
    ai:       'viewAI'
  };

  // Hepsini gizle
  Object.values(viewMap).forEach(id => {
    document.getElementById(id)?.classList.add('hidden');
  });

  // İstenileni göster
  const targetId = viewMap[view];
  if (targetId) document.getElementById(targetId)?.classList.remove('hidden');

  // Sidebar aktif durumu
  document.querySelectorAll('.sb-nav .sb-item').forEach(el => el.classList.remove('active'));
  const navMap = { chat:'navChat', users:'navUsers', ai:'navAI' };
  if (navMap[view]) document.getElementById(navMap[view])?.classList.add('active');

  // View'a özel yükleyiciler
  if (view === 'profile') loadProfile();
  if (view === 'users') loadUsers();
  if (view === 'settings') loadAccountInfo();
  if (view === 'chat') { loadChat(); scrollChatBottom(); }
  if (view === 'ai') { loadAIHistory(); scrollAIBottom(); }

  // Mobil menü kapat
  if (window.innerWidth < 900) {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('overlay')?.classList.remove('active');
  }
}

// ═══════════ SORGU ═══════════
function openQuery(type) {
  const q = QUERIES[type];
  if (!q) return;
  if (q.vip && !isVIP(CURRENT_USER)) { toast('💎 Bu sorgu VIP üyeler içindir', 'error'); return; }

  CURRENT_QUERY = type;
  document.getElementById('queryIcon').textContent = q.icon;
  document.getElementById('queryTitle').textContent = q.baslik;
  document.getElementById('querySubtitle').textContent = q.alt || '';
  document.getElementById('queryInfoText').textContent = INFO_TEXT[type] || '';

  const wrap = document.getElementById('queryInputs');
  wrap.innerHTML = '';
  for (const inp of q.inputs) {
    const g = document.createElement('div');
    g.className = 'q-input-group';
    if (inp.label) {
      const lb = document.createElement('label');
      lb.className = 'q-input-label';
      lb.innerHTML = inp.label + (inp.max ? ` <span class="q-input-hint">(${inp.max} hane)</span>` : '');
      g.appendChild(lb);
    }
    const i = document.createElement('input');
    i.type = 'text'; i.id = 'input_' + inp.id;
    i.className = 'q-input'; i.placeholder = inp.ph;
    if (inp.max) i.maxLength = inp.max;
    i.addEventListener('keypress', e => { if (e.key === 'Enter') runQuery(); });
    g.appendChild(i);
    wrap.appendChild(g);
  }
  document.getElementById('resultWrap').classList.remove('active');
  document.getElementById('resultTable').innerHTML = '';
  showView('query');
}

function resetQuery() {
  const q = QUERIES[CURRENT_QUERY];
  if (!q) return;
  for (const inp of q.inputs) {
    const el = document.getElementById('input_' + inp.id);
    if (el) el.value = '';
  }
  document.getElementById('resultWrap').classList.remove('active');
  document.getElementById('resultTable').innerHTML = '';
}

async function runQuery() {
  const q = QUERIES[CURRENT_QUERY];
  if (!q) return;
  if (q.vip && !isVIP(CURRENT_USER)) { toast('💎 VIP gerekli', 'error'); return; }

  const btn = document.getElementById('queryBtn');
  const wrap = document.getElementById('resultWrap');
  const table = document.getElementById('resultTable');
  const params = new URLSearchParams();
  params.set('action', 'query');
  params.set('type', q.type);
  for (const inp of q.inputs) {
    const v = document.getElementById('input_' + inp.id)?.value.trim() || '';
    if (v) params.set(inp.id, v);
  }
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Sorgulanıyor...';
  wrap.classList.add('active');
  table.innerHTML = '<div class="q-empty"><span class="spinner"></span> Sorgulanıyor...</div>';
  document.getElementById('resultCount').textContent = '';
  try {
    const r = await fetch(API + '?' + params.toString());
    const d = await r.json();
    if (r.status === 401) { doLogout(); return; }
    renderResult(d);
  } catch (e) {
    table.innerHTML = `<div class="q-error">✗ Bağlantı hatası</div>`;
  } finally {
    btn.disabled = false;
    btn.innerHTML = '🔎 Sorgula';
  }
}

function renderResult(data) {
  const table = document.getElementById('resultTable');
  const cnt = document.getElementById('resultCount');
  if (data.error || data.hata) { table.innerHTML = `<div class="q-error">✗ ${esc(data.error || data.hata)}</div>`; cnt.textContent = ''; return; }
  if (data.success === false) { table.innerHTML = `<div class="q-error">✗ ${esc(data.error || 'Kayıt yok')}</div>`; cnt.textContent = ''; return; }

  if (data.data && typeof data.data === 'object' && !Array.isArray(data.data) && data.data.kisi) {
    const d = data.data;
    const kategoriler = { kisi:'👤 Kişi', baba:'👨 Baba', anne:'👩 Anne', buyukbaba:'👴 Büyükbaba', buyukanne:'👵 Büyükanne', kardesler:'👫 Kardeşler', cocuklar:'👶 Çocuklar', torunlar:'👦 Torunlar', ailesirano_uyeleri:'📋 Aile Üyeleri' };
    let html = '', toplam = 0;
    if (d.ozet) {
      let s = [];
      for (const [k, v] of Object.entries(d.ozet)) if (v) s.push(`${k}: ${v}`);
      if (s.length) html += `<div class="result-ozet">📊 ${s.join(' · ')}</div>`;
    }
    for (const [key, list] of Object.entries(d)) {
      if (Array.isArray(list) && list.length > 0) {
        toplam += list.length;
        const b = kategoriler[key] || key;
        html += `<div class="result-category"><div class="result-category-title">${b} <span class="cat-count">${list.length} kişi</span></div><table class="q-table"><thead><tr>`;
        const keys = [], seen = new Set();
        for (const it of list) for (const k of Object.keys(it)) if (!seen.has(k)) { seen.add(k); keys.push(k); }
        for (const k of keys) html += `<th>${esc(k)}</th>`;
        html += '</tr></thead><tbody>';
        for (const it of list) {
          html += '<tr>';
          for (const k of keys) {
            let v = it[k];
            if (v === null || v === undefined) v = '-';
            else if (typeof v === 'object') v = JSON.stringify(v);
            html += `<td>${esc(v)}</td>`;
          }
          html += '</tr>';
        }
        html += '</tbody></table></div>';
      }
    }
    if (toplam === 0) { table.innerHTML = '<div class="q-empty">✗ Kayıt yok</div>'; return; }
    table.innerHTML = html;
    cnt.textContent = toplam + ' kayıt';
    return;
  }

  let items = data.rows || data.data?.results || data.data?.rows || data.data || data.results || data;
  if (!Array.isArray(items)) items = [items];
  items = items.filter(x => x && Object.keys(x).length > 0);
  if (items.length === 0) { table.innerHTML = '<div class="q-empty">✗ Kayıt yok</div>'; return; }

  const keys = [], seen = new Set();
  for (const it of items) for (const k of Object.keys(it)) if (!seen.has(k)) { seen.add(k); keys.push(k); }

  let html = '<table class="q-table"><thead><tr>';
  for (const k of keys) html += `<th>${esc(k)}</th>`;
  html += '</tr></thead><tbody>';
  for (const it of items) {
    html += '<tr>';
    for (const k of keys) {
      let v = it[k];
      if (v === null || v === undefined) v = '-';
      else if (typeof v === 'object') v = JSON.stringify(v);
      html += `<td>${esc(v)}</td>`;
    }
    html += '</tr>';
  }
  html += '</tbody></table>';
  table.innerHTML = html;
  cnt.textContent = items.length + ' kayıt';
}

// ═══════════ CHAT ═══════════
async function loadChat() {
  try {
    const r = await fetch(CHAT + '?action=messages');
    const d = await r.json();
    if (!d.success) return;
    const box = document.getElementById('chatMessages');
    if (!box) return;
    const atBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 80;
    let html = '';
    for (const m of d.messages) {
      const me = CURRENT_USER && m.user_id === CURRENT_USER.id;
      html += `
        <div class="msg ${me ? 'me' : ''}">
          <img class="av" src="${esc(m.avatar)}" alt="">
          <div><div class="msg-bubble">
            <div class="msg-name">${esc(m.username)} ${m.verified ? '✅' : ''}<span class="msg-rank">${esc(m.rank)}</span></div>
            <div class="msg-text">${esc(m.text)}</div>
            <div class="msg-time">${esc(m.time)}</div>
          </div></div>
        </div>`;
    }
    box.innerHTML = html || '<div class="q-empty">Henüz mesaj yok.</div>';
    if (atBottom) box.scrollTop = box.scrollHeight;
    try {
      const ur = await fetch(AUTH + '?action=users');
      const ud = await ur.json();
      if (ud.success) document.getElementById('chatOnlineCount').textContent = ud.users.filter(u => u.online).length;
    } catch (e) {}
  } catch (e) {}
}
function scrollChatBottom() { const b = document.getElementById('chatMessages'); if (b) b.scrollTop = b.scrollHeight; }
async function sendMessage() {
  const inp = document.getElementById('chatInput');
  const t = inp.value.trim();
  if (!t) return;
  const btn = document.getElementById('chatSendBtn');
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('action','send'); fd.append('text',t);
    const r = await fetch(CHAT, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { inp.value=''; await loadChat(); scrollChatBottom(); }
    else toast('❌ ' + (d.error || 'Hata'), 'error');
  } catch (e) { toast('❌ Bağlantı hatası', 'error'); }
  finally { btn.disabled = false; }
}

// ═══════════ AI ═══════════
async function loadAIHistory() {
  try {
    const r = await fetch(AIAPI + '?action=history');
    const d = await r.json();
    if (!d.success) return;
    const box = document.getElementById('aiMessages');
    if (!box) return;
    let html = '';
    for (const m of d.messages) {
      html += `
        <div class="msg me"><div><div class="msg-bubble"><div class="msg-text">${esc(m.soru)}</div></div></div></div>
        <div class="msg"><img class="av" src="${DEFAULT_AVATAR}" alt=""><div><div class="msg-bubble"><div class="msg-text">${esc(m.cevap)}</div>${m.komut ? '<div class="msg-time" style="color:#f59e0b;">⚡ Komut</div>' : ''}</div></div></div>`;
    }
    box.innerHTML = html || '<div class="q-empty">AI ile sohbete başla! 👋</div>';
  } catch (e) {}
}
function scrollAIBottom() { const b = document.getElementById('aiMessages'); if (b) b.scrollTop = b.scrollHeight; }
async function sendAI() {
  const inp = document.getElementById('aiInput');
  const q = inp.value.trim();
  if (!q) return;
  const box = document.getElementById('aiMessages');
  box.innerHTML += `<div class="msg me"><div><div class="msg-bubble"><div class="msg-text">${esc(q)}</div></div></div></div>`;
  inp.value = ''; scrollAIBottom();

  const btn = document.getElementById('aiSendBtn');
  btn.disabled = true;
  const waitId = 'wait_' + Date.now();
  box.innerHTML += `<div class="msg" id="${waitId}"><img class="av" src="${DEFAULT_AVATAR}" alt=""><div><div class="msg-bubble"><div class="msg-text"><span class="spinner"></span> düşünüyor...</div></div></div></div>`;
  scrollAIBottom();

  try {
    const fd = new FormData();
    fd.append('action','chat'); fd.append('q', q);
    const r = await fetch(AIAPI, { method:'POST', body: fd });
    const d = await r.json();
    document.getElementById(waitId)?.remove();
    if (d.success) {
      box.innerHTML += `<div class="msg"><img class="av" src="${DEFAULT_AVATAR}" alt=""><div><div class="msg-bubble"><div class="msg-text">${esc(d.response)}</div>${d.command ? '<div class="msg-time" style="color:#f59e0b;">⚡ Komut Uygulandı</div>' : ''}</div></div></div>`;
    } else {
      box.innerHTML += `<div class="msg"><div><div class="msg-bubble"><div class="msg-text" style="color:#f85149">❌ ${esc(d.error || 'Hata')}</div></div></div></div>`;
    }
    scrollAIBottom();
  } catch (e) {
    document.getElementById(waitId)?.remove();
    box.innerHTML += `<div class="msg"><div><div class="msg-bubble"><div class="msg-text" style="color:#f85149">❌ Bağlantı hatası</div></div></div></div>`;
  } finally { btn.disabled = false; btn.textContent = 'Gönder'; }
}
async function clearAI() {
  if (!confirm('AI geçmişini temizle?')) return;
  await fetch(AIAPI + '?action=clear');
  document.getElementById('aiMessages').innerHTML = '<div class="q-empty">Geçmiş temizlendi!</div>';
}

// ═══════════ USERS ═══════════
async function loadUsers() {
  try {
    const r = await fetch(AUTH + '?action=users');
    const d = await r.json();
    if (!d.success) return;
    const users = d.users.sort((a,b) => (b.online - a.online) || a.username.localeCompare(b.username));
    document.getElementById('usersCount').textContent = users.length + ' kullanıcı';
    let html = '';
    for (const u of users) {
      const cls = u.banned ? 'banned' : (u.online ? 'on' : 'off');
      const txt = u.banned ? 'BANLI' : (u.online ? 'Çevrimiçi' : 'Çevrimdışı');
      let rankCls = '';
      if (u.is_admin) rankCls = 'admin';
      else if (u.is_vip || u.rank === 'VIP') rankCls = 'vip';
      html += `
        <div class="user-card">
          <img class="av" src="${esc(avatarOf(u))}" alt="">
          <div class="info">
            <div class="nm">
              ${esc(u.username)} ${u.verified ? '<span class="tk">✅</span>' : ''}
              <span class="rk ${rankCls}">${esc(u.rank)}</span>
            </div>
            <div class="bio">${esc(u.bio || '')} ${u.horoscope ? '· ' + esc(u.horoscope) : ''}</div>
            <div class="st ${cls}"><span class="dot"></span> ${txt}</div>
          </div>
        </div>`;
    }
    document.getElementById('usersList').innerHTML = html || '<div class="q-empty">Kayıt yok</div>';
  } catch (e) {}
}

// ═══════════ PROFILE ═══════════
async function loadProfile() {
  try {
    const r = await fetch(AUTH + '?action=profile&user=' + encodeURIComponent(CURRENT_USER.username));
    const d = await r.json();
    if (!d.success) return;
    const u = d.user;
    document.getElementById('pAvatar').src = avatarOf(u);
    document.getElementById('pUsername').textContent = u.username;
    document.getElementById('pTick').classList.toggle('hidden', !u.verified);
    const rk = document.getElementById('pRank');
    rk.textContent = u.rank;
    rk.className = 'profile-rank-badge' + ((u.is_vip || u.rank === 'VIP') ? ' vip' : '');
    document.getElementById('pBio').textContent = u.bio || '';
    document.getElementById('pMeta').textContent = `${u.email || '-'} · ${u.horoscope || '-'} · ${u.city || '-'} · ${u.job || '-'}`;
    document.getElementById('editEmail').value = u.email || '';
    document.getElementById('editBio').value = u.bio || '';
    document.getElementById('editBirthday').value = u.birthday || '';
    document.getElementById('editCity').value = u.city || '';
    document.getElementById('editJob').value = u.job || '';
    document.getElementById('editInstagram').value = u.instagram || '';
    document.getElementById('editTelegram').value = u.telegram || '';
    document.getElementById('editAvatar').value = u.avatar || '';
  } catch (e) {}
}
async function saveProfile() {
  const fd = new FormData();
  fd.append('action','update_profile');
  fd.append('email', document.getElementById('editEmail').value.trim());
  fd.append('bio', document.getElementById('editBio').value.trim());
  fd.append('birthday', document.getElementById('editBirthday').value.trim());
  fd.append('city', document.getElementById('editCity').value.trim());
  fd.append('job', document.getElementById('editJob').value.trim());
  fd.append('instagram', document.getElementById('editInstagram').value.trim());
  fd.append('telegram', document.getElementById('editTelegram').value.trim());
  fd.append('avatar', document.getElementById('editAvatar').value.trim());
  try {
    const r = await fetch(AUTH, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { toast('✓ Profil güncellendi', 'success'); loadProfile(); }
    else toast('❌ ' + d.error, 'error');
  } catch (e) { toast('❌ Bağlantı hatası', 'error'); }
}

// ═══════════ SETTINGS ═══════════
async function changePassword() {
  const o = document.getElementById('oldPass').value;
  const n = document.getElementById('newPass').value;
  if (!o || !n) return toast('Şifre alanlarını doldur', 'error');
  const fd = new FormData();
  fd.append('action','change_password');
  fd.append('old_password',o); fd.append('new_password',n);
  try {
    const r = await fetch(AUTH, { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { toast('✓ Şifre değiştirildi', 'success'); document.getElementById('oldPass').value=''; document.getElementById('newPass').value=''; }
    else toast('❌ ' + d.error, 'error');
  } catch (e) { toast('❌ Bağlantı hatası', 'error'); }
}
async function loadAccountInfo() {
  try {
    const r = await fetch(AUTH + '?action=check');
    const d = await r.json();
    if (!d.success || !d.logged) return;
    const u = d.user;
    document.getElementById('settingsAccountInfo').innerHTML = `
      <div>👤 <b>Kullanıcı:</b> ${esc(u.username)}</div>
      <div>📧 <b>Email:</b> ${esc(u.email || '-')}</div>
      <div>🎂 <b>Doğum:</b> ${esc(u.birthday || '-')}</div>
      <div>♈ <b>Burç:</b> ${esc(u.horoscope || '-')}</div>
      <div>🏙️ <b>Şehir:</b> ${esc(u.city || '-')}</div>
      <div>💼 <b>Meslek:</b> ${esc(u.job || '-')}</div>
      <div>📷 <b>Instagram:</b> ${esc(u.instagram || '-')}</div>
      <div>✈️ <b>Telegram:</b> ${esc(u.telegram || '-')}</div>
      <div>🏅 <b>Rütbe:</b> ${esc(u.rank)} ${u.is_vip ? '💎' : ''}</div>
      <div>✅ <b>Doğrulanmış:</b> ${u.verified ? 'Evet' : 'Hayır'}</div>
      <div>📅 <b>Kayıt:</b> ${u.created_at ? new Date(u.created_at).toLocaleString('tr-TR') : '-'}</div>
    `;
  } catch (e) {}
}

// ═══════════ OLAYLAR ═══════════
document.getElementById('loginPass')?.addEventListener('keypress', e => { if (e.key === 'Enter') doLogin(); });
document.getElementById('regPass')?.addEventListener('keypress', e => { if (e.key === 'Enter') doRegister(); });
document.getElementById('chatInput')?.addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });
document.getElementById('aiInput')?.addEventListener('keypress', e => { if (e.key === 'Enter') sendAI(); });

checkSession();