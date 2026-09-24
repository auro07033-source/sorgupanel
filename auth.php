<?php
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'register':
        $username = sanitize_username($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email    = trim($_POST['email'] ?? '');
        $birthday = trim($_POST['birthday'] ?? '');

        if (strlen($username) < 3) json_out(["success"=>false,"error"=>"Kullanıcı adı en az 3 karakter"]);
        if (strlen($password) < 4) json_out(["success"=>false,"error"=>"Şifre en az 4 karakter"]);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(["success"=>false,"error"=>"Geçersiz email"]);
        if (find_user($username)) json_out(["success"=>false,"error"=>"Bu kullanıcı adı alınmış"]);

        $users = load_users();
        $newUser = [
            'id'         => gen_id(),
            'username'   => $username,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'avatar'     => DEFAULT_AVATAR,
            'bio'        => 'Forex Sorgulama Hizmeti üyesi',
            'birthday'   => $birthday,
            'horoscope'  => $birthday ? burcHesapla($birthday) : '',
            'city'       => '',
            'job'        => '',
            'instagram'  => '',
            'telegram'   => '',
            'rank'       => 'Üye',
            'is_vip'     => false,
            'verified'   => false,
            'banned'     => false,
            'is_admin'   => false,
            'created_at' => now_iso(),
            'last_seen'  => now_iso(),
        ];
        $users[] = $newUser;
        save_users($users);

        $_SESSION['user_id'] = $newUser['id'];
        json_out(["success"=>true, "message"=>"Kayıt başarılı", "user"=>public_user($newUser)]);

    case 'login':
        $username = sanitize_username($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = find_user($username);
        if (!$user) json_out(["success"=>false,"error"=>"Kullanıcı bulunamadı"], 401);
        if (!empty($user['banned'])) json_out(["success"=>false,"error"=>"Hesabınız askıya alınmış"], 403);
        if (!password_verify($password, $user['password'])) json_out(["success"=>false,"error"=>"Şifre yanlış"], 401);

        $_SESSION['user_id'] = $user['id'];
        update_last_seen($user['id']);

        json_out(["success"=>true, "message"=>"Giriş başarılı", "user"=>public_user($user)]);

    case 'logout':
        session_destroy();
        json_out(["success"=>true, "message"=>"Çıkış yapıldı"]);

    case 'check':
        $u = current_user();
        if (!$u) json_out(["success"=>true, "logged"=>false]);
        update_last_seen($u['id']);
        json_out(["success"=>true, "logged"=>true, "user"=>public_user($u)]);

    case 'profile':
        require_login();
        $target = $_GET['user'] ?? '';
        $u = find_user($target) ?: current_user();
        if (!$u) json_out(["success"=>false,"error"=>"Kullanıcı bulunamadı"], 404);
        json_out(["success"=>true, "user"=>public_user($u)]);

    case 'update_profile':
        require_login();
        $me = current_user();
        $users = load_users();

        $newBio    = trim($_POST['bio'] ?? '');
        $newEmail  = trim($_POST['email'] ?? $me['email']);
        $newAvatar = trim($_POST['avatar'] ?? $me['avatar']);
        $newBday   = trim($_POST['birthday'] ?? ($me['birthday'] ?? ''));
        $newCity   = trim($_POST['city'] ?? ($me['city'] ?? ''));
        $newJob    = trim($_POST['job'] ?? ($me['job'] ?? ''));
        $newInsta  = trim($_POST['instagram'] ?? ($me['instagram'] ?? ''));
        $newTg     = trim($_POST['telegram'] ?? ($me['telegram'] ?? ''));

        foreach ($users as &$u) {
            if ($u['id'] === $me['id']) {
                $u['bio']   = mb_substr($newBio, 0, 200);
                $u['email'] = $newEmail;
                if ($newAvatar && filter_var($newAvatar, FILTER_VALIDATE_URL)) $u['avatar'] = $newAvatar;
                $u['birthday']  = $newBday;
                $u['horoscope'] = $newBday ? burcHesapla($newBday) : ($u['horoscope'] ?? '');
                $u['city']      = $newCity;
                $u['job']       = $newJob;
                $u['instagram'] = $newInsta;
                $u['telegram']  = $newTg;
            }
        }
        save_users($users);
        json_out(["success"=>true, "message"=>"Profil güncellendi", "user"=>public_user(current_user())]);

    case 'change_password':
        require_login();
        $me = current_user();
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';

        if (!password_verify($old, $me['password'])) json_out(["success"=>false,"error"=>"Mevcut şifre yanlış"]);
        if (strlen($new) < 4) json_out(["success"=>false,"error"=>"Yeni şifre en az 4 karakter"]);

        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $me['id']) $u['password'] = password_hash($new, PASSWORD_DEFAULT);
        }
        save_users($users);
        json_out(["success"=>true, "message"=>"Şifre değiştirildi"]);

    case 'users':
        require_login();
        $users = load_users();
        $list = [];
        foreach ($users as $u) $list[] = public_user($u);
        json_out(["success"=>true, "users"=>$list, "total"=>count($list)]);

    case 'heartbeat':
        if (is_logged_in()) update_last_seen($_SESSION['user_id']);
        json_out(["success"=>true]);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}

function public_user($u) {
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

function update_last_seen($id) {
    $users = load_users();
    foreach ($users as &$u) {
        if ($u['id'] === $id) { $u['last_seen'] = now_iso(); break; }
    }
    save_users($users);
}

function burcHesapla($tarih) {
    $tarih = str_replace('/', '-', $tarih);
    $parts = explode('-', $tarih);
    if (count($parts) !== 3) return '';
    if ((int)$parts[0] > 1900) { $y = (int)$parts[0]; $m = (int)$parts[1]; $d = (int)$parts[2]; }
    else { $d = (int)$parts[0]; $m = (int)$parts[1]; $y = (int)$parts[2]; }
    if (!checkdate($m, $d, $y)) return '';

    $burclar = [
        ['20.01', '18.02', 'Kova ♒'],
        ['19.02', '20.03', 'Balık ♓'],
        ['21.03', '19.04', 'Koç ♈'],
        ['20.04', '20.05', 'Boğa ♉'],
        ['21.05', '20.06', 'İkizler ♊'],
        ['21.06', '22.07', 'Yengeç ♋'],
        ['23.07', '22.08', 'Aslan ♌'],
        ['23.08', '22.09', 'Başak ♍'],
        ['23.09', '22.10', 'Terazi ♎'],
        ['23.10', '21.11', 'Akrep ♏'],
        ['22.11', '21.12', 'Yay ♐'],
        ['22.12', '19.01', 'Oğlak ♑'],
    ];
    foreach ($burclar as [$start, $end, $isim]) {
        [$sm, $sd] = array_map('intval', explode('.', $start));
        [$em, $ed] = array_map('intval', explode('.', $end));
        if ($sm <= $em) {
            if (($m > $sm || ($m == $sm && $d >= $sd)) &&
                ($m < $em || ($m == $em && $d <= $ed))) return $isim;
        } else {
            if ($m >= $sm || $m <= $em) return $isim;
        }
    }
    return '';
}