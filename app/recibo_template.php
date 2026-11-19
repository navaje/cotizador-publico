<!doctype html><html lang="es"><head><meta charset="UTF-8"><style>
@page { margin:28px; }
body { font-family:DejaVu Sans,sans-serif; font-size:11px; color:#1f2937; }
.header { border-bottom:3px solid #00235A; padding-bottom:12px; margin-bottom:16px; }
.brand { font-size:14px; font-weight:bold; color:#00235A; margin-bottom:6px; }
.docname { font-size:18px; font-weight:bold; color:#00235A; margin:8px 0; }
table { width:100%; border-collapse:collapse; } td { vertical-align:top; padding:5px; word-wrap:break-word; }
.box { border:1px solid #d6dee8; background:#f8fafc; padding:10px; margin:12px 0; word-wrap:break-word; }
.section { font-size:12px; font-weight:bold; color:#00235A; margin:12px 0 5px; }
.summary { width:100%; margin-top:12px; } .summary td { border:1px solid #d6dee8; padding:8px; }
.amount { background:#00235A; color:white; font-weight:bold; font-size:14px; }
.right { text-align:right; } .muted { color:#555752; font-size:10px; }
.signature { margin-top:28px; border-top:1px solid #888; width:55%; padding-top:6px; }
.footer { border-top:1px solid #ddd; margin-top:18px; padding-top:8px; font-size:10px; }
.annulled { border:2px solid #b91c1c; color:#b91c1c; padding:10px; margin-bottom:12px; }
</style></head><body>
<?php if ($receipt['anulado_en'] !== null): ?>
<div class="annulled"><strong>RECIBO ANULADO — NO ACREDITA UN PAGO VIGENTE</strong><br>
Fecha: <?= ojr_e($receipt['anulado_en']) ?><br>Motivo: <?= ojr_e($receipt['motivo_anulacion']) ?><br>
Anulado por: <?= ojr_e($receipt['anulado_por']) ?></div>
<?php endif; ?>
<div class="header"><table><tr><td style="width:65%">
<div class="brand"><?= ojr_e($s['empresa']['nombre']) ?></div>
<div class="docname"><?= ojr_e($title) ?></div>
<?= ojr_e($s['empresa']['direccion']) ?><br>
<?= ojr_e($s['empresa']['telefono']) ?> | <?= ojr_e($s['empresa']['email']) ?><br>
<?= ojr_e($s['empresa']['web']) ?>
</td><td class="right"><strong>Folio: <?= ojr_e($receipt['folio']) ?></strong><br>
Fecha de recepción:<br><?= ojr_e(date('d/m/Y H:i', strtotime($receipt['fecha_pago']))) ?><br>
Moneda: pesos mexicanos (MXN)</td></tr></table></div>
<div class="section">Cliente y trabajo</div>
<div class="box"><strong>Cliente:</strong> <?= ojr_e($s['cliente']['nombre']) ?><br>
<strong>Proyecto:</strong> <?= ojr_e($s['proyecto']) ?><br>
<strong>Ubicación:</strong> <?= ojr_e($s['cliente']['direccion']) ?><br>
<strong>Teléfono:</strong> <?= ojr_e($s['cliente']['telefono']) ?></div>
<div class="section">Documentos relacionados</div><div class="box">
<?php foreach ($s['documentos'] as $doc): ?>
<strong><?= ojr_e($doc['tipo']) ?>:</strong> <?= ojr_e($doc['folio']) ?> — PDF: <?= ojr_e($doc['archivo']) ?><br>
<?php endforeach; ?>
<?php foreach ($s['aceptaciones'] as $a): ?>
<strong>Acta de aceptación:</strong> <?= ojr_e($a['archivo']) ?><br>
Registro #<?= (int)$a['id'] ?>; relacionado con <?= ojr_e($a['folio_documento']) ?>;
firmado por <?= ojr_e($a['nombre']) ?> el <?= ojr_e($a['fecha']) ?>.<br>
<?php endforeach; ?>
<?php if (!$s['aceptaciones']): ?><span class="muted">Sin acta de aceptación registrada al emitir este recibo.</span><?php endif; ?>
</div>
<div class="section">Constancia de recepción</div><div class="box">
Recibimos de <strong><?= ojr_e($s['entrego']) ?></strong>, por cuenta del cliente indicado,
la cantidad de <strong><?= ojr_e(ojr_money($s['importe'])) ?></strong> por el siguiente concepto:<br><br>
<?= nl2br(ojr_e($s['concepto'])) ?><br><br>
<strong>Forma de pago:</strong> <?= $receipt['forma_pago'] === 'efectivo' ? 'Efectivo' : 'Transferencia bancaria' ?><br>
<?php if ($receipt['forma_pago'] === 'transferencia'): ?>
<strong>Banco receptor:</strong> <?= ojr_e($s['banco']) ?><br>
<strong>Referencia / clave de rastreo:</strong> <?= ojr_e($s['referencia']) ?><br>
<?php endif; ?>
<strong>Recibió:</strong> <?= ojr_e($s['recibio']) ?></div>
<table class="summary">
<tr><td>Total del documento aplicado</td><td class="right"><?= ojr_e(ojr_money($s['total'])) ?></td></tr>
<tr><td>Pagos anteriores registrados</td><td class="right"><?= ojr_e(ojr_money($s['previo'])) ?></td></tr>
<tr class="amount"><td>Importe recibido en este comprobante</td><td class="right"><?= ojr_e(ojr_money($s['importe'])) ?></td></tr>
<tr><td>Total abonado al emitirlo</td><td class="right"><?= ojr_e(ojr_money($s['acumulado'])) ?></td></tr>
<tr><td><strong>Saldo pendiente al emitirlo</strong></td><td class="right"><strong><?= ojr_e(ojr_money($s['saldo'])) ?></strong></td></tr>
</table>
<p class="muted">Los importes corresponden al registro en la fecha de emisión. Los pagos posteriores o anulaciones se consultan en el historial del trabajo.</p>
<?php if ($s['observaciones'] !== ''): ?><div class="box"><strong>Observaciones:</strong><br><?= nl2br(ojr_e($s['observaciones'])) ?></div><?php endif; ?>
<div class="signature"><?= ojr_e($s['recibio']) ?><br>Nombre y firma de quien recibió (si se imprime)</div>
<div class="footer">Comprobante interno de recepción de dinero. No es una factura CFDI.<br>
Este recibo acredita el pago señalado; la entrega y aceptación del trabajo se documentan por separado.<br>
Emitido el <?= ojr_e($receipt['creado_en']) ?> por <?= ojr_e($receipt['creado_por']) ?>.</div>
</body></html>
