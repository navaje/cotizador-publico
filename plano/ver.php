<?php
require_once __DIR__ . '/../auth.php';

$archivo = basename((string)($_GET['archivo'] ?? ''));
if (!preg_match('/^plano_[A-Za-z0-9_-]+\.png$/', $archivo)) {
    http_response_code(400);
    exit('Archivo no válido.');
}

$ruta = __DIR__ . '/planos/' . $archivo;
if (!is_file($ruta)) {
    http_response_code(404);
    exit('Plano no encontrado.');
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
