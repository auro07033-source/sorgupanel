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

// ═══════════ HTTP YARDIMCI (debug gömülü) ═══════════
function http_get($url, $params = [], $timeout = 15) {
    if ($params) {
        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = $k . '=' . str_replace('%2C', ',', urlencode($v));
        }
        $url .= '?' . implode('&', $parts);
    }

    $GLOBALS['HTTP_LAST'] = [
        'url'       => $url,
        'code'      => null,
        'err'       => null,
        'body_len'  => 0,
        'body_head' => '',
        'json_err'  => null,
        'method'    => null
    ];

    // ─── 1) cURL dene ───
    if (function_exists('curl_init')) {
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
            // CURLOPT_ENCODING YOK — gzip istemiyoruz
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $GLOBALS['HTTP_LAST']['method']    = 'curl';
        $GLOBALS['HTTP_LAST']['code']      = $code;
        $GLOBALS['HTTP_LAST']['err']       = $err;
        $GLOBALS['HTTP_LAST']['body_len']  = strlen((string)$body);
        $GLOBALS['HTTP_LAST']['body_head'] = substr((string)$body, 0, 400);

        if (!$err && $code === 200 && $body) {
            $j = json_decode($body, true);
            if (is_array($j)) return $j;
            $GLOBALS['HTTP_LAST']['json_err'] = json_last_error_msg();
        }
    }

    // ─── 2) file_get_contents fallback ───
    $GLOBALS['HTTP_LAST']['method'] = 'file_get_contents';
    $ctx = stream_context_create([
        'http' => [
            'timeout'    => $timeout,
            'user_agent' => 'HavaBot/1.0',
            'header'     => "Accept: application/json\r\nAccept-Encoding: identity\r\n"
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $body2 = @file_get_contents($url, false, $ctx);

    $GLOBALS['HTTP_LAST']['body_len']  = strlen((string)$body2);
    $GLOBALS['HTTP_LAST']['body_head'] = substr((string)$body2, 0, 400);

    if ($body2 === false) {
        $GLOBALS['HTTP_LAST']['err'] = 'file_get_contents failed';
        return null;
    }
    $j2 = json_decode($body2, true);
    if (!is_array($j2)) {
        $GLOBALS['HTTP_LAST']['json_err'] = json_last_error_msg();
        return null;
    }
    return $j2;
}

// ═══════════ 1) GEOCODING (il -> koordinat) ═══════════
$geo = http_get('https://geocoding-api.open-meteo.com/v1/search', [
    'name'     => $il,
    'count'    => 1,
    'language' => 'tr',
    'format'   => 'json'
]);
$geo_debug = $GLOBALS['HTTP_LAST'];

if (!$geo || empty($geo['results'])) {
    echo json_encode([
        "success"    => false,
        "error"      => "'$il' bulunamadı",
        "adim"       => "geocoding",
        "geo_debug"  => $geo_debug
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
$hava_debug = $GLOBALS['HTTP_LAST'];

if (!$hava) {
    echo json_encode([
        "success"    => false,
        "error"      => "Hava verisi alınamadı",
        "konum"      => [
            "il"     => $ad,
            "ulke"   => $ulke,
            "enlem"  => $enlem,
            "boylam" => $boylam
        ],
        "adim"       => "hava_api",
        "geo_debug"  => $geo_debug,
        "hava_debug" => $hava_debug
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