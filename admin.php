<?php
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'admin_login':
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        if ($user !== ADMIN_USER) json_out(["success"=>false,"error"=>"Kullanıcı adı yanlış"], 401);
        if ($pass !== ADMIN_PASS) json_out(["success"=>false,"error"=>"Şifre yanlış"], 401);

        $admin = find_user(ADMIN_USER);
        if (!$admin) {
            $users = load_users();
            $admin = [
                'id'         => gen_id(),
                'username'   => ADMIN_USER,
                'email'      => 'admin@forex.local',
                'password'   => password_hash($pass, PASSWORD_DEFAULT),
                'avatar'     => DEFAULT_AVATAR,
                'bio'        => 'Yönetici',
                'birthday'   => '',
                'horoscope'  => '',
                'city'       => '',
                'job'        => '',
                'instagram'  => '',
                'telegram'   => '',
                'rank'       => 'Baş Admin',
                'is_vip'     => true,
                'verified'   => true,
                'banned'     => false,
                'is_admin'   => true,
                'created_at' => now_iso(),
                'last_seen'  => now_iso(),
            ];
            $users[] = $admin;
            save_users($users);
        } else {
            $users = load_users();
            foreach ($users as &$u) {
                if ($u['id'] === $admin['id']) {
                    $u['password'] = password_hash($pass, PASSWORD_DEFAULT);
                    $u['is_admin'] = true;
                }
            }
            save_users($users);
        }

        $_SESSION['user_id'] = $admin['id'];
        json_out(["success"=>true, "message"=>"Admin girişi başarılı", "user"=>public_user_admin($admin)]);

    case 'admin_check':
        $u = current_user();
        if (!$u || empty($u['is_admin'])) json_out(["success"=>true, "logged"=>false]);
        json_out(["success"=>true, "logged"=>true, "user"=>public_user_admin($u)]);

    case 'list_users':
        require_admin();
        $users = load_users();
        $out = [];
        foreach ($users as $u) $out[] = public_user_admin($u);
        json_out(["success"=>true, "users"=>$out, "total"=>count($out)]);

    case 'ban':
        require_admin();
        $id = $_POST['id'] ?? '';
        $ban = (int)($_POST['ban'] ?? 1);
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                if (!empty($u['is_admin'])) json_out(["success"=>false,"error"=>"Admin banlanamaz"]);
                $u['banned'] = (bool)$ban;
            }
        }
        save_users($users);
        json_out(["success"=>true, "message"=>$ban ? "Banlandı" : "Ban kaldırıldı"]);

    case 'verify':
        require_admin();
        $id = $_POST['id'] ?? '';
        $v = (int)($_POST['verified'] ?? 1);
        $users = load_users();
        foreach ($users as &$u) if ($u['id'] === $id) $u['verified'] = (bool)$v;
        save_users($users);
        json_out(["success"=>true, "message"=>$v ? "✅ Tik verildi" : "Tik kaldırıldı"]);

    case 'vip':
        require_admin();
        $id = $_POST['id'] ?? '';
        $vip = (int)($_POST['vip'] ?? 1);
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                $u['is_vip'] = (bool)$vip;
                if ($vip) $u['rank'] = 'VIP';
                elseif ($u['rank'] === 'VIP') $u['rank'] = 'Üye';
            }
        }
        save_users($users);
        json_out(["success"=>true, "message"=>$vip ? "💎 VIP verildi" : "VIP kaldırıldı"]);

    case 'delete_user':
        require_admin();
        $id = $_POST['id'] ?? '';
        $users = load_users();
        $new = [];
        foreach ($users as $u) {
            if ($u['id'] === $id) {
                if (!empty($u['is_admin'])) json_out(["success"=>false,"error"=>"Admin silinemez"]);
                continue;
            }
            $new[] = $u;
        }
        save_users($new);
        json_out(["success"=>true, "message"=>"Hesap silindi"]);

    case 'set_rank':
        require_admin();
        $id = $_POST['id'] ?? '';
        $rank = trim($_POST['rank'] ?? '');
        if ($rank === '') json_out(["success"=>false,"error"=>"Rütbe gerekli"]);
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                $u['rank'] = mb_substr($rank, 0, 30);
                $u['is_vip'] = ($rank === 'VIP');
            }
        }
        save_users($users);
        json_out(["success"=>true, "message"=>"Rütbe ayarlandı"]);

    case 'stats':
        require_admin();
        $users = load_users();
        $messages = read_json(CHAT_FILE, []);
        $aiMsgs   = read_json(AI_CHAT_FILE, []);
        $online = 0; $verified = 0; $banned = 0; $vip = 0;
        foreach ($users as $u) {
            if (is_online($u)) $online++;
            if (!empty($u['verified'])) $verified++;
            if (!empty($u['banned'])) $banned++;
            if (!empty($u['is_vip']) || ($u['rank'] ?? '') === 'VIP') $vip++;
        }
        json_out([
            "success"     => true,
            "total"       => count($users),
            "online"      => $online,
            "verified"    => $verified,
            "banned"      => $banned,
            "vip"         => $vip,
            "messages"    => count($messages),
            "ai_messages" => count($aiMsgs),
        ]);

    case 'settings':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $s = load_settings();
            if (isset($_POST['theme']))        $s['theme'] = $_POST['theme'];
            if (isset($_POST['announcement'])) $s['announcement'] = $_POST['announcement'];
            if (isset($_POST['maintenance']))  $s['maintenance'] = (bool)$_POST['maintenance'];
            if (isset($_POST['ai_enabled']))   $s['ai_enabled'] = (bool)$_POST['ai_enabled'];
            save_settings($s);
            json_out(["success"=>true, "message"=>"Ayarlar güncellendi", "settings"=>$s]);
        }
        json_out(["success"=>true, "settings"=>load_settings()]);

    case 'clear_chat':
        require_admin();
        write_json(CHAT_FILE, []);
        json_out(["success"=>true, "message"=>"Sohbet temizlendi"]);

    case 'clear_ai':
        require_admin();
        write_json(AI_CHAT_FILE, []);
        json_out(["success"=>true, "message"=>"AI geçmişi temizlendi"]);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}

function public_user_admin($u) {
    if (!$u) return null;
    return [
        'id'         => $u['id'],
        'username'   => $u['username'],
        'email'      => $u['email'] ?? '',
        'avatar'     => avatar_url($u),
        'bio'        => $u['bio'] ?? '',
        'birthday'   => $u['birthday'] ?? '',
        'horoscope'  => $u['horoscope'] ?? '',
        'city'       => $u['city'] ?? '',
        'job'        => $u['job'] ?? '',
        'instagram'  => $u['instagram'] ?? '',
        'telegram'   => $u['telegram'] ?? '',
        'rank'       => $u['rank'] ?? 'Üye',
        'is_vip'     => !empty($u['is_vip']) || ($u['rank'] ?? '') === 'VIP',
        'verified'   => !empty($u['verified']),
        'banned'     => !empty($u['banned']),
        'is_admin'   => !empty($u['is_admin']),
        'online'     => is_online($u),
        'last_seen'  => $u['last_seen'] ?? null,
        'created_at' => $u['created_at'] ?? null,
    ];
}