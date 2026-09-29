<?php
/**
 * havadurumu.php — İl ile hava durumu API proxy
 * Kaynak: Open-Meteo (ücretsiz, anahtar gerekmez)
 * Cache: data/hava_cache.json (30 dk TTL)
 */
require_once __DIR__ . '/config.php';

$WMO = [
    0=>"Açık",1=>"Az bulutlu",2=>"Parçalı bulutlu",3=>"Kapalı",
    45=>"Sisli",48=>"Kırağılı sis",
    51=>"Hafif çisenti",53=>"Çisenti",55=>"Yoğun çisenti",
    61=>"Hafif yağmur",63=>"Yağmur",65=>"Şiddetli yağmur",
    66=>"Dondurucu yağmur",67=>"Şiddetli dondurucu yağmur",
    71=>"Hafif kar",73=>"Kar",75=>"Yoğun kar",77=>"Kar taneleri",
    80=>"Hafif sağanak",81=>"Sağanak",82=>"Şiddetli sağanak",
    85=>"Hafif kar sağanağı",86=>"Yoğun kar sağanağı",
    95=>"Gök gürültülü fırtına",96=>"Dolulu fırtına",99=>"Şiddetli dolulu fırtına"
];

function http_json($url, $params = [], $timeout = 15) {
    if ($params) $url .= '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'HavaBot/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err || $code !== 200) return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

function cache_oku($key) {
    $c = read_json(HAVA_CACHE_FILE, []);
    $k = strtolower($key);
    if (isset($c[$k]) && (time() - $c[$k]['ts']) < HAVA_CACHE_TTL) return $c[$k]['data'];
    return null;
}
function cache_yaz($key, $data) {
    $c = read_json(HAVA_CACHE_FILE, []);
    $c[strtolower($key)] = ['ts' => time(), 'data' => $data];
    if (count($c) > 300) {
        uasort($c, fn($a,$b) => $b['ts'] - $a['ts']);
        $c = array_slice($c, 0, 300, true);
    }
    write_json(HAVA_CACHE_FILE, $c);
}

// ═══════════ GİRDİ ═══════════
$il    = trim($_REQUEST['il'] ?? $_REQUEST['sehir'] ?? '');
$gun   = isset($_REQUEST['gun']) ? max(1, min(16, (int)$_REQUEST['gun'])) : 7;
$force = isset($_REQUEST['force']) && $_REQUEST['force'] == '1';

if ($il === '') json_out(["success"=>false,"error"=>"il parametresi gerekli"], 400);

$cache_key = $il . '_' . $gun;

if (!$force) {
    $c = cache_oku($cache_key);
    if ($c) {
        $c['cached']   = true;
        $c['telegram'] = '@cmrbaskani';
        $c['kaynak']   = 'sanene mk';
        json_out($c);
    }
}

// ═══════════ 1) GEOCODING ═══════════
$geo = http_json(GEO_URL, [
    'name'     => $il,
    'count'    => 1,
    'language' => 'tr',
    'format'   => 'json'
]);
if (!$geo || empty($geo['results'])) {
    json_out(["success"=>false,"error"=>"'$il' bulunamadı"], 404);
}
$g = $geo['results'][0];
$enlem  = $g['latitude'];
$boylam = $g['longitude'];
$tz     = $g['timezone'] ?? 'Europe/Istanbul';
$ad     = $g['name'] ?? $il;
$ulke   = $g['country'] ?? '';

// ═══════════ 2) HAVA VERİSİ ═══════════
$hava = http_json(HAVA_URL, [
    'latitude'      => $enlem,
    'longitude'     => $boylam,
    'current'       => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code,apparent_temperature,pressure_msl',
    'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max,sunrise,sunset,uv_index_max',
    'hourly'        => 'temperature_2m,precipitation_probability,weather_code,wind_speed_10m',
    'timezone'      => $tz,
    'forecast_days' => $gun
]);
if (!$hava) json_out(["success"=>false,"error"=>"Hava verisi alınamadı"], 502);

$cur = $hava['current'] ?? [];
$d   = $hava['daily']   ?? [];
$h   = $hava['hourly']  ?? [];

$gun_adlari = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];

// ═══════════ 3) GÜNLÜK (haftalık) TAHMİN ═══════════
$gunluk = [];
foreach (($d['time'] ?? []) as $i => $t) {
    $kod = $d['weather_code'][$i] ?? -1;
    $gunluk[] = [
        'tarih'    => $t,
        'gun_adi'  => $gun_adlari[(int)date('w', strtotime($t))],
        'durum'    => $WMO[$kod] ?? 'Bilinmiyor',
        'kod'      => $kod,
        'min'      => $d['temperature_2m_min'][$i] ?? null,
        'max'      => $d['temperature_2m_max'][$i] ?? null,
        'yagis'    => $d['precipitation_sum'][$i] ?? null,
        'ruzgar'   => $d['wind_speed_10m_max'][$i] ?? null,
        'gunes_dog'=> $d['sunrise'][$i] ?? null,
        'gunes_bat'=> $d['sunset'][$i] ?? null,
        'uv'       => $d['uv_index_max'][$i] ?? null,
    ];
}

// ═══════════ 4) SAATLİK ═══════════
$saatlik = [];
$limit = min(48, count($h['time'] ?? []));
for ($i = 0; $i < $limit; $i++) {
    $kod = $h['weather_code'][$i] ?? -1;
    $saatlik[] = [
        'saat'     => $h['time'][$i],
        'sicaklik' => $h['temperature_2m'][$i] ?? null,
        'yagis'    => $h['precipitation_probability'][$i] ?? null,
        'durum'    => $WMO[$kod] ?? 'Bilinmiyor',
        'ruzgar'   => $h['wind_speed_10m'][$i] ?? null,
    ];
}

// ═══════════ 5) ÖZET ═══════════
$ozet = [
    'en_dusuk'    => null,
    'en_yuksek'   => null,
    'ort_sicak'   => null,
    'toplam_yagis'=> 0,
    'gun_sayisi'  => count($gunluk),
];
if ($gunluk) {
    $mins  = array_filter(array_column($gunluk, 'min'), fn($v) => $v !== null);
    $maxs  = array_filter(array_column($gunluk, 'max'), fn($v) => $v !== null);
    $yagis = array_filter(array_column($gunluk, 'yagis'), fn($v) => $v !== null);
    if ($mins) $ozet['en_dusuk']  = min($mins);
    if ($maxs) $ozet['en_yuksek'] = max($maxs);
    if ($mins && $maxs) $ozet['ort_sicak'] = round((array_sum($mins) + array_sum($maxs)) / (count($mins) + count($maxs)), 1);
    if ($yagis) $ozet['toplam_yagis'] = round(array_sum($yagis), 1);
}

$kod = $cur['weather_code'] ?? -1;

$sonuc = [
    'success' => true,
    'konum'   => [
        'ad'     => $ad,
        'ulke'   => $ulke,
        'enlem'  => $enlem,
        'boylam' => $boylam,
        'tz'     => $tz,
    ],
    'anlik'   => [
        'saat'      => $cur['time'] ?? null,
        'sicaklik'  => $cur['temperature_2m'] ?? null,
        'hissedilen'=> $cur['apparent_temperature'] ?? null,
        'nem'       => $cur['relative_humidity_2m'] ?? null,
        'ruzgar'    => $cur['wind_speed_10m'] ?? null,
        'basinc'    => $cur['pressure_msl'] ?? null,
        'durum'     => $WMO[$kod] ?? 'Bilinmiyor',
        'kod'       => $kod,
    ],
    'gunluk'   => $gunluk,
    'saatlik'  => $saatlik,
    'ozet'     => $ozet,
    'gun'      => $gun,
    'kaynak'   => 'sanene mk',
    'telegram' => '@cmrbaskani',
    'ts'       => time(),
];

cache_yaz($cache_key, $sonuc);
json_out($sonuc);