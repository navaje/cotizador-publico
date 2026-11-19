<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/listado_filtros.php';
require_once __DIR__ . '/app/exportacion_csv.php';
ini_set('display_errors', '0');
header('Cache-Control: no-store');
$filtros = oj_listado_filtros($_GET);
if ($filtros['errores']) {
    http_response_code(422);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Revisa los filtros</title><body style="font-family:Arial;padding:24px"><h1>Revisa los filtros antes de exportar</h1>';
    foreach ($filtros['errores'] as $error) echo '<p>' . oj_listado_e($error) . '</p>';
    echo '<a href="' . oj_listado_e(oj_listado_url($filtros, 1)) . '">Volver al listado</a></body></html>';
    exit;
}
try {
    $whereSql = $filtros['where'] ? ' WHERE ' . implode(' AND ', $filtros['where']) : '';
    // Todos los resultados del filtro, sin el límite de la página del listado.
    $stmt = $pdo->prepare('SELECT folio, tipo_documento, fecha, cliente_nombre, cliente_telefono, cliente_email, proyecto, subtotal, iva, total, estado_pago FROM cotizaciones' . $whereSql . ' ORDER BY id DESC');
    $stmt->execute($filtros['params']);
    // Preparar primero el archivo evita entregar una descarga incompleta si falla la consulta.
    $archivo = fopen('php://temp/maxmemory:5242880', 'w+b');
    if ($archivo === false) throw new RuntimeException('No se pudo preparar el CSV.');
    if (fwrite($archivo, "\xEF\xBB\xBF") === false) throw new RuntimeException('No se pudo escribir el CSV.');
    $cabeceras = ['Folio','Tipo de documento','Fecha','Cliente','Teléfono','Correo','Proyecto','Subtotal','IVA','Total','Estado de pago','Moneda'];
    if (fputcsv($archivo, $cabeceras, ',', '"', '') === false) throw new RuntimeException('No se pudo escribir el CSV.');
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (fputcsv($archivo, oj_csv_documento($fila), ',', '"', '') === false) throw new RuntimeException('No se pudo escribir el CSV.');
    }
    rewind($archivo);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Ojo_Jarocho_Documentos_' . date('Y-m-d_H-i-s') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    fpassthru($archivo);
    fclose($archivo);
    exit;
} catch (Throwable $e) {
    if (isset($archivo) && is_resource($archivo)) fclose($archivo);
    error_log('Exportación de documentos: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Exportación</title><body style="font-family:Arial;padding:24px"><h1>No se pudo preparar la exportación</h1><p>Intenta de nuevo o revisa el registro de errores del hosting.</p><a href="' . oj_listado_e(oj_listado_url($filtros, 1)) . '">Volver al listado</a></body></html>';
    exit;
}
