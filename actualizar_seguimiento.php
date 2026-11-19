<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_post_csrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) die('ID inválido');

$origenes = ['web','catalogo','facebook','whatsapp','recomendacion','otro'];
$origen = strtolower(trim((string)($_POST['origen'] ?? 'web')));
if (!in_array($origen, $origenes, true)) $origen = 'web';

$productoNombre = substr(trim((string)($_POST['producto_nombre'] ?? '')), 0, 255);
$productoSku = substr(trim((string)($_POST['producto_sku'] ?? '')), 0, 100);
$productoUrl = substr(trim((string)($_POST['producto_url'] ?? '')), 0, 500);
$proximaAccion = substr(trim((string)($_POST['proxima_accion'] ?? '')), 0, 255);
$notasInternas = trim((string)($_POST['notas_internas'] ?? ''));
$fechaEntrada = trim((string)($_POST['fecha_seguimiento'] ?? ''));

if ($productoUrl !== '' && strpos($productoUrl, 'https://www.productos-info.com/tienda/ojojarocho') !== 0) {
    die('La URL del producto no pertenece al catálogo de Ojo Jarocho.');
}
$fechaSeguimiento = null;
if ($fechaEntrada !== '') {
    $fecha = DateTime::createFromFormat('Y-m-d\TH:i', $fechaEntrada);
    if (!$fecha || $fecha->format('Y-m-d\TH:i') !== $fechaEntrada) die('Fecha de seguimiento inválida.');
    $fechaSeguimiento = $fecha->format('Y-m-d H:i:s');
}

$stmt = $pdo->prepare('UPDATE solicitudes_servicio SET origen=?, producto_nombre=?, producto_sku=?, producto_url=?, proxima_accion=?, fecha_seguimiento=?, notas_internas=? WHERE id=?');
$stmt->execute([
    $origen,
    $productoNombre !== '' ? $productoNombre : null,
    $productoSku !== '' ? $productoSku : null,
    $productoUrl !== '' ? $productoUrl : null,
    $proximaAccion !== '' ? $proximaAccion : null,
    $fechaSeguimiento,
    $notasInternas !== '' ? $notasInternas : null,
    $id
]);

header('Location: ver_solicitud.php?id=' . $id . '&seguimiento=actualizado');
exit;
