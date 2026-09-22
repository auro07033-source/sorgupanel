<?php
/**
 * forexsystem.php — Forex Sorgulama Hizmeti
 * Login + API proxy
 * Telegram: @cmrbaskani
 */

session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ═══════════════════════════════════════════════════════
// AYARLAR
// ═══════════════════════════════════════════════════════

// Panel giriş şifresi (değiştir!)
define('PANEL_USER', 'cmrbaskani');
define('PANEL_PASS', 'forex2026');

// Ajax API base
define('AJAX_BASE', 'https://apiv2.ajaxsystems.fun');

// Session süresi (saniye) — 24 saat
define('SESSION_TTL', 86400);

// ═══════════════════════════════════════════════════════
// YARDIMCI
// ═══════════════════════════════════════════════════════

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function is_logged_in() {
    return isset($_SESSION['logged_in']) 
        && $_SESSION['logged_in'] === true
        && isset($_SESSION['login_time'])
        && (time() - $_SESSION['login_time']) < SESSION_TTL;
}

function require_login() {
    if (!is_logged_in()) {
        json_out([
            "success" => false,
            "error"   => "Oturum gerekli. Lutfen giris yapin.",
            "login"   => "index.html"
        ], 401);
    }
}

// Ajax API'ye istek at
function ajax_get($endpoint, $params = []) {
    $url = AJAX_BASE . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (ForexSorgu/1.0)',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json, */*',
        ],
    ]);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ["success" => false, "error" => "cURL: $err"];
    }

    if ($code !== 200) {
        return ["success" => false, "error" => "HTTP $code"];
    }

    $json = json_decode($body, true);
    if ($json === null) {
        return ["success" => false, "error" => "Gecersiz JSON yanit", "raw" => substr($body, 0, 300)];
    }

    return $json;
}

// ═══════════════════════════════════════════════════════
// ROUTE
// ═══════════════════════════════════════════════════════
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ─── LOGIN ───
    case 'login':
        $user = trim($_POST['user'] ?? $_GET['user'] ?? '');
        $pass = trim($_POST['pass'] ?? $_GET['pass'] ?? '');

        if ($user === '' || $pass === '') {
            json_out(["success" => false, "error" => "Kullanici adi ve sifre gerekli"], 400);
        }

        if ($user === PANEL_USER && $pass === PANEL_PASS) {
            $_SESSION['logged_in']  = true;
            $_SESSION['login_time'] = time();
            $_SESSION['user']       = $user;

            json_out([
                "success"  => true,
                "message"  => "Giris basarili",
                "user"     => $user,
                "telegram" => "@cmrbaskani"
            ]);
        } else {
            json_out(["success" => false, "error" => "Kullanici adi veya sifre yanlis"], 401);
        }
        break;

    // ─── LOGOUT ───
    case 'logout':
        session_destroy();
        json_out(["success" => true, "message" => "Cikis yapildi"]);
        break;

    // ─── OTURUM KONTROL ───
    case 'check':
        json_out([
            "success" => true,
            "logged"  => is_logged_in(),
            "user"    => $_SESSION['user'] ?? null
        ]);
        break;

    // ─── API PROXY (tüm sorgular) ───
    case 'query':
        require_login();

        $type = $_GET['type'] ?? $_POST['type'] ?? '';
        if ($type === '') {
            json_out(["success" => false, "error" => "type parametresi gerekli"], 400);
        }

        // Endpoint haritası
        $endpoints = [
            'tc'        => ['path' => '/tc.php',       'params' => ['tc']],
            'tcpro'     => ['path' => '/tcpro.php',    'params' => ['tc']],
            'adsoyad'   => ['path' => '/adsoyad.php',  'params' => ['ad', 'soyad']],
            'aile'      => ['path' => '/aile.php',     'params' => ['tc']],
            'ailepro'   => ['path' => '/ailepro.php',  'params' => ['tc']],
            'sulale'    => ['path' => '/sulale.php',   'params' => ['tc']],
            'tcgsm'     => ['path' => '/tcgsm.php',    'params' => ['tc']],
            'gsmtc'     => ['path' => '/gsmtc.php',    'params' => ['gsm', 'auth']],
            'eokul'     => ['path' => '/eokul.php',    'params' => ['tc']],
            'adres'     => ['path' => '/adres.php',    'params' => ['tc']],
            'tapu'      => ['path' => '/tapu.php',     'params' => ['tc']],
            'adaparsel' => ['path' => '/adaparsel.php', 'params' => ['il', 'ilce', 'mahalle', 'ada', 'parsel']],
        ];

        if (!isset($endpoints[$type])) {
            json_out(["success" => false, "error" => "Gecersiz type: $type"], 400);
        }

        $cfg = $endpoints[$type];
        $params = [];
        foreach ($cfg['params'] as $p) {
            if (isset($_GET[$p]) && $_GET[$p] !== '') {
                $params[$p] = $_GET[$p];
            } elseif (isset($_POST[$p]) && $_POST[$p] !== '') {
                $params[$p] = $_POST[$p];
            }
        }

        if (empty($params)) {
            json_out([
                "success" => false,
                "error"   => "En az bir parametre gerekli",
                "params"  => $cfg['params']
            ], 400);
        }

        // gsmtc için auth=fire zorunlu
        if ($type === 'gsmtc' && !isset($params['auth'])) {
            $params['auth'] = 'fire';
        }

        $result = ajax_get($cfg['path'], $params);

        // AJAX API bazen "data" bazen direkt array döner, normalize et
        if (!isset($result['success'])) {
            // Eğer cevap bir array veya data içeriyorsa sar
            if (isset($result['data']) || isset($result['status']) || isset($result['hata'])) {
                // olduğu gibi bırak
            } else {
                $result = ["success" => true, "data" => $result];
            }
        }

        $result['telegram'] = '@cmrbaskani';
        $result['type'] = $type;
        json_out($result);
        break;

    // ─── ENDPOINT LİSTESİ ───
    case 'list':
        require_login();
        json_out([
            "success" => true,
            "endpoints" => [
                ["id" => "tc",         "isim" => "TC Sorgulama",       "params" => ["tc"]],
                ["id" => "tcpro",      "isim" => "TC Pro Sorgulama",   "params" => ["tc"]],
                ["id" => "adsoyad",    "isim" => "Ad Soyad Sorgulama", "params" => ["ad", "soyad"]],
                ["id" => "aile",       "isim" => "Aile Sorgulama",     "params" => ["tc"]],
                ["id" => "ailepro",    "isim" => "Aile Pro Sorgulama", "params" => ["tc"]],
                ["id" => "sulale",     "isim" => "Sülale Sorgulama",   "params" => ["tc"]],
                ["id" => "tcgsm",      "isim" => "TC → GSM",           "params" => ["tc"]],
                ["id" => "gsmtc",      "isim" => "GSM → TC",           "params" => ["gsm", "auth"]],
                ["id" => "eokul",      "isim" => "E-Okul Sorgulama",   "params" => ["tc"]],
                ["id" => "adres",      "isim" => "Adres Sorgulama",    "params" => ["tc"]],
                ["id" => "tapu",       "isim" => "Tapu Sorgulama",     "params" => ["tc"]],
                ["id" => "adaparsel",  "isim" => "Ada Parsel Sorgulama","params" => ["il","ilce","mahalle","ada","parsel"]]
            ],
            "telegram" => "@cmrbaskani"
        ]);
        break;

    // ─── HEALTH ───
    case 'health':
        json_out([
            "success"  => true,
            "status"   => "ok",
            "time"     => date('c'),
            "login"    => is_logged_in(),
            "telegram" => "@cmrbaskani"
        ]);
        break;

    default:
        json_out([
            "success" => false,
            "error"   => "Bilinmeyen action",
            "ornekler" => [
                "login"  => "POST action=login&user=...&pass=...",
                "query"  => "GET action=query&type=tc&tc=17420629810",
                "list"   => "GET action=list",
                "check"  => "GET action=check",
                "logout" => "GET action=logout"
            ],
            "telegram" => "@cmrbaskani"
        ], 404);
}