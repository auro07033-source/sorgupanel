<?php
/**
 * admin.php — Admin API
 */
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
                'avatar'     => '',
                'bio'        => 'Yönetici',
                'rank'       => 'Baş Admin',
                'verified'   => true,
                'banned'     => false,
                'is_admin'   => true,
                'created_at' => now_iso(),
                'last_seen'  => now_iso(),
            ];
            $users[] = $admin;
            save_users($users);
        } else {
            // Şifre güncellendiyse hash'i yenile
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
        foreach ($users as &$u) {
            if ($u['id'] === $id) $u['verified'] = (bool)$v;
        }
        save_users($users);
        json_out(["success"=>true, "message"=>$v ? "✅ Tik verildi" : "Tik kaldırıldı"]);

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
            if ($u['id'] === $id) $u['rank'] = mb_substr($rank, 0, 30);
        }
        save_users($users);
        json_out(["success"=>true, "message"=>"Rütbe ayarlandı"]);

    case 'stats':
        require_admin();
        $users = load_users();
        $messages = read_json(CHAT_FILE, []);
        $online = 0; $verified = 0; $banned = 0;
        foreach ($users as $u) {
            if (is_online($u)) $online++;
            if (!empty($u['verified'])) $verified++;
            if (!empty($u['banned'])) $banned++;
        }
        json_out([
            "success"   => true,
            "total"     => count($users),
            "online"    => $online,
            "verified"  => $verified,
            "banned"    => $banned,
            "messages"  => count($messages),
        ]);

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
        'rank'       => $u['rank'] ?? 'Üye',
        'verified'   => !empty($u['verified']),
        'banned'     => !empty($u['banned']),
        'is_admin'   => !empty($u['is_admin']),
        'online'     => is_online($u),
        'last_seen'  => $u['last_seen'] ?? null,
        'created_at' => $u['created_at'] ?? null,
    ];
}