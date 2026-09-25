<?php
/**
 * brute.php — Instagram Brute-Force Çalıştırıcı (Simülasyon)
 */
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'run':
        require_login();
        $s = load_settings();
        if (empty($s['brute_enabled'])) json_out(["success"=>false,"error"=>"Brute devre dışı"]);

        $targetUser = trim($_POST['target'] ?? '');
        $wordlist   = sanitize_filename($_POST['wordlist'] ?? '');
        $delay      = max(1, min((int)($_POST['delay'] ?? 5), 60));
        $maxTries   = max(1, min((int)($_POST['max'] ?? 50), 500));

        if ($targetUser === '') json_out(["success"=>false,"error"=>"Hedef kullanıcı gerekli"]);
        if ($wordlist === '')   json_out(["success"=>false,"error"=>"Wordlist seç"]);
        if (!preg_match('/^[a-zA-Z0-9._]{1,30}$/', $targetUser)) json_out(["success"=>false,"error"=>"Geçersiz kullanıcı adı"]);

        $path = WORDLIST_DIR . '/' . $wordlist;
        if (!file_exists($path)) json_out(["success"=>false,"error"=>"Wordlist bulunamadı"]);

        $me = current_user();
        $sonuclar = [];
        $sayac = 0;

        $fh = @fopen($path, 'r');
        if (!$fh) json_out(["success"=>false,"error"=>"Dosya açılamadı"]);

        while (($line = fgets($fh)) !== false && $sayac < $maxTries) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) continue;
            list($u, $p) = $parts;

            if (strtolower($u) !== strtolower($targetUser)) continue;

            $sonuc = [
                'id'       => gen_id(),
                'target'   => $targetUser,
                'username' => $u,
                'password' => $p,
                'success'  => false,
                'status'   => 'test',
                'message'  => 'Simülasyon modu — gerçek istek atılmadı',
                'ts'       => time(),
            ];
            $sonuclar[] = $sonuc;
            $sayac++;
            sleep($delay);
        }
        fclose($fh);

        if (!empty($sonuclar)) {
            $logs = read_json(BRUTE_LOG_FILE, []);
            $logs = array_merge($logs, $sonuclar);
            if (count($logs) > 2000) $logs = array_slice($logs, -2000);
            write_json(BRUTE_LOG_FILE, $logs);
        }

        add_log('brute_run', "target=$targetUser wordlist=$wordlist denenen=$sayac");

        json_out([
            "success"  => true,
            "message"  => "$sayac deneme yapıldı (simülasyon)",
            "denenen"  => $sayac,
            "sonuclar" => $sonuclar,
        ]);

    case 'log':
        require_login();
        $me = current_user();
        $logs = read_json(BRUTE_LOG_FILE, []);
        if (empty($me['is_admin'])) {
            $logs = array_values(array_filter($logs, fn($l) => ($l['target'] ?? '') === $me['username']));
        }
        $logs = array_slice(array_reverse($logs), 0, 100);
        json_out(["success"=>true, "logs"=>$logs]);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}