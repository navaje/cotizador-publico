<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app/catalogo.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); header('Allow: POST');
    echo json_encode(['error' => 'Usa el botón Guardar en Mis conceptos.']); exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || $token === '' || !hash_equals(csrf_token(),$token)) {
    http_response_code(403);
    echo json_encode(['error' => 'La sesión expiró o el formulario no es válido. Conserva tu captura y recarga antes de intentar de nuevo.']); exit;
}
try {
    $datos = oj_catalogo_validar($_POST);
    oj_catalogo_schema($pdo);
    $resultado = oj_catalogo_guardar_unico($pdo,(int)$_SESSION['usuario_id'],$datos);
    echo json_encode($resultado,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    if ($e instanceof InvalidArgumentException) $mensaje = $e->getMessage();
    else { error_log('Guardar cálculo en catálogo: ' . $e->getMessage()); $mensaje = 'No se pudo guardar el concepto. Intenta de nuevo o revisa la instalación de Mis conceptos.'; }
    echo json_encode(['error' => $mensaje],JSON_UNESCAPED_UNICODE);
}
