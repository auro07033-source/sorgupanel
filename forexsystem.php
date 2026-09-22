<?php
/**
 * forexsystem.php — Forex Sorgulama API proxy
 * Telegram: @cmrbaskani
 */
require_once __DIR__ . '/config.php';

define('AJAX_BASE', 'https://apiv2.ajaxsystems.fun');

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'query':
        require_login();
        $type = $_GET['type'] ?? $_POST['type'] ?? '';
        if (!$type) json_out(["success"=>false,"error"=>"type gerekli"], 400);

        $endpoints = [
            'tc'        => ['path'=>'/tc.php',       'params'=>['tc']],
            'tcpro'     => ['path'=>'/tcpro.php',    'params'=>['tc']],
            'adsoyad'   => ['path'=>'/adsoyad.php',  'params'=>['ad','soyad']],
            'aile'      => ['path'=>'/aile.php',     'params'=>['tc']],
            'ailepro'   => ['path'=>'/ailepro.php',  'params'=>['tc']],
            'sulale'    => ['path'=>'/sulale.php',   'params'=>['tc']],
            'tcgsm'     => ['path'=>'/tcgsm.php',    'params'=>['tc']],
            'gsmtc'     => ['path'=>'/gsmtc.php',    'params'=>['gsm','auth']],
            'eokul'     => ['path'=>'/eokul.php',    'params'=>['tc']],
            'adres'     => ['path'=>'/adres.php',    'params'=>['tc']],
            'tapu'      => ['path'=>'/tapu.php',     'params'=>['tc']],
            'adaparsel' => ['path'=>'/adaparsel.php', 'params'=>['il','ilce','mahalle','ada','parsel']],
        ];

        if (!isset($endpoints[$type])) json_out(["success"=>false,"error"=>"Geçersiz type"], 400);

        $cfg = $endpoints[$type];
        $params = [];
        foreach ($cfg['params'] as $p) {
            $v = $_GET[$p] ?? $_POST[$p] ?? '';
            if ($v !== '') $params[$p] = $v;
        }
        if (empty($params)) json_out(["success"=>false,"error"=>"Parametre gerekli","params"=>$cfg['params']], 400);
        if ($type === 'gsmtc' && !isset($params['auth'])) $params['auth'] = 'fire';

        $url = AJAX_BASE . $cfg['path'] . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'ForexSorgu/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) json_out(["success"=>false,"error"=>"cURL: $err"]);
        if ($code !== 200) json_out(["success"=>false,"error"=>"HTTP $code"]);

        $json = json_decode($body, true);
        if ($json === null) json_out(["success"=>false,"error"=>"Geçersiz JSON"], 500);

        $json['type'] = $type;
        json_out($json);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}