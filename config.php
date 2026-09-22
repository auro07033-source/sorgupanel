<?php
/**
 * config.php — Forex Sorgulama Hizmeti
 * Telegram: @cmrbaskani
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ═══════════════════════════════════════════
// SABİTLER
// ═══════════════════════════════════════════
define('DATA_DIR', __DIR__ . '/data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('CHAT_FILE', DATA_DIR . '/chat.json');
define('SESSION_TTL', 86400 * 7);

// ═══════════════════════════════════════════
// ADMIN — düz metin şifre (basit ve çalışır)
// ═══════════════════════════════════════════
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'forex:qw24');

// ═══════════════════════════════════════════
// YARDIMCI
// ═══════════════════════════════════════════
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0777, true); }

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function read_json($file, $default = []) {
    if (!file_exists($file)) return $default;
    $raw = @file_get_contents($file);
    if (!$raw) return $default;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : $default;
}

function write_json($file, $data) {
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function load_users() { return read_json(USERS_FILE, []); }
function save_users($u) { write_json(USERS_FILE, $u); }

function find_user($username) {
    $u = strtolower(trim($username));
    foreach (load_users() as $user) {
        if (strtolower($user['username']) === $u) return $user;
    }
    return null;
}

function find_user_by_id($id) {
    foreach (load_users() as $user) {
        if ($user['id'] === $id) return $user;
    }
    return null;
}

function current_user() {
    if (!isset($_SESSION['user_id'])) return null;
    return find_user_by_id($_SESSION['user_id']);
}

function is_logged_in() { return current_user() !== null; }
function is_admin() { $u = current_user(); return $u && !empty($u['is_admin']); }

function require_login() {
    if (!is_logged_in()) {
        json_out(["success" => false, "error" => "Oturum gerekli", "login" => true], 401);
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) json_out(["success" => false, "error" => "Yetkin yok"], 403);
}

function gen_id() { return bin2hex(random_bytes(8)); }
function sanitize_username($u) { return preg_replace('/[^a-zA-Z0-9_]/', '', $u); }
function now_iso() { return date('c'); }

function avatar_url($user) {
    if (!empty($user['avatar'])) return $user['avatar'];
    $renk = substr(md5($user['username'] ?? 'user'), 0, 6);
    $harf = strtoupper(substr($user['username'] ?? 'U', 0, 1));
    return "https://ui-avatars.com/api/?name={$harf}&background={$renk}&color=fff&bold=true&size=128";
}

function is_online($user) {
    if (empty($user['last_seen'])) return false;
    return (time() - strtotime($user['last_seen'])) < 300;
}