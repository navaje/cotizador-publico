<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/descuentos.php';
require_post_csrf();

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    die('ID inválido');
}

$id = (int) $_POST['id'];

// Opcional: solo admin puede borrar
if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    die('No tienes permiso para eliminar documentos.');
}

// Los estados de un trabajo con recibos se derivan de sus pagos.
require_once __DIR__ . '/app/recibos.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $chain = ojr_cadena($pdo, $id);
    $rootId = (int)end($chain)['id'];
    ojr_verificar_motor($pdo);
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id FROM cotizaciones WHERE id = ? FOR UPDATE');
    $lock->execute([$rootId]);
    $checkInspections = $pdo->prepare('SELECT COUNT(*) FROM oj_inspecciones WHERE raiz_id = ?');
    $checkInspections->execute([$rootId]);
    if ((int)$checkInspections->fetchColumn() > 0) {
        $pdo->rollBack();
        http_response_code(409);
        exit('Este trabajo tiene inspecciones. Conserva sus documentos de referencia y consulta el historial de checklist.');
    }
    if (ojr_historial($pdo, $rootId)) {
        $pdo->rollBack();
        http_response_code(409);
        exit('Este trabajo tiene recibos. No se puede eliminar su documento; conserva las referencias y utiliza el historial de recibos.');
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('OJ control de recibos: '.$error->getMessage());
    http_response_code(503);
    exit('Revisa la instalación del módulo: ejecuta MIGRACION_RECIBOS.sql antes de operar con documentos.');
}

// Buscar documento
$stmt = $pdo->prepare("SELECT id, folio FROM cotizaciones WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    die('Documento no encontrado.');
}

// Quitar el desglose antes de borrar sus conceptos.
ojd_borrar($pdo,$id);

// Borrar items primero
$stmtItems = $pdo->prepare("DELETE FROM cotizacion_items WHERE cotizacion_id = ?");
$stmtItems->execute([$id]);

// Borrar documento
$stmtDoc = $pdo->prepare("DELETE FROM cotizaciones WHERE id = ?");
$stmtDoc->execute([$id]);

$pdo->commit();

// Opcional: borrar PDF guardado
$pdf = __DIR__ . '/pdfs/' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $doc['folio']) . '.pdf';
if (file_exists($pdf)) {
    unlink($pdf);
}

header('Location: listado.php');
exit;
