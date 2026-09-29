<?php
/**
 * havadurumu.php — İl ile hava durumu
 * Kullanıcı: ?il=Ankara
 * Dönen: Türkçe alan adlarıyla JSON
 * İletişim: Telegram @cmrbaskani
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ═══════════ GİRDİ ═══════════
$il = trim($_REQUEST['il'] ?? $_REQUEST['sehir'] ?? '');
if ($il === '') {
    echo json_encode(["hata" => "il parametresi gerekli"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════ HTTP YARDIMCI ═══════════
function http_get($url, $params = [], $timeout = 15) {
    if ($params) {
        $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
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

// ═══════════ DURUM KODU ÇEVİRİ ═══════════
function durum_adi($kod) {
    $harita = [
        0  => "Açık",
        1  => "Az bulutlu",
        2  => "Parçalı bulutlu",
        3  => "Kapalı",
        45 => "Sisli",
        48 => "Kırağılı sis",
        51 => "Hafif çisenti",
        53 => "Çisenti",
        55 => "Yoğun çisenti",
        61 => "Hafif yağmur",
        63 => "Yağmur",
        65 => "Şiddetli yağmur",
        66 => "Dondurucu yağmur",
        67 => "Şiddetli dondurucu yağmur",
        71 => "Hafif kar",
        73 => "Kar",
        75 => "Yoğun kar",
        77 => "Kar taneleri",
        80 => "Hafif sağanak",
        81 => "Sağanak",
        82 => "Şiddetli sağanak",
        85 => "Hafif kar sağanağı",
        86 => "Yoğun kar sağanağı",
        95 => "Gök gürültülü fırtına",
        96 => "Dolulu fırtına",
        99 => "Şiddetli dolulu fırtına",
    ];
    return $harita[$kod] ?? "Bilinmiyor";
}

function gun_adi($tarih) {
    $gunler = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
    return $gunler[(int)date('w', strtotime($tarih))];
}

// ═══════════ 1) GEOCODING ═══════════
$geo = http_get('https://geocoding-api.open-meteo.com/v1/search', [
    'name'     => $il,
    'count'    => 1,
    'language' => 'tr',
    'format'   => 'json'
]);

if (!$geo || empty($geo['results'])) {
    echo json_encode(["hata" => "'$il' bulunamadı"], JSON_UNESCAPED_UNICODE);
    exit;
}

$g      = $geo['results'][0];
$enlem  = $g['latitude'];
$boylam = $g['longitude'];
$tz     = $g['timezone'] ?? 'Europe/Istanbul';

// ═══════════ 2) HAVA VERİSİ ═══════════
$hava = http_get('https://api.open-meteo.com/v1/forecast', [
    'latitude'  => $enlem,
    'longitude' => $boylam,
    'current'   => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code,apparent_temperature,pressure_msl',
    'daily'     => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max,sunrise,sunset,uv_index_max',
    'hourly'    => 'temperature_2m,precipitation_probability,weather_code,wind_speed_10m',
    'timezone'  => $tz
]);

if (!$hava) {
    echo json_encode(["hata" => "Hava verisi alınamadı"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════ 3) TÜRKÇE ALAN ADLARINA ÇEVİR ═══════════
$cur = $hava['current'] ?? [];
$d   = $hava['daily']   ?? [];
$h   = $hava['hourly']  ?? [];

$kod = $cur['weather_code'] ?? -1;

// ANLIK
$anlik = [
    'saat'          => $cur['time'] ?? null,
    'sicaklik'      => $cur['temperature_2m'] ?? null,
    'hissedilen'    => $cur['apparent_temperature'] ?? null,
    'nem'           => $cur['relative_humidity_2m'] ?? null,
    'ruzgar'        => $cur['wind_speed_10m'] ?? null,
    'basinc'        => $cur['pressure_msl'] ?? null,
    'durum'         => durum_adi($kod),
    'durum_kodu'    => $kod,
];

// GÜNLÜK (7 gün)
$gunluk = [];
foreach (($d['time'] ?? []) as $i => $t) {
    $k = $d['weather_code'][$i] ?? -1;
    $gunluk[] = [
        'tarih'       => $t,
        'gun'         => gun_adi($t),
        'durum'       => durum_adi($k),
        'durum_kodu'  => $k,
        'en_dusuk'    => $d['temperature_2m_min'][$i] ?? null,
        'en_yuksek'   => $d['temperature_2m_max'][$i] ?? null,
        'yagis_mm'    => $d['precipitation_sum'][$i] ?? null,
        'ruzgar_max'  => $d['wind_speed_10m_max'][$i] ?? null,
        'gunes_dogus' => $d['sunrise'][$i] ?? null,
        'gunes_batis' => $d['sunset'][$i] ?? null,
        'uv'          => $d['uv_index_max'][$i] ?? null,
    ];
}

// SAATLİK (48 saat)
$saatlik = [];
$limit = min(48, count($h['time'] ?? []));
for ($i = 0; $i < $limit; $i++) {
    $k = $h['weather_code'][$i] ?? -1;
    $saatlik[] = [
        'saat'      => $h['time'][$i],
        'sicaklik'  => $h['temperature_2m'][$i] ?? null,
        'durum'     => durum_adi($k),
        'yagis_yuz' => $h['precipitation_probability'][$i] ?? null,
        'ruzgar'    => $h['wind_speed_10m'][$i] ?? null,
    ];
}

// ÖZET
$mins  = array_filter(array_column($gunluk, 'en_dusuk'),  fn($v) => $v !== null);
$maxs  = array_filter(array_column($gunluk, 'en_yuksek'), fn($v) => $v !== null);
$yagis = array_filter(array_column($gunluk, 'yagis_mm'),  fn($v) => $v !== null);

$ozet = [
    'gun_sayisi'    => count($gunluk),
    'en_dusuk'      => $mins ? min($mins) : null,
    'en_yuksek'     => $maxs ? max($maxs) : null,
    'ortalama'      => ($mins && $maxs)
        ? round((array_sum($mins) + array_sum($maxs)) / (count($mins) + count($maxs)), 1)
        : null,
    'toplam_yagis'  => $yagis ? round(array_sum($yagis), 1) : 0,
];

// ═══════════ 4) TÜRKÇE JSON DÖN ═══════════
$sonuc = [
    'basarili' => true,
    'sorgu_il' => $il,
    'konum'    => [
        'il'      => $g['name']    ?? $il,
        'ulke'    => $g['country'] ?? '',
        'enlem'   => $enlem,
        'boylam'  => $boylam,
        'saat_dilimi' => $tz,
    ],
    'anlik'    => $anlik,
    'gunluk'   => $gunluk,
    'saatlik'  => $saatlik,
    'ozet'     => $ozet,
    'kaynak'   => 'none',
    'telegram' => '@cmrbaskani',
    'tarih'    => date('c'),
];

echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);