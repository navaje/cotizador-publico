<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso directo bloqueado');
}

require_post_csrf();

if (!empty($_POST['website'])) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

if (($_POST['acepta_privacidad'] ?? '') !== '1') {
    http_response_code(400);
    exit('Debes aceptar el Aviso de privacidad para enviar la solicitud.');
}

if (!empty($_SESSION['ultima_solicitud']) && time() - (int)$_SESSION['ultima_solicitud'] < 60) {
    http_response_code(429);
    exit('Espera un minuto antes de enviar otra solicitud.');
}

function limpiar($v) {
    return trim((string)($v ?? ''));
}

$cliente_nombre   = limpiar($_POST['cliente_nombre'] ?? '');
$cliente_telefono = limpiar($_POST['cliente_telefono'] ?? '');
$cliente_email    = limpiar($_POST['cliente_email'] ?? '');
$direccion        = limpiar($_POST['direccion'] ?? '');
$persona_atiende  = limpiar($_POST['persona_atiende'] ?? '');

$tipo_equipo      = limpiar($_POST['tipo_equipo'] ?? '');
$marca            = limpiar($_POST['marca'] ?? '');
$modelo           = limpiar($_POST['modelo'] ?? '');
$numero_serie     = limpiar($_POST['numero_serie'] ?? '');
$tipo_servicio    = limpiar($_POST['tipo_servicio'] ?? 'revision');
$falla_reportada  = limpiar($_POST['falla_reportada'] ?? '');

$fecha_preferida  = limpiar($_POST['fecha_preferida'] ?? '');
$horario_preferido = limpiar($_POST['horario_preferido'] ?? '');
$observaciones    = limpiar($_POST['observaciones'] ?? '');
$origen           = strtolower(limpiar($_POST['origen'] ?? 'web'));
$producto_nombre  = substr(limpiar($_POST['producto_nombre'] ?? ''), 0, 255);
$producto_sku     = substr(limpiar($_POST['producto_sku'] ?? ''), 0, 100);
$producto_url     = substr(limpiar($_POST['producto_url'] ?? ''), 0, 500);

$origenesValidos = ['web','catalogo','facebook','whatsapp','recomendacion','otro'];
if (!in_array($origen, $origenesValidos, true)) $origen = 'web';
if (strpos($producto_url, 'https://www.productos-info.com/tienda/ojojarocho') !== 0) $producto_url = '';

if ($cliente_nombre === '' || $cliente_telefono === '' || $direccion === '' || $falla_reportada === '') {
    die('Faltan datos obligatorios.');
}

$tiposValidos = ['mantenimiento','revision','reparacion','instalacion','otro'];
if (!in_array($tipo_servicio, $tiposValidos, true)) {
    $tipo_servicio = 'revision';
}

$folio = 'SERV-' . date('Ymd-His');

$fechaSQL = null;
if ($fecha_preferida !== '') {
    $fechaSQL = $fecha_preferida;
}

$stmt = $pdo->prepare("
INSERT INTO solicitudes_servicio (
    folio,
    cliente_nombre,
    cliente_telefono,
    cliente_email,
    direccion,
    persona_atiende,
    tipo_equipo,
    marca,
    modelo,
    numero_serie,
    tipo_servicio,
    falla_reportada,
    fecha_preferida,
    horario_preferido,
    observaciones,
    estado,
    origen,
    producto_nombre,
    producto_sku,
    producto_url
) VALUES (
    :folio,
    :cliente_nombre,
    :cliente_telefono,
    :cliente_email,
    :direccion,
    :persona_atiende,
    :tipo_equipo,
    :marca,
    :modelo,
    :numero_serie,
    :tipo_servicio,
    :falla_reportada,
    :fecha_preferida,
    :horario_preferido,
    :observaciones,
    'nuevo',
    :origen,
    :producto_nombre,
    :producto_sku,
    :producto_url
)
");

$stmt->execute([
    ':folio' => $folio,
    ':cliente_nombre' => $cliente_nombre,
    ':cliente_telefono' => $cliente_telefono,
    ':cliente_email' => $cliente_email,
    ':direccion' => $direccion,
    ':persona_atiende' => $persona_atiende,
    ':tipo_equipo' => $tipo_equipo,
    ':marca' => $marca,
    ':modelo' => $modelo,
    ':numero_serie' => $numero_serie,
    ':tipo_servicio' => $tipo_servicio,
    ':falla_reportada' => $falla_reportada,
    ':fecha_preferida' => $fechaSQL,
    ':horario_preferido' => $horario_preferido,
    ':observaciones' => $observaciones,
    ':origen' => $origen,
    ':producto_nombre' => $producto_nombre !== '' ? $producto_nombre : null,
    ':producto_sku' => $producto_sku !== '' ? $producto_sku : null,
    ':producto_url' => $producto_url !== '' ? $producto_url : null
]);
$_SESSION['ultima_solicitud'] = time();

// Notificación interna. Usa WordPress/FluentSMTP cuando está disponible.
$asuntoCorreo = 'Nueva solicitud de servicio: ' . $folio;
$cuerpoCorreo = "Se registró una nueva solicitud en Ojo Jarocho.\n\n" .
    "Folio: " . $folio . "\n" .
    "Fecha: " . date('Y-m-d H:i:s') . "\n" .
    "Origen: " . ucfirst($origen) . "\n\n" .
    "Cliente: " . $cliente_nombre . "\n" .
    "Teléfono: " . $cliente_telefono . "\n" .
    "Correo: " . ($cliente_email !== '' ? $cliente_email : 'No proporcionado') . "\n" .
    "Dirección: " . $direccion . "\n\n" .
    "Servicio: " . ucfirst($tipo_servicio) . "\n" .
    "Equipo: " . $tipo_equipo . "\n" .
    "Marca: " . $marca . "\n" .
    "Modelo: " . $modelo . "\n" .
    "Falla o necesidad: " . $falla_reportada . "\n" .
    ($producto_nombre !== '' ? "Producto: " . $producto_nombre . "\n" : '') .
    ($producto_url !== '' ? "Enlace: " . $producto_url . "\n" : '') .
    "\nConsulta la solicitud en:\nhttps://ojojarocho.online/cotizador/solicitudes.php";

$wpLoad = dirname(__DIR__) . '/wp-load.php';
if (is_file($wpLoad)) {
    require_once $wpLoad;
}
$cabecerasCorreo = [];
if ($cliente_email !== '' && filter_var($cliente_email, FILTER_VALIDATE_EMAIL)) {
    $cabecerasCorreo[] = 'Reply-To: ' . $cliente_nombre . ' <' . $cliente_email . '>';
}
if (function_exists('wp_mail')) {
    if (!wp_mail('contacto@ojojarocho.online', $asuntoCorreo, $cuerpoCorreo, $cabecerasCorreo)) {
        error_log('No se pudo enviar la notificación de la solicitud ' . $folio . ' mediante wp_mail.');
    }
} else {
    $cabeceras = "From: Ojo Jarocho <contacto@ojojarocho.online>\r\n";
    if ($cabecerasCorreo) $cabeceras .= implode("\r\n", $cabecerasCorreo) . "\r\n";
    if (!@mail('contacto@ojojarocho.online', $asuntoCorreo, $cuerpoCorreo, $cabeceras)) {
        error_log('No se pudo enviar la notificación de la solicitud ' . $folio . ' mediante mail.');
    }
}

$mensajeWhatsApp = "Hola Ojo Jarocho, acabo de enviar una solicitud de servicio.\n\n" .
    "Folio: " . $folio . "\n" .
    "Nombre: " . $cliente_nombre . "\n" .
    "Servicio: " . ucfirst($tipo_servicio) . "\n" .
    ($modelo !== '' ? "Modelo: " . $modelo . "\n" : '') .
    ($producto_nombre !== '' ? "Producto: " . $producto_nombre . "\n" : '') .
    ($producto_url !== '' ? "Enlace: " . $producto_url . "\n" : '') .
    "\nDeseo confirmar la recepción y recibir información sobre el servicio.";
$whatsappUrl = 'https://wa.me/5210000000000?text=' . urlencode($mensajeWhatsApp);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitud enviada</title>
<style>
body{
  margin:0;
  font-family:Arial, Helvetica, sans-serif;
  background:#eef3f8;
  color:#1f2937;
  padding:24px;
}
.box{
  max-width:700px;
  margin:60px auto;
  background:#fff;
  border-radius:14px;
  box-shadow:0 10px 30px rgba(0,0,0,.08);
  padding:28px;
  text-align:center;
}
h1{
  color:#0d3b66;
}
.folio{
  display:inline-block;
  background:#0d3b66;
  color:#fff;
  padding:10px 14px;
  border-radius:8px;
  font-weight:bold;
  margin:12px 0;
}
p{
  line-height:1.5;
}
.actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:22px;}
.btn{display:inline-block;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold;}
.btn-wa{background:#25D366;color:#073b1c;}
.btn-catalogo{background:#dbe7f3;color:#0d3b66;}
</style>
</head>
<body>
<div class="box">
  <h1>Solicitud enviada correctamente</h1>
  <p>Gracias. Hemos recibido tu solicitud de servicio / mantenimiento.</p>
  <div class="folio"><?php echo htmlspecialchars($folio); ?></div>
  <p>Nos pondremos en contacto contigo para confirmar la visita.</p>
  <p>Tu solicitud ya quedó registrada. El siguiente botón es opcional y permite avisarnos inmediatamente por WhatsApp.</p>
  <div class="actions">
    <a class="btn btn-wa" href="<?php echo htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Avisar a Ojo Jarocho por WhatsApp</a>
    <a class="btn btn-catalogo" href="https://www.productos-info.com/tienda/ojojarocho">Regresar al catálogo</a>
  </div>
</div>
</body>
</html>
