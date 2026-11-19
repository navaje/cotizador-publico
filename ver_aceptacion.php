<?php
require_once __DIR__ . '/auth.php';

$archivo = (string)($_GET['archivo'] ?? '');
if (!preg_match('/\AACEPTACION-[A-Za-z0-9_-]+\.pdf\z/D', $archivo)) {
    http_response_code(400);
    exit('Nombre de acta no válido.');
}

$directorio = realpath(__DIR__ . '/pdfs');
if ($directorio === false) {
    http_response_code(404);
    exit('No existe la carpeta de documentos.');
}
$ruta = realpath($directorio . DIRECTORY_SEPARATOR . $archivo);
if ($ruta === false || dirname($ruta) !== $directorio || !is_file($ruta) || !is_readable($ruta)) {
    http_response_code(404);
    exit('El acta no está disponible.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
