<?php
/**
 * havadurumu.php — İl -> koordinat -> hava API
 * Kullanıcı: ?il=Ankara
 * Dönen: Open-Meteo ham JSON (default 7 gün)
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ═══════════ GİRDİ ═══════════
$il = trim($_REQUEST['il'] ?? $_REQUEST['sehir'] ?? '');
if ($il === '') {
    echo json_encode(["error" => "il parametresi gerekli"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════ HTTP ═══════════
function http_get($url, $params = [], $timeout = 15) {
    if ($params) {
        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = $k . '=' . str_replace('%2C', ',', rawurlencode($v));
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
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Accept-Encoding: identity'
        ],
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
    echo json_encode(["error" => "'$il' bulunamadı"], JSON_UNESCAPED_UNICODE);
    exit;
}

$g      = $geo['results'][0];
$enlem  = $g['latitude'];
$boylam = $g['longitude'];
$tz     = $g['timezone'] ?? 'Europe/Istanbul';

// ═══════════ 2) HAVA API (ham JSON, default 7 gün) ═══════════
$hava = http_get('https://api.open-meteo.com/v1/forecast', [
    'latitude'  => $enlem,
    'longitude' => $boylam,
    'current'   => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code,apparent_temperature,pressure_msl',
    'daily'     => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max,sunrise,sunset,uv_index_max',
    'hourly'    => 'temperature_2m,precipitation_probability,weather_code,wind_speed_10m',
    'timezone'  => $tz
    // forecast_days YOK -> Open-Meteo default 7 gün döner
]);

if (!$hava) {
    echo json_encode(["error" => "Hava verisi alınamadı"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════ 3) HAM JSON DÖN ═══════════
echo json_encode($hava, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);