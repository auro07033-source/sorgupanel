<?php
/**
 * havadurumu.php — İl ile hava durumu (tool ile aynı akış)
 * Akış: il -> geocoding -> koordinat -> hava API -> JSON döndür
 * İletişim: Telegram @cmrbaskani
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ═══════════ GİRDİ ═══════════
$il  = trim($_REQUEST['il'] ?? $_REQUEST['sehir'] ?? '');
$gun = isset($_REQUEST['gun']) ? max(1, min(16, (int)$_REQUEST['gun'])) : 7;

if ($il === '') {
    echo json_encode([
        "success" => false,
        "error"   => "il parametresi gerekli"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ═══════════ HTTP YARDIMCI ═══════════
function http_get($url, $params = [], $timeout = 15) {
    if ($params) {
        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = $k . '=' . str_replace('%2C', ',', urlencode($v));
        }
        $url .= '?' . implode('&', $parts);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'HavaBot/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || $code !== 200 || !$body) return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

// ═══════════ 1) GEOCODING (il -> koordinat) ═══════════
$geo = http_get('https://geocoding-api.open-meteo.com/v1/search', [
    'name'     => $il,
    'count'    => 1,
    'language' => 'tr',
    'format'   => 'json'
]);

if (!$geo || empty($geo['results'])) {
    echo json_encode([
        "success" => false,
        "error"   => "'$il' bulunamadı"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$g      = $geo['results'][0];
$enlem  = $g['latitude'];
$boylam = $g['longitude'];
$tz     = $g['timezone'] ?? 'Europe/Istanbul';
$ad     = $g['name'] ?? $il;
$ulke   = $g['country'] ?? '';

// ═══════════ 2) HAVA API (koordinat -> JSON) ═══════════
$hava = http_get('https://api.open-meteo.com/v1/forecast', [
    'latitude'      => $enlem,
    'longitude'     => $boylam,
    'current'       => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code,apparent_temperature,pressure_msl',
    'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max,sunrise,sunset,uv_index_max',
    'hourly'        => 'temperature_2m,precipitation_probability,weather_code,wind_speed_10m',
    'timezone'      => $tz,
    'forecast_days' => $gun
]);

if (!$hava) {
    echo json_encode([
        "success" => false,
        "error"   => "Hava verisi alınamadı",
        "konum"   => [
            "il"     => $ad,
            "ulke"   => $ulke,
            "enlem"  => $enlem,
            "boylam" => $boylam
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ═══════════ 3) DÖNEN JSON'U OLDUĞU GİBİ DÖNDÜR + KONUM EKLE ═══════════
$hava['success']  = true;
$hava['sorgu_il'] = $il;
$hava['konum']    = [
    'ad'     => $ad,
    'ulke'   => $ulke,
    'enlem'  => $enlem,
    'boylam' => $boylam,
    'tz'     => $tz
];
$hava['kaynak']   = 'none';
$hava['telegram'] = '@cmrbaskani';

echo json_encode($hava, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);