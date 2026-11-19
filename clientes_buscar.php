<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app/clientes.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    oj_clientes_schema($pdo);
    $q = oj_clientes_texto($_GET, 'q', 100);
    $sql = 'SELECT id, nombre, telefono, email, direccion FROM cotizador_clientes WHERE usuario_id = ? AND activo = 1';
    $args = [(int)$_SESSION['usuario_id']];
    if ($q !== '') {
        $patron = oj_clientes_patron($q);
        $sql .= " AND (nombre LIKE ? ESCAPE '!' OR telefono LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";
        $args[] = $patron; $args[] = $patron; $args[] = $patron;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY nombre ASC, id ASC LIMIT 26');
    $stmt->execute($args);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['items' => array_slice($items, 0, 25), 'hay_mas' => count($items) > 25], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    if ($e instanceof InvalidArgumentException) $mensaje = $e->getMessage();
    else { error_log('Clientes: ' . $e->getMessage()); $mensaje = 'No se pudo consultar el directorio. Revisa su instalación desde Mis clientes.'; }
    echo json_encode(['error' => $mensaje], JSON_UNESCAPED_UNICODE);
}
