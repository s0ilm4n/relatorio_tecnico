<?php
/**
 * Autenticação e segurança
 */
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
session_start();

require_once __DIR__ . '/../config/database.php';

// ===== TOKENS CSRF =====
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('Erro de segurança: token CSRF inválido. Volte atrás e tente novamente.');
    }
}

// ===== AUTENTICAÇÃO =====
function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_username'] = $user['username'];
        return true;
    }
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

function logout() {
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
    session_destroy();
    header('Location: login.php');
    exit;
}

function getUserName() {
    return $_SESSION['user_nome'] ?? 'Técnico';
}

// ===== VALIDAÇÃO DE INPUT =====
function validar_texto($valor, $max = 255) {
    return substr(trim($valor), 0, $max);
}

function validar_email($email) {
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) ? trim($email) : '';
}

function validar_telefone($tel) {
    $tel = preg_replace('/[^0-9+]/', '', trim($tel));
    return strlen($tel) <= 20 ? $tel : '';
}

function validar_data($data) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $parts = explode('-', $data);
        return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]) ? $data : date('Y-m-d');
    }
    return date('Y-m-d');
}

function validar_hora($hora) {
    if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
        return $hora;
    }
    return '';
}

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
