<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/recibos.php';
date_default_timezone_set('America/Mexico_City');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$id = (int)($_GET['id'] ?? 0); $error = ''; $receiptId = 0;
try {
    $chain = ojr_cadena($pdo, $id); $doc = $chain[0]; $root = (int)end($chain)['id'];
    $rows = ojr_historial($pdo, $root);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_post_csrf();
        if (($_POST['accion'] ?? '') === 'anular') {
            ojr_anular($pdo, $id, (int)($_POST['recibo_id'] ?? 0), ojr_text($_POST, 'motivo', 500, true), $_SESSION);
            header('Location: anticipos.php?id='.$id.'&anulado=1'); exit;
        }
        $receiptId = ojr_guardar($pdo, $id, $_POST, $_SESSION);
        header('Location: anticipos.php?id='.$id.'&recibo='.$receiptId); exit;
    }
} catch (PDOException $e) {
    error_log('OJ recibos: '.$e->getMessage());
    http_response_code(503);
    exit('No se pudo acceder al registro de recibos. Ejecuta MIGRACION_RECIBOS.sql en la base del cotizador; si ya lo hiciste, revisa el registro de errores.');
} catch (Throwable $e) { $error = $e->getMessage(); }
if (!isset($doc, $rows)) { http_response_code(400); exit(ojr_e($error)); }
$paid = ojr_acumulado($rows); $total = ojr_centavos($doc['total']); $balance = $total - $paid;
$token = bin2hex(random_bytes(32));
$_SESSION['ojr_tokens'][$token] = $id;
while (count($_SESSION['ojr_tokens']) > 30) array_shift($_SESSION['ojr_tokens']);
function ojr_input(string $key, string $default = ''): string { return ojr_e(is_scalar($_POST[$key] ?? null) ? (string)$_POST[$key] : $default); }
?>
<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recibos — <?= ojr_e($doc['folio']) ?></title><link rel="stylesheet" href="assets/recibos.css"></head><body><main>
<nav><a href="dashboard.php">Panel</a> · <a href="listado.php">Documentos</a> · <a href="recibos_historial.php">Todos los recibos</a></nav>
<h1>Recibos de pago · <?= ojr_e($doc['folio']) ?></h1>
<p><strong><?= ojr_e($doc['cliente_nombre']) ?></strong> · <?= ojr_e($doc['proyecto']) ?></p>
<p>Documentos de origen:
<?php foreach (array_reverse($chain) as $related): ?> <a href="ver-cotizacion.php?id=<?= (int)$related['id'] ?>" target="_blank" rel="noopener"><?= ojr_e($related['folio']) ?></a> <?php endforeach; ?></p>
<?php if ($error): ?><p class="error"><?= ojr_e($error) ?></p><?php endif; ?>
<?php if (isset($_GET['recibo'])): foreach ($rows as $r): if ((int)$r['id'] === (int)$_GET['recibo']): ?>
<p class="success">Recibo <?= ojr_e($r['folio']) ?> guardado. <a href="recibo_pdf.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener">Abrir PDF</a> · <a href="recibo_pdf.php?id=<?= (int)$r['id'] ?>&descargar=1">Descargar para enviar al cliente</a></p>
<?php endif; endforeach; endif; ?>
<?php if (isset($_GET['anulado'])): ?><p class="success">Recibo anulado. El historial conserva su registro y el saldo se recalculó.</p><?php endif; ?>
<div class="cards"><div>Total de <?= ojr_e($doc['folio']) ?><strong><?= ojr_money($total) ?></strong></div><div>Pagos registrados<strong><?= ojr_money($paid) ?></strong></div><div>Saldo actual<strong><?= $balance >= 0 ? ojr_money($balance) : 'Revisar: pagos superiores al total' ?></strong></div></div>
<p class="notice">El saldo solo incluye los pagos registrados en este módulo. Antes de cobrar, registra también los anticipos anteriores con su fecha real. El estado «Anticipo» o «Pagado» del panel no aporta un importe por sí solo. Las notas derivadas de la misma cotización comparten los pagos.</p>
<?php if ($balance > 0): ?>
<h2>Registrar dinero recibido</h2><form method="post" action="anticipos.php?id=<?= $id ?>">
<?= csrf_field() ?><input type="hidden" name="solicitud_token" value="<?= $token ?>">
<div class="grid"><label>Importe recibido (MXN)<input name="importe" type="number" min="0.01" step="0.01" max="<?= ojr_decimal($balance) ?>" required value="<?= ojr_input('importe') ?>"></label>
<label>Fecha y hora de recepción<input name="fecha_pago" type="datetime-local" required max="<?= date('Y-m-d\TH:i') ?>" value="<?= ojr_input('fecha_pago',date('Y-m-d\TH:i')) ?>"></label>
<label>Forma de pago<select id="forma_pago" name="forma_pago"><option value="efectivo">Efectivo</option><option value="transferencia" <?= ($_POST['forma_pago'] ?? '') === 'transferencia' ? 'selected' : '' ?>>Transferencia bancaria</option></select></label>
<label>Persona que entregó / transfirió<input name="entrego" maxlength="160" required value="<?= ojr_input('entrego',$doc['cliente_nombre']) ?>"></label>
<label>Persona que recibió<input name="recibio" maxlength="160" required value="<?= ojr_input('recibio',$_SESSION['usuario_nombre'] ?? '') ?>"></label>
<label class="bank">Banco receptor<input id="banco" name="banco" maxlength="100" value="<?= ojr_input('banco') ?>"></label>
<label class="bank">Referencia / clave de rastreo<input id="referencia" name="referencia" maxlength="160" value="<?= ojr_input('referencia') ?>"></label></div>
<label>Concepto del pago<textarea name="concepto" maxlength="1000" required><?= ojr_input('concepto','Pago a cuenta del trabajo '.$doc['folio'].': '.$doc['proyecto']) ?></textarea></label>
<label>Observaciones<textarea name="observaciones" maxlength="1000"><?= ojr_input('observaciones') ?></textarea></label>
<label class="check"><input type="checkbox" name="confirmado" value="1" required> Confirmo que recibí el dinero; en transferencias, comprobé el abono en mi cuenta. Ya registré los pagos anteriores de este trabajo.</label>
<p>El sistema clasifica el recibo como anticipo, abono o liquidación según el saldo.</p><button>Guardar recibo</button>
</form><?php else: ?><p class="notice">No hay saldo disponible para registrar otro pago. Revisa el total del documento y los recibos si hay una diferencia.</p><?php endif; ?>
<h2>Historial del trabajo</h2><div class="scroll"><table><thead><tr><th>Folio</th><th>Fecha de recepción</th><th>Importe</th><th>Forma</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= ojr_e($r['folio']) ?></td><td><?= ojr_e($r['fecha_pago']) ?></td><td><?= ojr_money(ojr_centavos($r['importe'])) ?></td><td><?= ojr_e($r['forma_pago']) ?></td><td><?= $r['anulado_en'] === null ? 'Vigente' : 'ANULADO' ?></td><td>
<a href="recibo_pdf.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener">PDF</a> · <a href="recibo_pdf.php?id=<?= (int)$r['id'] ?>&descargar=1">Descargar</a>
<?php if ($r['anulado_en'] === null && ($_SESSION['usuario_rol'] ?? '') === 'admin'): ?>
<details><summary>Anular por error</summary><form method="post" action="anticipos.php?id=<?= $id ?>" onsubmit="return confirm('¿Anular este recibo y descontarlo de los pagos registrados? No se borra el historial.');">
<?= csrf_field() ?><input type="hidden" name="accion" value="anular"><input type="hidden" name="recibo_id" value="<?= (int)$r['id'] ?>"><label>Motivo<input name="motivo" maxlength="500" required></label><button class="danger">Anular recibo</button></form></details>
<?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6">Aún no hay recibos para este trabajo.</td></tr><?php endif; ?></tbody></table></div>
<p class="notice">Los recibos no se editan ni se borran. Si registraste un pago por error, el administrador puede anularlo e ingresar el correcto. Envía al cliente el comprobante de anulación si ya recibió el PDF anterior.</p>
</main><script>function bankFields(){var yes=document.getElementById('forma_pago').value==='transferencia';document.querySelectorAll('.bank').forEach(function(el){el.style.display=yes?'block':'none';el.querySelector('input').required=yes;});}var method=document.getElementById('forma_pago');if(method){method.addEventListener('change',bankFields);bankFields();}</script></body></html>
