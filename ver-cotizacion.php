<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/descuentos.php';
require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID no válido');
}

$id = (int) $_GET['id'];

function money($n) {
    return '$' . number_format((float)$n, 2, '.', ',');
}

function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$cotizacion = $stmt->fetch();

if (!$cotizacion) {
    die('Cotización no encontrada');
}

$stmtItems = $pdo->prepare("
    SELECT id, concepto, cantidad, precio, importe
    FROM cotizacion_items
    WHERE cotizacion_id = :id
    ORDER BY id ASC
");
$stmtItems->execute([':id' => $id]);
$items = $stmtItems->fetchAll();

$metasDescuentos = ojd_cargar($pdo,$id);
$hayDescuentos = (bool)$metasDescuentos;
$cabeceraProductos = ojd_pdf_cabecera($hayDescuentos);
$brutoCentavos = 0;
$rows = '';
if (!$items) {
    $rows = '<tr><td colspan="' . ($hayDescuentos ? 6 : 4) . '" class="center">No hay conceptos guardados para este documento.</td></tr>';
} else {
    foreach ($items as $item) {
        $meta = $metasDescuentos[(int)$item['id']] ?? null;
        $rows .= ojd_pdf_fila((string)$item['concepto'],(float)$item['cantidad'],(float)$item['precio'],(float)$item['importe'],$meta,$hayDescuentos);
        $original = (float)($meta['precio_original'] ?? $item['precio']);
        $brutoCentavos += (int)round((float)$item['cantidad'] * (int)round($original*100));
    }
}
$resumenDescuentos = ojd_pdf_resumen($brutoCentavos/100,($brutoCentavos-(int)round((float)$cotizacion['subtotal']*100))/100,$hayDescuentos);

$tipoDocumento = $cotizacion['tipo_documento'] ?? 'cotizacion';

$tituloDocumento = 'Cotización de equipo / instalación';
$nombreDocumento = 'Cotización';
$color = '#0d3b66';

if ($tipoDocumento === 'nota_venta') {
    $tituloDocumento = 'Nota de venta';
    $nombreDocumento = 'NOTA DE VENTA';
    $color = '#15803d';
} elseif ($tipoDocumento === 'nota_servicio') {
    $tituloDocumento = 'NOTA DE SERVICIO';
    $nombreDocumento = 'NOTA DE SERVICIO';
    $color = '#c2410c';
}

// LOGO
$logoPath = __DIR__ . '/assets/logo.png';
$logoHtml = '';

if (file_exists($logoPath)) {
    $logoData = base64_encode(file_get_contents($logoPath));
    $logoHtml = '<img src="data:image/png;base64,' . $logoData . '" style="height:70px; margin-bottom:10px;">';
}

// PLANO
$planoRuta = __DIR__ . '/plano/planos/plano_' . $id . '.png';
$planoHtml = '';

if (file_exists($planoRuta)) {
    $planoData = base64_encode(file_get_contents($planoRuta));
    $planoHtml = '
    <div style="page-break-before: always; text-align:center;">
        <h2 style="margin-bottom:10px; color:' . $color . ';">Plano de instalación</h2>
        <img src="data:image/png;base64,' . $planoData . '" style="width:95%; max-height:700px;">
    </div>';
}

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  @page {
    margin: 28px 28px 34px 28px;
  }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #1f2937;
    margin: 0;
  }

  .header {
    width: 100%;
    border-bottom: 3px solid ' . $color . ';
    padding-bottom: 12px;
    margin-bottom: 18px;
  }

  .header-table {
    width: 100%;
    border-collapse: collapse;
  }

  .header-table td {
    border: none;
    vertical-align: top;
  }

  .brand {
    font-size: 14px;
    font-weight: bold;
    color: ' . $color . ';
    margin-bottom: 4px;
  }

  .docname {
    font-size: 18px;
    font-weight: bold;
    color: ' . $color . ';
    margin-top: 10px;
    line-height: 1.1;
  }

  .subtitle {
    font-size: 13px;
    color: #4b5563;
    margin-top: 4px;
  }

  .folio-box {
    text-align: right;
    font-size: 11px;
    vertical-align: top;
    padding-top: 10px;
  }

  .folio-number {
    background: ' . $color . ';
    color: #fff;
    display: inline-block;
    padding: 8px 12px;
    border-radius: 6px;
    font-weight: bold;
    margin-bottom: 8px;
  }

  .section-title {
    font-size: 13px;
    font-weight: bold;
    color: ' . $color . ';
    margin: 16px 0 8px;
    padding-bottom: 4px;
    border-bottom: 1px solid #dbe3ec;
  }

  .info-box {
    background: #f8fafc;
    border: 1px solid #d6dee8;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
  }

  .info-table {
    width: 100%;
    border-collapse: collapse;
  }

  .info-table td {
    border: none;
    padding: 4px 6px 4px 0;
    vertical-align: top;
  }

  .label {
    font-weight: bold;
    color: #111827;
    width: 130px;
  }

  table.products {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }

  .products th {
    background: ' . $color . ';
    color: #fff;
    font-size: 11px;
    padding: 10px 8px;
    text-align: left;
  }

  .products td {
    border: 1px solid #d9e2ec;
    padding: 8px;
    font-size: 11px;
  }

  .center {
    text-align: center;
  }

  .right {
    text-align: right;
  }

  .totals {
    width: 280px;
    margin-left: auto;
    margin-top: 18px;
    border-collapse: collapse;
  }

  .totals td {
    border: 1px solid #d6dee8;
    padding: 9px 10px;
    font-size: 11px;
  }

  .totals .grand td {
    background: ' . $color . ';
    color: #fff;
    font-weight: bold;
    font-size: 13px;
  }

  .two-col {
    width: 100%;
    border-collapse: collapse;
    margin-top: 18px;
  }

  .two-col td {
    width: 50%;
    vertical-align: top;
    border: none;
    padding-right: 10px;
  }

  .mini-box {
    background: #fafbfd;
    border: 1px solid #dbe3ec;
    border-radius: 8px;
    padding: 10px;
    min-height: 90px;
  }

  .footer {
    margin-top: 22px;
    padding-top: 10px;
    border-top: 1px solid #dbe3ec;
    font-size: 10px;
    color: #4b5563;
  }
</style>
</head>

<body>

<div class="header">
  <table class="header-table">
    <tr>
      <td style="width:60%;">
        ' . $logoHtml . '
        <div class="brand">' . e($cotizacion['empresa_nombre']) . '</div>
        <div class="docname">' . e($nombreDocumento) . '</div>
        <div class="subtitle">' . e($tituloDocumento) . '</div>
        <div style="margin-top:8px;">
          ' . e($cotizacion['empresa_direccion']) . '<br>
          ' . e($cotizacion['empresa_telefono']) . ' | ' . e($cotizacion['empresa_email']) . '<br>
          ' . e($cotizacion['empresa_web']) . '
        </div>
      </td>
      <td style="width:40%;" class="folio-box">
        <div class="folio-number">Folio: ' . e($cotizacion['folio']) . '</div><br>
        <strong>Fecha:</strong> ' . date('d/m/Y', strtotime($cotizacion['fecha'])) . '<br>
        <strong>Vigencia:</strong> ' . e($cotizacion['vigencia']) . '<br>
        <strong>Pago:</strong> ' . e($cotizacion['metodo_pago']) . '<br>
        <strong>Estado:</strong> ' . strtoupper(e($cotizacion['estado_pago'] ?? 'pendiente')) . '
      </td>
    </tr>
  </table>
</div>

<div class="section-title">Datos del cliente</div>
<div class="info-box">
  <table class="info-table">
    <tr>
      <td class="label">Cliente:</td>
      <td>' . e($cotizacion['cliente_nombre']) . '</td>
      <td class="label">Proyecto:</td>
      <td>' . e($cotizacion['proyecto']) . '</td>
    </tr>
    <tr>
      <td class="label">Teléfono:</td>
      <td>' . e($cotizacion['cliente_telefono']) . '</td>
      <td class="label">Correo:</td>
      <td>' . e($cotizacion['cliente_email']) . '</td>
    </tr>
    <tr>
      <td class="label">Ubicación:</td>
      <td colspan="3">' . nl2br(e($cotizacion['cliente_direccion'])) . '</td>
    </tr>
  </table>
</div>

<div class="section-title">Descripción general</div>
<div class="info-box">
  ' . nl2br(e($cotizacion['descripcion'])) . '
</div>

<div class="section-title">Conceptos cotizados</div>
<table class="products">
  <thead>
    <tr>
      ' . $cabeceraProductos . '
    </tr>
  </thead>
  <tbody>
    ' . $rows . '
  </tbody>
</table>

<table class="totals">
  ' . $resumenDescuentos . '
  <tr>
    <td><strong>Subtotal</strong></td>
    <td class="right">' . money($cotizacion['subtotal']) . '</td>
  </tr>
  <tr>
    <td><strong>IVA (16%)</strong></td>
    <td class="right">' . money($cotizacion['iva']) . '</td>
  </tr>
  <tr class="grand">
    <td><strong>Total</strong></td>
    <td class="right">' . money($cotizacion['total']) . '</td>
  </tr>
</table>

<table class="two-col">
  <tr>
    <td>
      <div class="section-title">Instalación y observaciones</div>
      <div class="mini-box">
        <strong>Tiempo estimado:</strong> ' . e($cotizacion['tiempo_instalacion']) . '<br><br>
        ' . nl2br(e($cotizacion['observaciones'])) . '
      </div>
    </td>
    <td>
      <div class="section-title">Condiciones</div>
      <div class="mini-box">
        ' . nl2br(e($cotizacion['condiciones'])) . '
      </div>
    </td>
  </tr>
</table>

' . $planoHtml . '

<div class="footer">
  Gracias por considerar a <strong>' . e($cotizacion['empresa_nombre']) . '</strong>.<br>
  Para cualquier aclaración o confirmación, contáctanos al ' . e($cotizacion['empresa_telefono']) . ' o por correo a ' . e($cotizacion['empresa_email']) . '.
</div>

</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($cotizacion['folio'] . '.pdf', ['Attachment' => false]);