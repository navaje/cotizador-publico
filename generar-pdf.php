<?php
   

require_once __DIR__ . '/auth.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso directo bloqueado');
}
require_post_csrf();

// Los errores detallados se registran, pero no se muestran a visitantes.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/dompdf/autoload.inc.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/folios.php';
require_once __DIR__ . '/app/descuentos.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function money($n) {
    return '$' . number_format((float)$n, 2, '.', ',');
}

function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// EMPRESA
$stmtEmpresa = $pdo->query("SELECT * FROM empresa LIMIT 1");
$empresa = $stmtEmpresa->fetch();

if (!$empresa) {
    die('No hay datos configurados en la tabla empresa.');
}

// Solo el botón de vista previa envía esta marca; valores inválidos se rechazan.
if (isset($_POST['vista_previa']) && $_POST['vista_previa'] !== '1') {
    http_response_code(422);
    exit('Modo de vista previa no válido. Regresa al formulario.');
}
$esVistaPrevia = isset($_POST['vista_previa']) && $_POST['vista_previa'] === '1';

// TIPO DE DOCUMENTO
$tipoDocumento = $_POST['tipo_documento'] ?? 'cotizacion';
$solicitud_id = null;

if (isset($_POST['solicitud_id']) && is_numeric($_POST['solicitud_id'])) {
    $solicitud_id = (int)$_POST['solicitud_id'];
}
if (!in_array($tipoDocumento, ['cotizacion', 'nota_venta', 'nota_servicio'], true)) {
    $tipoDocumento = 'cotizacion';
}

// DATOS DEL FORMULARIO
$cliente_nombre    = trim($_POST['cliente_nombre'] ?? '');
$cliente_telefono  = trim($_POST['cliente_telefono'] ?? '');
$cliente_email     = trim($_POST['cliente_email'] ?? '');
$cliente_direccion = trim($_POST['cliente_direccion'] ?? '');
$proyecto          = trim($_POST['proyecto'] ?? '');

$vigencia           = trim($_POST['vigencia'] ?? '15 días');
$metodo_pago        = trim($_POST['metodo_pago'] ?? '');
$tiempo_instalacion = trim($_POST['tiempo_instalacion'] ?? '');
$descripcion        = trim($_POST['descripcion'] ?? '');
$observaciones      = trim($_POST['observaciones'] ?? '');
$condiciones        = trim($_POST['condiciones'] ?? '');

if ($cliente_nombre === '') {
    http_response_code(422);
    exit('Escribe el nombre del cliente antes de generar el documento. Puedes guardar un borrador sin completarlo.');
}

$conceptos  = $_POST['concepto'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];
$precios    = $_POST['precio'] ?? [];

// Validar antes de asignar un folio o guardar el documento.
if (!is_array($conceptos) || !is_array($cantidades) || !is_array($precios)
    || count($conceptos) === 0 || count($conceptos) > 300 || count($conceptos) !== count($cantidades) || count($conceptos) !== count($precios)) {
    http_response_code(422);
    exit('Revisa los conceptos, cantidades y precios. Regresa al formulario para corregirlos.');
}
$conceptos = array_values($conceptos); $cantidades = array_values($cantidades); $precios = array_values($precios);
$porcentajes = $_POST['descuento'] ?? array_fill(0,count($conceptos),'0');
if (!is_array($porcentajes) || count($porcentajes) !== count($conceptos)) {
    http_response_code(422); exit('Revisa los porcentajes de descuento.');
}
$porcentajes = array_values($porcentajes);
foreach ($conceptos as $i => $conceptoEntrada) {
    if (!is_string($conceptoEntrada) || trim($conceptoEntrada) === ''
        || !is_scalar($cantidades[$i] ?? null) || !is_scalar($precios[$i] ?? null)
        || !is_numeric($cantidades[$i]) || !is_numeric($precios[$i])
        || !is_finite((float)$cantidades[$i]) || !is_finite((float)$precios[$i])
        || (float)$cantidades[$i] <= 0 || (float)$precios[$i] < 0
        || abs((float)$cantidades[$i] - round((float)$cantidades[$i], 2)) > 0.0000001
        || abs((float)$precios[$i] - round((float)$precios[$i], 2)) > 0.0000001) {
        http_response_code(422);
        exit('Cada concepto debe tener descripción, cantidad mayor que cero y precio no negativo, con hasta dos decimales. Regresa al formulario para corregirlo.');
    }
}

// Calcular el precio final antes de guardar; los módulos existentes usan este precio.
$preciosOriginales = $precios;
$lineasDescuentos = []; $hayDescuentos = false;
try {
    foreach ($conceptos as $i => $conceptoEntrada) {
        $d = $porcentajes[$i] === '' ? '0' : $porcentajes[$i];
        if (!is_scalar($d) || !is_numeric($d) || !is_finite((float)$d)
            || (float)$d < 0 || (float)$d > 100 || abs((float)$d-round((float)$d,2)) > 0.0000001) throw new InvalidArgumentException('El descuento debe estar entre 0 y 100%, con hasta dos decimales.');
        $porcentajes[$i] = (float)$d;
        $lineasDescuentos[$i] = ojd_linea((float)$cantidades[$i],(float)$preciosOriginales[$i],(float)$d);
        $precios[$i] = $lineasDescuentos[$i]['precio'];
        if ((float)$d > 0) $hayDescuentos = true;
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422); exit(e($e->getMessage()));
}
$rows = ''; $subtotalCentavos = 0; $brutoCentavos = 0;
foreach ($conceptos as $i => $conceptoEntrada) {
    $linea = $lineasDescuentos[$i];
    $subtotalCentavos += (int)round($linea['importe']*100);
    $brutoCentavos += (int)round($linea['bruto']*100);
    if ($brutoCentavos > 9007199254740000 || $subtotalCentavos*16+50 > 9007199254740000) {
        http_response_code(422); exit('El importe total supera el límite de cálculo.');
    }
    $rows .= ojd_pdf_fila(trim($conceptoEntrada),(float)$cantidades[$i],(float)$precios[$i],$linea['importe'],['precio_original' => $preciosOriginales[$i], 'porcentaje' => $porcentajes[$i]],$hayDescuentos);
}
$subtotal = $subtotalCentavos/100;
$ivaCentavos = intdiv($subtotalCentavos*16+50,100);
$iva = $ivaCentavos/100;
$total = ($subtotalCentavos+$ivaCentavos)/100;
$cabeceraProductos = ojd_pdf_cabecera($hayDescuentos);
$resumenDescuentos = ojd_pdf_resumen($brutoCentavos/100,($brutoCentavos-$subtotalCentavos)/100,$hayDescuentos);
if (!$esVistaPrevia && $hayDescuentos) {
    try { ojd_schema($pdo); }
    catch (Throwable $e) { error_log('Descuentos: ' . $e->getMessage()); http_response_code(500); exit('No se pudo preparar el registro de descuentos. Revisa la instalación de su tabla.'); }
}

// La vista previa utiliza los datos capturados, sin reservar folio ni escribir documentos.
$folio = 'VISTA PREVIA';
$fecha = date('d/m/Y');
$planoHtml = '';
$borradorGeneracionId = null;
$estadoPago = 'pendiente';
if (!$esVistaPrevia) {
// Verificar el borrador antes de consumir un folio.
$borradorGeneracionId = null;
if (isset($_POST['borrador_id']) && $_POST['borrador_id'] !== '') {
    require_once __DIR__ . '/app/borradores.php';
    try {
        oj_borradores_schema($pdo);
        $borradorGeneracionId = oj_borrador_id($_POST['borrador_id']);
        $borradorGeneracionVersion = oj_borrador_id($_POST['borrador_version'] ?? null);
        $borradorOrigen = oj_borrador_leer($pdo, $borradorGeneracionId, (int)$_SESSION['usuario_id']);
        if ($borradorOrigen['documento_id'] || (int)$borradorOrigen['version'] !== $borradorGeneracionVersion) {
            throw new RuntimeException('El borrador cambió o ya generó un documento. Abre su estado actual desde Borradores.');
        }
    } catch (Throwable $e) { oj_borrador_error($e, 409); }
}

// FOLIO PROFESIONAL
$folio = generarFolio($pdo, $tipoDocumento);
// generarFolio gestiona su propia transacción. La del borrador empieza después.
if ($borradorGeneracionId) {
    try {
        $pdo->beginTransaction();
        $borradorOrigen = oj_borrador_leer($pdo, $borradorGeneracionId, (int)$_SESSION['usuario_id'], true);
        if ($borradorOrigen['documento_id'] || (int)$borradorOrigen['version'] !== $borradorGeneracionVersion) {
            throw new RuntimeException('El borrador cambió o ya generó un documento. Abre su estado actual desde Borradores.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        oj_borrador_error($e, 409);
    }
}
if ($hayDescuentos && !$pdo->inTransaction()) $pdo->beginTransaction();
$fecha = date('d/m/Y');

// ESTADO INICIAL
$estadoPago = 'pendiente';
if ($tipoDocumento === 'cotizacion') {
    $estadoPago = 'pendiente';
}

// GUARDAR DOCUMENTO EN BD
$stmt = $pdo->prepare("
INSERT INTO cotizaciones (
    folio, fecha,
    empresa_nombre, empresa_telefono, empresa_email, empresa_web, empresa_direccion,
    cliente_nombre, cliente_telefono, cliente_email, cliente_direccion,
    proyecto, vigencia, metodo_pago, tiempo_instalacion,
    descripcion, observaciones, condiciones,
    subtotal, iva, total,
tipo_documento, estado_pago, solicitud_id
) VALUES (
    :folio, NOW(),
    :empresa_nombre, :empresa_telefono, :empresa_email, :empresa_web, :empresa_direccion,
    :cliente_nombre, :cliente_telefono, :cliente_email, :cliente_direccion,
    :proyecto, :vigencia, :metodo_pago, :tiempo_instalacion,
    :descripcion, :observaciones, :condiciones,
    :subtotal, :iva, :total,
:tipo_documento, :estado_pago, :solicitud_id
)");

$stmt->execute([
    ':folio' => $folio,
    ':empresa_nombre' => $empresa['nombre'],
    ':empresa_telefono' => $empresa['telefono'],
    ':empresa_email' => $empresa['email'],
    ':empresa_web' => $empresa['web'],
    ':empresa_direccion' => $empresa['direccion'],
    ':cliente_nombre' => $cliente_nombre,
    ':cliente_telefono' => $cliente_telefono,
    ':cliente_email' => $cliente_email,
    ':cliente_direccion' => $cliente_direccion,
    ':proyecto' => $proyecto,
    ':vigencia' => $vigencia,
    ':metodo_pago' => $metodo_pago,
    ':tiempo_instalacion' => $tiempo_instalacion,
    ':descripcion' => $descripcion,
    ':observaciones' => $observaciones,
    ':condiciones' => $condiciones,
    ':subtotal' => $subtotal,
    ':iva' => $iva,
    ':total' => $total,
    ':tipo_documento' => $tipoDocumento,
    ':estado_pago' => $estadoPago,
    ':solicitud_id' => $solicitud_id
]);

$cotizacion_id = $pdo->lastInsertId();
             $planoRuta = __DIR__ . '/plano/planos/plano_' . $cotizacion_id . '.png';
$planoHtml = '';

if (file_exists($planoRuta)) {
    $planoData = base64_encode(file_get_contents($planoRuta));
    $planoHtml = '
    <div style="page-break-before: always;">
        <div class="section-title">Plano de instalación</div>
        <img src="data:image/png;base64,' . $planoData . '" style="width:100%;">
    </div>';
}

// GUARDAR ITEMS
$stmtItem = $pdo->prepare("
INSERT INTO cotizacion_items (
    cotizacion_id, concepto, cantidad, precio, importe
) VALUES (
    :cotizacion_id, :concepto, :cantidad, :precio, :importe
)");

for ($i = 0; $i < count($conceptos); $i++) {
    $concepto = trim($conceptos[$i] ?? '');
    $cantidad = (float)($cantidades[$i] ?? 0);
    $precio   = (float)($precios[$i] ?? 0);

    if ($concepto === '' || $cantidad <= 0 || $precio < 0) {
        continue;
    }

    $importe = $lineasDescuentos[$i]['importe'];

    $stmtItem->execute([
        ':cotizacion_id' => $cotizacion_id,
        ':concepto' => $concepto,
        ':cantidad' => $cantidad,
        ':precio' => $precio,
        ':importe' => $importe
    ]);
    ojd_guardar($pdo,(int)$pdo->lastInsertId(),(float)$preciosOriginales[$i],(float)$porcentajes[$i]);
}
if ($solicitud_id) {
    $nuevoEstadoSolicitud = 'cotizado';

    if ($tipoDocumento === 'nota_venta' || $tipoDocumento === 'nota_servicio') {
        $nuevoEstadoSolicitud = 'aceptado';
    }

    $stmtSol = $pdo->prepare("
        UPDATE solicitudes_servicio
        SET estado = :estado
        WHERE id = :id
    ");
    $stmtSol->execute([
        ':estado' => $nuevoEstadoSolicitud,
        ':id' => $solicitud_id
    ]);
}


} // Fin del guardado exclusivo de documentos definitivos.

// TÍTULO Y COLOR SEGÚN TIPO
$nombreDocumento = 'Cotización';
$subtituloDocumento = 'Cotización de equipo / instalación';
$color = '#0d3b66';

if ($tipoDocumento === 'nota_venta') {
    $nombreDocumento = 'NOTA DE VENTA';
    $subtituloDocumento = 'Documento de venta';
    $color = '#15803d';
} elseif ($tipoDocumento === 'nota_servicio') {
    $nombreDocumento = 'NOTA DE SERVICIO';
    $subtituloDocumento = 'Documento de servicio';
    $color = '#c2410c';
}

// LOGO
$logoPath = __DIR__ . '/' . ($empresa['logo'] ?? 'assets/logo.png');
$logoHtml = '';

if (file_exists($logoPath)) {
    $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
    $mime = 'image/png';

    if ($extension === 'jpg' || $extension === 'jpeg') {
        $mime = 'image/jpeg';
    } elseif ($extension === 'webp') {
        $mime = 'image/webp';
    }

    $logoData = base64_encode(file_get_contents($logoPath));
    $logoHtml = '<img src="data:' . $mime . ';base64,' . $logoData . '" style="height:52px; margin-bottom:7px;">';
}

$modo_pdf = $_POST['modo_pdf'] ?? 'ver';
$descargar = ($modo_pdf === 'descargar');

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

$avisoVistaPrevia = $esVistaPrevia
    ? '<div style="border:2px solid #b45309;background:#fffbeb;color:#92400e;padding:10px;margin-bottom:12px;text-align:center;font-weight:bold;">VISTA PREVIA - SIN GUARDAR<br>Sin folio definitivo. No registra un documento ni un pago.</div>'
    : '';

$descripcionHtml = $descripcion !== ''
    ? '<div class="section-title">Descripción general</div><div class="info-box">' . nl2br(e($descripcion)) . '</div>'
    : '';

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  @page {
    margin: 28px 28px 34px 28px;
  }

  body{
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #1f2937;
    margin: 0;
  }

  .header{
    width: 100%;
    border-bottom: 3px solid ' . $color . ';
    padding-bottom: 10px;
    margin-bottom: 12px;
  }

  .header-table{
    width: 100%;
    border-collapse: collapse;
  }

  .header-table td{
    border: none;
    vertical-align: top;
  }

  .brand{
    font-size: 14px;
    font-weight: bold;
    color: ' . $color . ';
    margin-bottom: 4px;
  }

  .docname{
    font-size: 18px;
    font-weight: bold;
    color: ' . $color . ';
    margin-top: 10px;
    line-height: 1.1;
  }

  .subtitle{
    font-size: 13px;
    color: #4b5563;
    margin-top: 4px;
  }

  .folio-box{
    text-align: right;
    font-size: 11px;
    vertical-align: top;
    padding-top: 10px;
  }

  .folio-number{
    background: ' . $color . ';
    color: #fff;
    display: inline-block;
    padding: 8px 12px;
    border-radius: 6px;
    font-weight: bold;
    margin-bottom: 8px;
  }

  .section-title{
    font-size: 13px;
    font-weight: bold;
    color: ' . $color . ';
    margin: 12px 0 6px;
    padding-bottom: 4px;
    border-bottom: 1px solid #dbe3ec;
  }

  .info-box{
    background: #f8fafc;
    border: 1px solid #d6dee8;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 8px;
  }

  .info-table{
    width: 100%;
    border-collapse: collapse;
  }

  .info-table td{
    border: none;
    padding: 4px 6px 4px 0;
    vertical-align: top;
  }

  .label{
    font-weight: bold;
    color: #111827;
    width: 130px;
  }

  table.products{
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }

  .products th{
    background: ' . $color . ';
    color: #fff;
    font-size: 11px;
    padding: 10px 8px;
    text-align: left;
  }

  .products td{
    border: 1px solid #d9e2ec;
    padding: 8px;
    font-size: 11px;
  }

  .center{
    text-align: center;
  }

  .right{
    text-align: right;
  }

  .totals{
    width: 280px;
    margin-left: auto;
    margin-top: 12px;
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
    page-break-inside: avoid;
  }

  .totals td{
    border: 1px solid #d6dee8;
    padding: 9px 10px;
    font-size: 11px;
  }

  .totals .grand td{
    background: ' . $color . ';
    color: #fff;
    font-weight: bold;
    font-size: 13px;
  }

  .two-col{
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
  }

  .two-col .section-title{
    margin-top: 0;
  }

  .two-col td{
    width: 50%;
    vertical-align: top;
    border: none;
    padding-right: 10px;
  }

  .mini-box{
    background: #fafbfd;
    border: 1px solid #dbe3ec;
    border-radius: 8px;
    padding: 10px;
    min-height: 60px;
  }

  .footer{
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px solid #dbe3ec;
    font-size: 10px;
    color: #4b5563;
    page-break-inside: avoid;
  }
</style>
</head>
<body>
' . $avisoVistaPrevia . '

<div class="header">
  <table class="header-table">
    <tr>
      <td style="width:60%;">
        ' . $logoHtml . '
        <div class="brand">' . e($empresa['nombre']) . '</div>
        <div class="docname">' . e($nombreDocumento) . '</div>
        <div class="subtitle">' . e($subtituloDocumento) . '</div>
        <div style="margin-top:8px;">
          ' . e($empresa['direccion']) . '<br>
          ' . e($empresa['telefono']) . ' | ' . e($empresa['email']) . '<br>
          ' . e($empresa['web']) . '
        </div>
      </td>
      <td style="width:40%;" class="folio-box">
        <div class="folio-number">Folio: ' . e($folio) . '</div><br>
        <strong>Fecha:</strong> ' . e($fecha) . '<br>
        <strong>Vigencia:</strong> ' . e($vigencia) . '<br>
        <strong>Pago:</strong> ' . e($metodo_pago) . '<br>
        <strong>Estado:</strong> ' . ($esVistaPrevia ? 'SIN GUARDAR' : strtoupper(e($estadoPago))) . '
      </td>
    </tr>
  </table>
</div>

<div class="section-title">Datos del cliente</div>
<div class="info-box">
  <table class="info-table">
    <tr>
      <td class="label">Cliente:</td>
      <td>' . e($cliente_nombre) . '</td>
      <td class="label">Proyecto:</td>
      <td>' . e($proyecto) . '</td>
    </tr>
    <tr>
      <td class="label">Teléfono:</td>
      <td>' . e($cliente_telefono) . '</td>
      <td class="label">Correo:</td>
      <td>' . e($cliente_email) . '</td>
    </tr>
    <tr>
      <td class="label">Ubicación:</td>
      <td colspan="3">' . nl2br(e($cliente_direccion)) . '</td>
    </tr>
  </table>
</div>

' . $descripcionHtml . '

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
    <td class="right">' . money($subtotal) . '</td>
  </tr>
  <tr>
    <td><strong>IVA (16%)</strong></td>
    <td class="right">' . money($iva) . '</td>
  </tr>
  <tr class="grand">
    <td><strong>Total</strong></td>
    <td class="right">' . money($total) . '</td>
  </tr>
</table>

<table class="two-col">
  <tr>
    <td>
      <div class="section-title">Instalación y observaciones</div>
      <div class="mini-box">
        <strong>Tiempo estimado:</strong> ' . e($tiempo_instalacion) . '<br><br>
        ' . nl2br(e($observaciones)) . '
      </div>
    </td>
    <td>
      <div class="section-title">Condiciones</div>
      <div class="mini-box">
        ' . nl2br(e($condiciones)) . '
      </div>
    </td>
  </tr>
</table>
              ' . $planoHtml . '
<div class="footer">
  Gracias por considerar a <strong>' . e($empresa['nombre']) . '</strong>.<br>
  Para cualquier aclaración o confirmación, contáctanos al ' . e($empresa['telefono']) . ' o por correo a ' . e($empresa['email']) . '.
</div>

</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

if ($esVistaPrevia) {
    header('Cache-Control: no-store');
    $dompdf->stream('VISTA_PREVIA_Ojo_Jarocho.pdf', ['Attachment' => false]);
    exit;
}

$pdfOutput = $dompdf->output();

// NOMBRE DE ARCHIVO SEGURO
$nombreArchivo = preg_replace('/[^A-Za-z0-9\\-_]/', '_', $folio) . '.pdf';
$carpetaPdfs = __DIR__ . '/pdfs';

if (!is_dir($carpetaPdfs)) {
    if (!mkdir($carpetaPdfs, 0775, true) && !is_dir($carpetaPdfs)) {
        die('No se pudo crear la carpeta: ' . $carpetaPdfs);
    }
}

$rutaArchivo = $carpetaPdfs . '/' . $nombreArchivo;
$resultadoGuardado = file_put_contents($rutaArchivo, $pdfOutput);

if ($resultadoGuardado === false) {
    die('No se pudo guardar el PDF en: ' . $rutaArchivo);
}

// Completar solo después de generar y guardar el PDF correctamente.
if ($borradorGeneracionId) {
    try {
        $stmtBorradorCompletado = $pdo->prepare('UPDATE cotizador_borradores SET documento_id = ?, version = version + 1, actualizado_en = NOW() WHERE id = ? AND usuario_id = ? AND version = ? AND documento_id IS NULL');
        $stmtBorradorCompletado->execute([(int)$cotizacion_id, $borradorGeneracionId, (int)$_SESSION['usuario_id'], $borradorGeneracionVersion]);
        if ($stmtBorradorCompletado->rowCount() !== 1) throw new RuntimeException('No se pudo completar el borrador.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // El archivo de esta operación no debe sobrevivir sin su documento.
        if (is_file($rutaArchivo)) unlink($rutaArchivo);
        oj_borrador_error($e, 500);
    }
}

// En documentos con descuento sin borrador, confirmar datos y metadatos juntos.
if (!$borradorGeneracionId && $hayDescuentos && $pdo->inTransaction()) $pdo->commit();

// Si el usuario pidió diseñar plano, redirige al plano
if (isset($_POST['accion']) && $_POST['accion'] === 'plano') {
    header("Location: plano/?id=" . $cotizacion_id);
    exit;
}

// Si no, muestra o descarga el PDF normal
$dompdf->stream($nombreArchivo, ['Attachment' => $descargar]);
