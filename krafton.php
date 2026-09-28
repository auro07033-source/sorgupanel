function krafton_login($email, $password, $ua) {
    // 1. Adım: CSRF token + cookie al
    $ch = curl_init('https://accounts.krafton.com/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_COOKIEJAR      => sys_get_temp_dir() . '/krafton_cookies.txt',
        CURLOPT_COOKIEFILE     => sys_get_temp_dir() . '/krafton_cookies.txt',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8',
        ],
    ]);
    $page = curl_exec($ch);
    $headers = curl_getinfo($ch);
    curl_close($ch);

    if (!$page) return ['status' => 'error', 'message' => 'Ana sayfa alınamadı'];

    // 2. Adım: CSRF token'ı çek (csrfToken / _csrf / XSRF-TOKEN)
    $csrf = null;
    if (preg_match('/name="csrf[_-]?token"\s+value="([^"]+)"/i', $page, $m)) {
        $csrf = $m[1];
    } elseif (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/i', $page, $m)) {
        $csrf = $m[1];
    } elseif (preg_match('/XSRF-TOKEN=([^;]+)/i', $page, $m)) {
        $csrf = urldecode($m[1]);
    } elseif (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', $page, $m)) {
        $csrf = $m[1];
    }

    if (!$csrf) {
        return ['status' => 'error', 'message' => 'CSRF token bulunamadı (sayfa yapısı değişmiş olabilir)'];
    }

    // 3. Adım: Login isteği (CSRF header'ı ile)
    $payload = json_encode([
        'email'             => $email,
        'password'          => $password,
        'trusted_device'    => false,
        'client_id'         => 'local',
        'activationVersion' => 'v2',
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(KRAFTON_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_COOKIEJAR      => sys_get_temp_dir() . '/krafton_cookies.txt',
        CURLOPT_COOKIEFILE     => sys_get_temp_dir() . '/krafton_cookies.txt',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/json',
            'User-Agent: ' . $ua,
            'Origin: https://accounts.krafton.com',
            'Referer: https://accounts.krafton.com/',
            'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8',
            'X-CSRF-TOKEN: ' . $csrf,
            'X-XSRF-TOKEN: ' . $csrf,
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['status' => 'error', 'message' => 'cURL: ' . $err];
    if ($code === 429) return ['status' => 'rate',  'message' => 'Rate limit'];
    if ($code === 404) return ['status' => 'endpoint', 'message' => 'Endpoint 404 — URL değişmiş'];
    if ($code === 403) return ['status' => 'csrf', 'message' => 'CSRF reddedildi (token geçersiz)'];

    $json = json_decode($body, true);
    if (!is_array($json)) {
        return ['status' => 'error', 'message' => 'Geçersiz JSON', 'raw' => substr($body, 0, 300)];
    }

    if ($code === 200 && !empty($json['access_token'])) {
        return ['status' => 'hit', 'message' => 'Giriş başarılı', 'token' => $json['access_token']];
    }

    $errCode = $json['errorCode'] ?? null;
    if ($errCode === 1 || $errCode === 2) {
        return ['status' => 'bad', 'message' => 'Hatalı bilgiler'];
    }

    return ['status' => 'fail', 'message' => $json['message'] ?? 'Bilinmeyen hata', 'raw' => substr($body, 0, 300)];
}