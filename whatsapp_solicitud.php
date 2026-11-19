<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
$brand = require __DIR__ . '/branding.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido');
}

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM solicitudes_servicio WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    die('Solicitud no encontrada');
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

$telefono = limpiarTelefono($solicitud['cliente_telefono']);

$mensaje = "Hola " . $solicitud['cliente_nombre'] . 
    ", recibimos tu solicitud de servicio " . $solicitud['folio'] . ".\n\n" .
    (!empty($solicitud['producto_nombre']) ? "Producto: " . $solicitud['producto_nombre'] . "\n" : '') .
    "Equipo: " . $solicitud['tipo_equipo'] . "\n" .
    "Servicio: " . $solicitud['tipo_servicio'] . "\n" .
    "Fecha preferida: " . $solicitud['fecha_preferida'] . " " . $solicitud['horario_preferido'] . "\n\n" .
    "Te contactamos para confirmar la visita.\n" .
    $brand['nombre'] . "\n" . $brand['lema'];

$url = 'https://wa.me/';
if ($telefono !== '') {
    $url .= $telefono;
}
$url .= '?text=' . urlencode($mensaje);

header('Location: ' . $url);
exit;
