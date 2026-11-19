<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app/catalogo.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    oj_catalogo_schema($pdo);
    $q = oj_catalogo_texto($_GET, 'q', 100);
    $categoria = oj_catalogo_texto($_GET, 'categoria', 20);
    if ($categoria !== '' && !in_array($categoria, ['producto','servicio'], true)) throw new InvalidArgumentException('Filtro no válido.');
    $sql = 'SELECT id, codigo, nombre, categoria, unidad, precio FROM cotizador_catalogo WHERE usuario_id = ? AND activo = 1';
    $args = [(int)$_SESSION['usuario_id']];
    if ($q !== '') {
        // Escapar los comodines para que una búsqueda de modelo sea literal.
        $patron = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $q) . '%';
        $sql .= " AND (nombre LIKE ? ESCAPE '!' OR codigo LIKE ? ESCAPE '!')";
        $args[] = $patron; $args[] = $patron;
    }
    if ($categoria !== '') { $sql .= ' AND categoria = ?'; $args[] = $categoria; }
    $stmt = $pdo->prepare($sql . ' ORDER BY nombre ASC, id ASC LIMIT 26');
    $stmt->execute($args);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hayMas = count($items) > 25;
    echo json_encode(['items' => array_slice($items, 0, 25), 'hay_mas' => $hayMas], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    if ($e instanceof InvalidArgumentException) $mensaje = $e->getMessage();
    else { error_log('Catálogo: ' . $e->getMessage()); $mensaje = 'No se pudo cargar el catálogo. Revisa su instalación desde Mis conceptos.'; }
    echo json_encode(['error' => $mensaje], JSON_UNESCAPED_UNICODE);
}
