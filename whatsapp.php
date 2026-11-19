<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/private_config.php';
$brand = require __DIR__ . '/branding.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido');
}

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("
    SELECT id, folio, cliente_nombre, cliente_telefono, total, tipo_documento
    FROM cotizaciones
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    die('Documento no encontrado');
}

function limpiarTelefono($telefono) {
    $telefono = preg_replace('/\D+/', '', $telefono ?? '');
    if ($telefono === '') {
        return '';
    }

    if (strlen($telefono) === 10) {
        $telefono = '52' . $telefono;
    }

    return $telefono;
}

$telefono = limpiarTelefono($doc['cliente_telefono']);

$tipo = 'documento';
if (($doc['tipo_documento'] ?? '') === 'cotizacion') {
    $tipo = 'cotización';
} elseif (($doc['tipo_documento'] ?? '') === 'nota_venta') {
    $tipo = 'nota de venta';
} elseif (($doc['tipo_documento'] ?? '') === 'nota_servicio') {
    $tipo = 'nota de servicio';
}

$total = '$' . number_format((float)$doc['total'], 2, '.', ',');

$rutaServidor = __DIR__ . '/pdfs/' . $doc['folio'] . '.pdf';
$linkPdf = '';

if (file_exists($rutaServidor)) {
    $expires = time() + 7 * 24 * 60 * 60;
    $sig = hash_hmac('sha256', $doc['folio'] . '|' . $expires, OJ_LINK_SECRET);
    $baseUrl = rtrim($brand['web'], '/') . '/cotizador/descargar-pdf.php';
    $linkPdf = $baseUrl . '?folio=' . rawurlencode($doc['folio']) . '&expires=' . $expires . '&sig=' . $sig;
}
if ($linkPdf !== '') {
    $mensaje = "Hola " . $doc['cliente_nombre'] .
        ", te comparto tu " . $tipo . " " . $doc['folio'] . ".\n\n" .
        "Total: " . $total . "\n\n" .
        "Descarga tu PDF aquí:\n" . $linkPdf . "\n\n" .
        "Quedamos atentos a cualquier duda.\n" . $brand['nombre'] . "\n" . $brand['lema'];
} else {
    $mensaje = "Hola " . $doc['cliente_nombre'] .
        ", te comparto tu " . $tipo . " " . $doc['folio'] . ".\n\n" .
        "Total: " . $total . "\n\n" .
        "Aún no tengo el PDF guardado en servidor, pero puedo reenviártelo enseguida.\n\n" .
        $brand['nombre'] . "\n" . $brand['lema'];
}

$url = 'https://wa.me/';
if ($telefono !== '') {
    $url .= $telefono;
}
$url .= '?text=' . urlencode($mensaje);

header('Location: ' . $url);
exit;
