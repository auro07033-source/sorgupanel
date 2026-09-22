<?php
/**
 * chat.php — Genel sohbet
 * Telegram: @cmrbaskani
 */
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // ─── MESAJLARI ÇEK ───
    case 'messages':
        require_login();
        $since = (int)($_GET['since'] ?? 0);
        $messages = read_json(CHAT_FILE, []);

        // Sadece son 200 mesaj
        $messages = array_slice($messages, -200);

        // since'den sonrakiler
        if ($since > 0) {
            $messages = array_filter($messages, fn($m) => $m['ts'] > $since);
            $messages = array_values($messages);
        }

        // Kullanıcı bilgisi ekle
        $out = [];
        foreach ($messages as $m) {
            $sender = find_user_by_id($m['user_id']);
            $out[] = [
                'id'       => $m['id'],
                'user_id'  => $m['user_id'],
                'username' => $sender['username'] ?? 'Bilinmeyen',
                'avatar'   => avatar_url($sender ?: []),
                'rank'     => $sender['rank'] ?? 'Üye',
                'verified' => !empty($sender['verified']),
                'text'     => $m['text'],
                'ts'       => $m['ts'],
                'time'     => date('H:i', $m['ts']),
                'date'     => date('d.m.Y', $m['ts']),
            ];
        }

        json_out(["success"=>true, "messages"=>$out]);

    // ─── MESAJ GÖNDER ───
    case 'send':
        require_login();
        $me = current_user();
        $text = trim($_POST['text'] ?? '');

        if ($text === '') json_out(["success"=>false,"error"=>"Boş mesaj"]);
        if (mb_strlen($text) > 500) json_out(["success"=>false,"error"=>"Mesaj çok uzun (max 500)"]);

        $messages = read_json(CHAT_FILE, []);
        $messages[] = [
            'id'      => gen_id(),
            'user_id' => $me['id'],
            'text'    => $text,
            'ts'      => time(),
        ];

        // Son 500 mesajı tut
        if (count($messages) > 500) {
            $messages = array_slice($messages, -500);
        }

        write_json(CHAT_FILE, $messages);
        json_out(["success"=>true, "message"=>"Gönderildi"]);

    // ─── MESAJ SİL (kendi mesajını veya admin) ───
    case 'delete':
        require_login();
        $me = current_user();
        $msgId = $_POST['id'] ?? '';

        $messages = read_json(CHAT_FILE, []);
        $new = [];
        foreach ($messages as $m) {
            if ($m['id'] === $msgId) {
                if ($m['user_id'] === $me['id'] || is_admin()) {
                    continue; // sil
                }
            }
            $new[] = $m;
        }
        write_json(CHAT_FILE, $new);
        json_out(["success"=>true, "message"=>"Silindi"]);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}