<?php
require_once __DIR__ . '/auth.php'; require_once __DIR__ . '/db.php'; require_once __DIR__ . '/app/recibos.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$search = trim(is_scalar($_GET['q'] ?? '') ? (string)($_GET['q'] ?? '') : '');
try {
    $q = $pdo->prepare('SELECT * FROM oj_recibos WHERE folio LIKE ? OR snapshot LIKE ? ORDER BY id DESC LIMIT 100');
    $term = '%'.substr($search,0,100).'%'; $q->execute([$term, $term]); $rows = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log('OJ historial recibos: '.$e->getMessage()); http_response_code(503); exit('Ejecuta MIGRACION_RECIBOS.sql en la base de datos del cotizador antes de utilizar los recibos.'); }
?><!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Historial de recibos</title><link rel="stylesheet" href="assets/recibos.css"></head><body><main>
<nav><a href="dashboard.php">Panel</a> · <a href="listado.php">Documentos</a></nav><h1>Historial de recibos</h1>
<form method="get"><label>Buscar por folio, cliente, documento o referencia<input name="q" value="<?= ojr_e($search) ?>" maxlength="100"></label><button>Buscar</button></form>
<p>Últimos 100 resultados. Incluye recibos vigentes y anulados.</p><div class="scroll"><table><thead><tr><th>Recibo</th><th>Cliente</th><th>Documento</th><th>Importe</th><th>Recepción</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($rows as $r): $s = json_decode($r['snapshot'], true); $docs = $s['documentos']; $last = end($docs); ?>
<tr><td><?= ojr_e($r['folio']) ?></td><td><?= ojr_e($s['cliente']['nombre']) ?></td><td><?= ojr_e($last['folio']) ?></td><td><?= ojr_money(ojr_centavos($r['importe'])) ?></td><td><?= ojr_e($r['fecha_pago']) ?></td><td><?= $r['anulado_en'] === null ? 'Vigente' : 'ANULADO' ?></td><td><a href="recibo_pdf.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener">PDF</a> · <a href="recibo_pdf.php?id=<?= (int)$r['id'] ?>&descargar=1">Descargar</a> · <a href="anticipos.php?id=<?= (int)$r['documento_id'] ?>">Trabajo</a></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7">No hay recibos para esta búsqueda.</td></tr><?php endif; ?></tbody></table></div></main></body></html>
