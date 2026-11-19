<?php

$oj_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $oj_https,
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if ($oj_https) header('Strict-Transport-Security: max-age=31536000');

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function require_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Método no permitido.');
    }

    $received = (string)($_POST['csrf_token'] ?? '');
    if ($received === '' || !hash_equals(csrf_token(), $received)) {
        http_response_code(403);
        exit('La sesión expiró o la solicitud no es válida. Recarga la página e inténtalo nuevamente.');
    }
}

function require_admin_login(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ../login.php');
        exit;
    }
}
