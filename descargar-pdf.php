<?php

require_once __DIR__ . '/private_config.php';

$folio = (string)($_GET['folio'] ?? '');
$expires = (int)($_GET['expires'] ?? 0);
$signature = (string)($_GET['sig'] ?? '');

if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/', $folio) || $expires < time()) {
    http_response_code(403);
    exit('El enlace no es válido o ya venció.');
}

$expected = hash_hmac('sha256', $folio . '|' . $expires, OJ_LINK_SECRET);
if ($signature === '' || !hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('El enlace no es válido.');
}

$path = __DIR__ . '/pdfs/' . $folio . '.pdf';
if (!is_file($path)) {
    http_response_code(404);
    exit('El documento no está disponible.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $folio . '.pdf"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
