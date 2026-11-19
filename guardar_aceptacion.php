<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso directo bloqueado');
}
require_post_csrf();

function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

$cotizacion_id = (int)($_POST['cotizacion_id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$puesto = trim($_POST['puesto'] ?? '');
$observacion = trim($_POST['observacion'] ?? '');
$firma = $_POST['firma'] ?? '';

if (strlen($firma) > 2 * 1024 * 1024 || strpos($firma, 'data:image/png;base64,') !== 0) {
    die('La firma no tiene un formato válido.');
}

if ($cotizacion_id <= 0 || $nombre === '' || $firma === '') {
    die('Faltan datos obligatorios.');
}

// Documento relacionado
$stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ? LIMIT 1");
$stmt->execute([$cotizacion_id]);
$data = $stmt->fetch();

if (!$data) {
    die('Documento no encontrado.');
}

// Empresa
$stmtEmpresa = $pdo->query("SELECT * FROM empresa LIMIT 1");
$empresa = $stmtEmpresa->fetch();

if (!$empresa) {
    die('No hay datos de empresa.');
}

// Técnico
$tecnico = $_SESSION['usuario_nombre'] ?? 'No asignado';

// Guardar aceptación
$stmtInsert = $pdo->prepare("
    INSERT INTO aceptaciones (cotizacion_id, cliente_nombre, puesto, firma, fecha)
    VALUES (?, ?, ?, ?, NOW())
");
$stmtInsert->execute([$cotizacion_id, $nombre, $puesto, $firma]);

// Logo
$logoHtml = '';
$logoPath = __DIR__ . '/' . ($empresa['logo'] ?? 'assets/logo.png');

if (file_exists($logoPath)) {
    $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
    $mime = 'image/png';

    if ($extension === 'jpg' || $extension === 'jpeg') {
        $mime = 'image/jpeg';
    } elseif ($extension === 'webp') {
        $mime = 'image/webp';
    }

    $logoData = base64_encode(file_get_contents($logoPath));
    $logoHtml = '<img src="data:' . $mime . ';base64,' . $logoData . '" style="height:70px; margin-bottom:10px;">';
}

// DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

// HTML
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 28px; }

body { font-family: DejaVu Sans; font-size: 11px; color:#1f2937; }

.sello {
  position:absolute;
  top:40%;
  right:10%;
  font-size:70px;
  color:rgba(0,128,0,0.15);
  transform:rotate(-20deg);
  font-weight:bold;
}

.header { border-bottom:3px solid #0d3b66; margin-bottom:18px; padding-bottom:10px; }

.brand { font-size:14px; font-weight:bold; color:#0d3b66; }

.docname { font-size:18px; font-weight:bold; color:#0d3b66; margin-top:6px; }

.box {
  background:#f8fafc;
  border:1px solid #d6dee8;
  border-radius:8px;
  padding:12px;
  margin-bottom:12px;
}

.label { font-weight:bold; width:140px; }

.firma { text-align:center; margin-top:20px; }

.firma img { width:220px; }

.linea { border-top:1px solid #999; width:240px; margin:8px auto; }

.footer {
  margin-top:20px;
  border-top:1px solid #ddd;
  padding-top:10px;
  font-size:10px;
}
</style>
</head>

<body>

<div class="sello">ACEPTADO</div>

<div class="header">
'.$logoHtml.'
<div class="brand">'.e($empresa['nombre']).'</div>
<div class="docname">ACTA DE ACEPTACIÓN</div>

'.e($empresa['direccion']).'<br>
'.e($empresa['telefono']).' | '.e($empresa['email']).'<br>
'.e($empresa['web']).'
</div>

<div class="box">
<strong>Folio:</strong> '.e($data['folio']).'<br>
<strong>Fecha:</strong> '.date('d/m/Y H:i').'<br>
<strong>Total:</strong> $'.number_format((float)$data['total'],2,'.',',').'
</div>

<div class="box">
<strong>Cliente:</strong> '.e($data['cliente_nombre']).'<br>
<strong>Proyecto:</strong> '.e($data['proyecto']).'<br>
<strong>Dirección:</strong> '.e($data['cliente_direccion']).'<br>
<strong>Teléfono:</strong> '.e($data['cliente_telefono']).'<br>
<strong>Correo:</strong> '.e($data['cliente_email']).'<br>
<strong>Atendió:</strong> '.e($tecnico).'
</div>

<div class="box">
  Por medio del presente se hace constar que el cliente recibe y acepta de conformidad el trabajo, servicio, instalación o entrega realizados, relacionados con el documento mencionado anteriormente. Se confirma que los trabajos fueron revisados visualmente y recibidos en condiciones satisfactorias, salvo las observaciones asentadas en el presente documento.

</div>

<div class="box">
<strong>Observaciones:</strong><br>
'.nl2br(e($observacion ?: 'Sin observaciones.')).'
</div>

<div class="firma">
<img src="'.$firma.'">
<div class="linea"></div>
<strong>'.e($nombre).'</strong><br>
'.e($puesto ?: 'Firma de aceptación').'
</div>

<div class="footer">
Documento de aceptación emitido por '.e($empresa['nombre']).'.
</div>

</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfOutput = $dompdf->output();

// GUARDAR PDF
$nombreArchivo = 'ACEPTACION-' . preg_replace('/[^A-Za-z0-9\\-_]/', '_', $data['folio']) . '.pdf';
$carpetaPdfs = __DIR__ . '/pdfs';

if (!is_dir($carpetaPdfs)) {
    mkdir($carpetaPdfs, 0775, true);
}

$rutaArchivo = $carpetaPdfs . '/' . $nombreArchivo;

file_put_contents($rutaArchivo, $pdfOutput);

// 🔥 REDIRECCIÓN CORRECTA (SOLUCIÓN AL ERROR)
$nombreUrl = rawurlencode($nombreArchivo);
header('Location: pdfs/' . $nombreUrl);
exit;
