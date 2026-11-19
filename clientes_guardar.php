<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app/clientes.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Usa el botón Guardar cliente del cotizador.']); exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'La sesión expiró o el formulario no es válido. Conserva tu captura y recarga antes de intentar de nuevo.']); exit;
}
try {
    $datos = oj_clientes_validar($_POST);
    oj_clientes_schema($pdo);
    $usuario = (int)$_SESSION['usuario_id'];
    $resultado = oj_clientes_guardar_unico($pdo, $usuario, $datos);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    if ($e instanceof InvalidArgumentException) $mensaje = $e->getMessage();
    else { error_log('Guardar cliente desde cotizador: ' . $e->getMessage()); $mensaje = 'No se pudo guardar el cliente. Intenta de nuevo o revisa la instalación de Mis clientes.'; }
    echo json_encode(['error' => $mensaje], JSON_UNESCAPED_UNICODE);
}
