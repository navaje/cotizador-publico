<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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
