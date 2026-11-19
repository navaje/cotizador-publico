<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/descuentos.php';
require_once __DIR__ . '/app/empresa.php';

$empresa = oj_emp_leer($pdo);
$bancoTexto = oj_emp_datos_banco($empresa);

$solicitud = null;
$copia = null;
$itemsCopia = [];
$borrador = null;
$clienteFormulario = [];
// Preparar una copia solo lee el original. Guardar utiliza el generador normal
// y crea un documento independiente, con folio nuevo y estado pendiente.
if (isset($_GET['cliente_id'])) {
    require_once __DIR__ . '/app/clientes.php';
    try {
        if (isset($_GET['borrador_id']) || isset($_GET['duplicar_id']) || isset($_GET['solicitud_id'])) throw new InvalidArgumentException('Abre el cliente desde una nueva cotización.');
        oj_clientes_schema($pdo);
        $stmtCliente = $pdo->prepare('SELECT nombre, telefono, email, direccion FROM cotizador_clientes WHERE id = ? AND usuario_id = ? AND activo = 1');
        $stmtCliente->execute([oj_clientes_id($_GET['cliente_id']), (int)$_SESSION['usuario_id']]);
        $clienteGuardado = $stmtCliente->fetch(PDO::FETCH_ASSOC);
        if (!$clienteGuardado) throw new RuntimeException('No se encontró un cliente activo para tu usuario.');
        foreach (['nombre','telefono','email','direccion'] as $campo) $clienteFormulario['cliente_' . $campo] = $clienteGuardado[$campo];
    } catch (Throwable $e) {
        http_response_code(400);
        if ($e instanceof PDOException) { error_log('Clientes: ' . $e->getMessage()); $mensajeCliente = 'No se pudo cargar el directorio. Revisa su instalación.'; }
        else $mensajeCliente = $e->getMessage();
        exit(htmlspecialchars($mensajeCliente, ENT_QUOTES, 'UTF-8') . ' <a href="clientes.php">Volver a Mis clientes</a>');
    }
} elseif (isset($_GET['borrador_id'])) {
    require_once __DIR__ . '/app/borradores.php';
    try {
        oj_borradores_schema($pdo);
        $idBorrador = oj_borrador_id($_GET['borrador_id']);
        if (!$idBorrador) throw new InvalidArgumentException('Identificador de borrador no válido.');
        $borrador = oj_borrador_leer($pdo, $idBorrador, (int)$_SESSION['usuario_id']);
        if ($borrador['documento_id']) throw new RuntimeException('Este borrador ya generó un documento. Puedes abrir su PDF o preparar otra copia desde Borradores.');
        $copia = json_decode($borrador['datos'], true, 512, JSON_THROW_ON_ERROR);
        $itemsCopia = $copia['items'] ?? [];
        $idSolicitudBorrador = oj_borrador_id($copia['solicitud_id'] ?? null);
        if ($idSolicitudBorrador) $solicitud = ['id' => $idSolicitudBorrador];
    } catch (Throwable $e) { oj_borrador_error($e); }
} elseif (isset($_GET['duplicar_id'])) {
    $idCopia = filter_var($_GET['duplicar_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idCopia === false) {
        http_response_code(400);
        exit('El documento de origen no es válido.');
    }
    $stmtCopia = $pdo->prepare('SELECT * FROM cotizaciones WHERE id = ? LIMIT 1');
    $stmtCopia->execute([$idCopia]);
    $copia = $stmtCopia->fetch(PDO::FETCH_ASSOC);
    if (!$copia) {
        http_response_code(404);
        exit('No se encontró el documento de origen.');
    }
    $stmtItemsCopia = $pdo->prepare('SELECT id, concepto, cantidad, precio FROM cotizacion_items WHERE cotizacion_id = ? ORDER BY id ASC');
    $stmtItemsCopia->execute([$idCopia]);
    $itemsCopia = $stmtItemsCopia->fetchAll(PDO::FETCH_ASSOC);
    $metasCopia = ojd_cargar($pdo, $idCopia);
    foreach ($itemsCopia as &$itemCopia) {
        $meta = $metasCopia[(int)$itemCopia['id']] ?? null;
        $itemCopia['precio'] = $meta['precio_original'] ?? $itemCopia['precio'];
        $itemCopia['descuento'] = $meta['porcentaje'] ?? 0;
    }
    unset($itemCopia);
} elseif (isset($_GET['solicitud_id']) && is_numeric($_GET['solicitud_id'])) {
    $stmtSolicitud = $pdo->prepare("SELECT * FROM solicitudes_servicio WHERE id = ? LIMIT 1");
    $stmtSolicitud->execute([(int)$_GET['solicitud_id']]);
    $solicitud = $stmtSolicitud->fetch();
}
$datosFormulario = $clienteFormulario ?: ($copia ?: ($solicitud ?: []));

$logoHtml = '';
$logoPath = __DIR__ . '/' . ($empresa['logo'] ?? 'assets/logo.png');

if (file_exists($logoPath)) {
    $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
    $mime = 'image/png';

    if ($extension === 'jpg' || $extension === 'jpeg') $mime = 'image/jpeg';
    if ($extension === 'webp') $mime = 'image/webp';

    $logoHtml = '<img src="data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath)) . '" style="height:60px; display:block; margin-bottom:8px;">';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cotizador Ojo Jarocho</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#eef3f8;color:#1f2937;padding:24px}
.wrap{max-width:1100px;margin:auto;background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden}
.topbar{background:#0d3b66;color:#fff;padding:22px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.topbar h1{margin:0}
.top-link{background:#dbe7f3;color:#0d3b66;text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:bold}
.content{padding:24px}
.empresa-header{border-bottom:3px solid #0d3b66;padding-bottom:15px;margin-bottom:20px;display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap}
.empresa-nombre{font-size:22px;font-weight:bold;color:#0d3b66}
.empresa-info{font-size:13px;line-height:1.5}
.doc-title{font-size:24px;font-weight:bold;color:#0d3b66}
.section{margin-bottom:24px;border:1px solid #e5e7eb;border-radius:12px;padding:20px;background:#fafcff}
.section h2{margin:0 0 16px;color:#0d3b66}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.full{grid-column:1/-1}
label{display:block;font-weight:bold;margin-bottom:6px;font-size:14px}
input,textarea{width:100%;padding:10px;border:1px solid #cfd8e3;border-radius:8px;font-size:14px;background:#fff}
textarea{min-height:85px;resize:vertical}
table{width:100%;border-collapse:collapse;margin-top:10px;background:#fff}
th{background:#0d3b66;color:#fff;padding:10px;text-align:left;font-size:13px}
td{border:1px solid #ddd;padding:8px;vertical-align:middle}
.actions{margin-top:12px;display:flex;gap:10px;flex-wrap:wrap}
.btn{padding:10px 14px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;font-size:14px}
.btn-primary{background:#0d3b66;color:#fff}
.btn-secondary{background:#dbe7f3;color:#0d3b66}
.btn-danger{background:#fee2e2;color:#b91c1c}
.btn-green{background:#dcfce7;color:#166534}
.btn-orange{background:#ffedd5;color:#c2410c}
.totals{text-align:right;font-size:20px;font-weight:bold;color:#0d3b66;margin-top:18px}
@media(max-width:900px){.grid,.grid-3{grid-template-columns:1fr}body{padding:12px}.content{padding:16px}table{display:block;overflow:auto}}
.descuento{min-width:85px}.precio-final,.importe{white-space:nowrap}
</style>
<link rel="stylesheet" href="assets/cotizador_mejoras.css?v=20261016-5">
<link rel="stylesheet" href="assets/catalogo.css?v=20261016-5">
<link rel="stylesheet" href="assets/cotizador_movil.css?v=20261016-59">
<link rel="stylesheet" href="assets/syscom.css?v=20261018-512">
</head>
<body>

<div class="wrap">
  <div class="topbar">
    <h1>Cotizador Ojo Jarocho</h1>
    <div>
      <a class="top-link" href="dashboard.php">Dashboard</a>
      <a class="top-link" href="visitas.php">Visitas técnicas</a>
      <a class="top-link" href="listado.php">Listado</a>
      <a class="top-link" href="solicitudes.php">Solicitudes</a>
      <a class="top-link" href="borradores.php">Borradores</a>
      <a class="top-link" href="catalogo.php" target="_blank" rel="noopener">Mis conceptos</a>
      <a class="top-link" href="clientes.php" target="_blank" rel="noopener">Mis clientes</a>
      <a class="top-link" href="logout.php">Salir</a>
    </div>
  </div>

  <div class="content">
    <div class="empresa-header">
      <div>
        <?php echo $logoHtml; ?>
        <div class="empresa-nombre"><?php echo htmlspecialchars($empresa['nombre'] ?? 'Ojo Jarocho'); ?></div>
        <div class="empresa-info">
          <?php echo htmlspecialchars($empresa['direccion'] ?? ''); ?><br>
          <?php echo htmlspecialchars($empresa['telefono'] ?? ''); ?> | <?php echo htmlspecialchars($empresa['email'] ?? ''); ?><br>
          <?php echo htmlspecialchars($empresa['web'] ?? ''); ?>
        </div>
      </div>
      <div class="doc-title">Generador de documentos</div>
    </div>

    <?php if ($copia && !$borrador): ?>
    <div class="section" role="status" style="background:#fff8e6;border-color:#eab308">
      <h2>Preparar copia de <?php echo htmlspecialchars((string)$copia['folio'], ENT_QUOTES, 'UTF-8'); ?></h2>
      <p>Revisa cliente, precios, vigencia y condiciones. Al generar el documento se asignará un folio nuevo con fecha actual y estado pendiente.</p>
      <p class="ayuda">Se copian los datos y conceptos. Pagos, facturas, firmas, planos, inspecciones y vínculo con la solicitud pertenecen al documento original y no se trasladan.</p>
      <a class="top-link" href="index.php">Empezar cotización vacía</a>
    </div>
    <?php endif; ?>
    <?php if ($borrador): ?>
    <div class="section" role="status" style="background:#f0fdf4;border-color:#82B60B">
      <h2>Borrador #<?php echo (int)$borrador['id']; ?></h2>
      <p><?php echo isset($_GET['guardado']) ? 'Borrador guardado correctamente.' : 'Continuando tu propuesta guardada.'; ?> Último guardado: <?php echo htmlspecialchars($borrador['actualizado_en'], ENT_QUOTES, 'UTF-8'); ?>.</p>
      <p class="ayuda">Guarda de nuevo para conservar los cambios. Al generar un documento, el borrador quedará marcado como completado y podrás consultar su PDF en Borradores.</p>
    </div>
    <?php endif; ?>
    <?php if ($borrador) { require_once __DIR__ . '/app/visitas_origen.php'; echo ojv_origin($pdo, (int)$borrador['id'], (int)$_SESSION['usuario_id']); } ?>
    <form method="POST" action="generar-pdf.php" target="_blank" id="cotizadorForm" data-propuesta-sin-guardar="<?php echo (!$borrador && ($copia || $solicitud || $datosFormulario)) ? '1' : '0'; ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="borrador_id" value="<?php echo $borrador ? (int)$borrador['id'] : ''; ?>">
      <input type="hidden" name="borrador_version" value="<?php echo $borrador ? (int)$borrador['version'] : ''; ?>">
      <div class="actions" style="margin-bottom:20px">
        <button class="btn btn-primary" type="submit" formaction="guardar_borrador.php" formtarget="_self" formnovalidate>Guardar borrador</button>
        <a class="top-link" href="borradores.php">Mis borradores</a>
        <span class="ayuda">Puedes guardar aunque falten datos. El guardado es manual; no genera PDF.</span>
      </div>
      <p id="propuestaGuardadoEstado" role="status" aria-live="polite" class="ayuda"></p>
      <input type="hidden" name="modo_pdf" id="modo_pdf" value="ver">
      <input type="hidden" name="tipo_documento" id="tipo_documento" value="cotizacion">
      <input type="hidden" name="solicitud_id" value="<?php echo htmlspecialchars($solicitud['id'] ?? ''); ?>">

      <div class="section">
        <h2>Datos del cliente</h2>
        <div class="grid">
          <div><label for="clientesQuery">Buscar cliente guardado</label><input type="search" id="clientesQuery" maxlength="100" placeholder="Nombre, teléfono o correo"></div>
          <div class="actions"><button type="button" id="clientesBuscar" class="btn btn-secondary">Buscar clientes</button><a class="top-link" href="clientes.php" target="_blank" rel="noopener">Administrar mis clientes</a></div>
        </div>
        <p class="ayuda" id="clientesEstado" role="status">Selecciona un cliente para completar los datos o captúralos manualmente.</p>
        <div id="clientesResultados"></div>
        <p class="ayuda">Los ajustes de esta propuesta no cambian la ficha del cliente guardado.</p>
        <div class="grid">
          <div>
            <label>Nombre</label>
            <input type="text" name="cliente_nombre" required value="<?php echo htmlspecialchars($datosFormulario['cliente_nombre'] ?? ''); ?>">
          </div>
          <div>
            <label>Teléfono</label>
            <input type="text" name="cliente_telefono" value="<?php echo htmlspecialchars($datosFormulario['cliente_telefono'] ?? ''); ?>">
          </div>
          <div>
            <label>Correo</label>
            <input type="email" name="cliente_email" value="<?php echo htmlspecialchars($datosFormulario['cliente_email'] ?? ''); ?>">
          </div>
          <div>
            <label>Proyecto</label>
            <input type="text" name="proyecto" value="<?php echo htmlspecialchars($copia['proyecto'] ?? ($solicitud['tipo_servicio'] ?? '')); ?>">
          </div>
          <div class="full">
            <label>Dirección / ubicación</label>
            <textarea name="cliente_direccion"><?php echo htmlspecialchars((string)($datosFormulario['cliente_direccion'] ?? ($solicitud['direccion'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
        </div>
        <div class="actions"><button type="button" id="clienteGuardarDirecto" class="btn btn-secondary">Guardar como cliente nuevo</button></div>
        <p class="ayuda" id="clienteGuardadoEstado" role="status">Guarda estos cuatro datos en Mis clientes sin salir de la propuesta. Para corregir una ficha existente, usa Administrar mis clientes.</p>
      </div>

      <div class="section">
        <h2>Detalles</h2>
        <div class="grid-3">
          <div>
            <label>Vigencia</label>
            <input type="text" name="vigencia" value="<?php echo htmlspecialchars((string)($copia['vigencia'] ?? '15 días'), ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div>
            <label>Método de pago</label>
            <input type="text" name="metodo_pago" value="<?php echo htmlspecialchars((string)($copia['metodo_pago'] ?? 'Transferencia / Efectivo'), ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div>
            <label>Tiempo de instalación</label>
            <input type="text" name="tiempo_instalacion" value="<?php echo htmlspecialchars((string)($copia['tiempo_instalacion'] ?? 'Sujeto a agenda y confirmación'), ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div class="full">
            <label>Descripción</label>
            <textarea name="descripcion"><?php
if ($copia) {
    echo htmlspecialchars((string)($copia['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
} elseif ($solicitud) {
    echo htmlspecialchars(
        "Solicitud: " . $solicitud['folio'] . "\n" .
        "Tipo de equipo: " . $solicitud['tipo_equipo'] . "\n" .
        "Marca: " . $solicitud['marca'] . "\n" .
        "Modelo: " . $solicitud['modelo'] . "\n" .
        "No. Serie: " . $solicitud['numero_serie'] . "\n\n" .
        "Falla reportada:\n" . $solicitud['falla_reportada']
    );
}
?></textarea>
          </div>

          <div class="full">
            <label>Observaciones</label>
            <textarea name="observaciones"><?php
if ($copia) {
    echo htmlspecialchars((string)($copia['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8');
} elseif ($solicitud) {
    echo htmlspecialchars(
        "Persona que atiende: " . $solicitud['persona_atiende'] . "\n" .
        "Fecha preferida: " . $solicitud['fecha_preferida'] . "\n" .
        "Horario preferido: " . $solicitud['horario_preferido'] . "\n\n" .
        "Observaciones del cliente:\n" . $solicitud['observaciones']
    );
}
?></textarea>
          </div>

          <div class="full">
            <label>Condiciones</label>
            <textarea name="condiciones"><?php echo htmlspecialchars((string)($copia['condiciones'] ?? ('La presente cotización está sujeta a disponibilidad de equipo. Los precios pueden cambiar sin previo aviso. El servicio de instalación se agenda una vez confirmado el anticipo (50%) o pago acordado.' . ($bancoTexto !== '' ? ' ' . $bancoTexto : ''))), ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
        </div>
      </div>

      <div class="section calculadora">
        <h2>Calculadora de precio de venta</h2>
        <p>Calcula tu precio desde el costo del proveedor. Los costos y la ganancia son de consulta interna; al documento solo pasa el concepto y su precio de venta.</p>
        <div class="grid-3">
          <div><label for="calcConcepto">Concepto</label><input id="calcConcepto" type="text" placeholder="Cámara, cableado, instalación…"></div>
          <div><label for="calcCosto">Costo unitario (MXN)</label><input id="calcCosto" type="number" min="0" step="0.01" value="0"></div>
          <div><label for="calcIva">IVA del costo</label><select id="calcIva"><option value="incluido">El costo incluye 16% de IVA</option><option value="sin">El costo no incluye IVA</option></select></div>
          <div><label for="calcMetodo">Cómo calcular la ganancia</label><select id="calcMetodo"><option value="margen">Margen sobre precio de venta</option><option value="recargo">Recargo sobre costo</option></select></div>
          <div><label for="calcPorcentaje">Porcentaje</label><input id="calcPorcentaje" type="number" min="0" step="0.01" value="30"></div>
          <div><label for="calcCantidad">Cantidad</label><input id="calcCantidad" type="number" min="1" step="1" value="1"></div>
        </div>
        <div class="actions"><button type="button" class="btn btn-secondary" data-porcentaje="30">Videovigilancia: 30%</button><button type="button" class="btn btn-secondary" data-porcentaje="20">Redes: 20%</button></div>
        <p class="ayuda" id="calcExplicacion"></p>
        <div class="resumen-calc" aria-live="polite">
          <div>Costo sin IVA / unidad<strong id="calcBase">$0.00</strong></div>
          <div>Venta sin IVA / unidad<strong id="calcVenta">$0.00</strong></div>
          <div>Venta con IVA / unidad<strong id="calcFinal">$0.00</strong></div>
          <div>Ganancia estimada / unidad<strong id="calcGanancia">$0.00</strong></div>
        </div>
        <p class="ayuda">La ganancia estimada es venta menos costo, antes de otros gastos e impuestos. Los porcentajes sugeridos se pueden modificar.</p>
        <p id="calcEstado" role="status"></p>
        <button type="button" class="btn btn-primary" id="calcAgregar">Agregar resultado a la cotización</button>
        <details style="margin-top:18px">
          <summary style="cursor:pointer;font-weight:bold;color:#0d3b66">Guardar este precio en Mis conceptos</summary>
          <p class="ayuda">Se guarda el nombre escrito arriba y su precio de venta unitario sin IVA. Los costos y la ganancia permanecen como consulta de la calculadora.</p>
          <div class="grid-3">
            <div><label for="calcCodigoCatalogo">Código / modelo (opcional)</label><input type="text" id="calcCodigoCatalogo" maxlength="80"></div>
            <div><label for="calcTipoCatalogo">Tipo de concepto</label><select id="calcTipoCatalogo"><option value="producto">Producto</option><option value="servicio">Servicio</option></select></div>
            <div><label for="calcUnidadCatalogo">Unidad</label><select id="calcUnidadCatalogo"><option value="pieza">Pieza</option><option value="metro">Metro</option><option value="servicio">Servicio</option><option value="visita">Visita</option><option value="hora">Hora</option><option value="jornada">Jornada</option><option value="lote">Lote</option></select></div>
          </div>
          <div class="actions"><button type="button" class="btn btn-secondary" id="calcGuardarCatalogo">Guardar en Mis conceptos</button><a class="top-link" href="catalogo.php" target="_blank" rel="noopener">Administrar conceptos</a></div>
          <p class="ayuda" id="calcCatalogoEstado" role="status">Guardar el concepto no agrega un renglón ni guarda el borrador de la propuesta.</p>
        </details>
      </div>
      <div class="section calculadora" id="calcDisco">
        <h2>Calculadora de almacenamiento (CCTV)</h2>
        <p class="ayuda">Estima GB/día y sugiere el disco según cámaras, resolución, fps, días de retención y modo de grabación.</p>
        <div class="grid-3">
          <div><label for="dCam">Cámaras</label><input id="dCam" type="number" min="1" max="256" step="1" value="4"></div>
          <div><label for="dRes">Resolución</label><select id="dRes"><option value="2">2 MP (1080p)</option><option value="4">4 MP</option><option value="5">5 MP</option><option value="8" selected>8 MP (4K)</option></select></div>
          <div><label for="dFps">FPS por cámara</label><input id="dFps" type="number" min="1" max="60" step="1" value="15"></div>
          <div><label for="dDias">Días de retención</label><input id="dDias" type="number" min="1" max="3650" step="1" value="30"></div>
          <div><label for="dModo">Grabación</label><select id="dModo"><option value="1" selected>Continua</option><option value="0.35">Por movimiento (~35%)</option><option value="0.15">Por eventos (~15%)</option></select></div>
          <div><label for="dPrecio">Precio de venta del disco (MXN sin IVA)</label><input id="dPrecio" type="number" min="0" step="0.01" value="0"></div>
        </div>
        <p class="ayuda" id="dExplicacion"></p>
        <div class="resumen-calc" aria-live="polite">
          <div>Estimación <strong id="dGbDia">—</strong> GB/día</div>
          <div>Total estimado <strong id="dTb">—</strong> TB</div>
          <div>Disco recomendado <strong id="dDisco">—</strong></div>
        </div>
        <div class="actions"><button type="button" class="btn btn-primary" id="dAgregar">Agregar disco a la cotización</button></div>
      </div>
      <div class="section">
        <h2>Agregar desde mis conceptos</h2>
        <p class="ayuda">Busca un producto o servicio guardado. Se agrega con su precio unitario sin IVA y puedes ajustarlo en la tabla.</p>
        <div class="grid-3">
          <div><label for="catalogoQuery">Nombre o modelo</label><input type="search" id="catalogoQuery" maxlength="100" placeholder="Ejemplo: visita técnica"></div>
          <div><label for="catalogoCategoria">Tipo</label><select id="catalogoCategoria"><option value="">Todos</option><option value="producto">Productos</option><option value="servicio">Servicios</option></select></div>
          <div><label for="catalogoCantidad">Cantidad a agregar</label><input type="number" id="catalogoCantidad" min="1" step="1" value="1"></div>
        </div>
        <div class="actions"><button type="button" id="catalogoBuscar" class="btn btn-secondary">Buscar conceptos</button><a class="top-link" href="catalogo.php" target="_blank" rel="noopener">Administrar mis conceptos</a></div>
        <p id="catalogoEstado" role="status">Pulsa Buscar conceptos para consultar tu catálogo.</p>
        <div id="catalogoResultados"></div>
      </div>
      <section class="syscom-panel" id="syscomCotizador">
        <h2>Agregar desde SYSCOM</h2>
        <p class="syscom-ayuda">Elige el catálogo CSV o consulta tu cuenta SYSCOM en línea. Costos en USD sin IVA; el precio de venta se calcula con tus parámetros.</p>
        <div class="syscom-grid">
          <div><label for="syscomOrigen">Dónde buscar</label><select id="syscomOrigen"><option value="csv">Mi catálogo CSV</option><option value="api">SYSCOM en línea (API)</option></select></div>
          <div id="syscomTipoZona" hidden><label for="syscomTipoBusqueda">Búsqueda en línea</label><select id="syscomTipoBusqueda"><option value="texto">Texto / descripción</option><option value="modelo">Modelo exacto</option></select></div>
        </div>
        <div class="syscom-grid">
          <div><label for="syscomQuery">Modelo, marca o descripción</label><input type="search" id="syscomQuery" maxlength="100" placeholder="Ejemplo: IDS-7204HQHI-M1/T"></div>
          <div><label for="syscomCategoria">Categoría del CSV</label><select id="syscomCategoria"><option value="">Todas</option></select></div>
          <div><label for="syscomVeracruz"><input type="checkbox" id="syscomVeracruz"> Sólo existencia Veracruz</label></div>
        </div>
        <div class="syscom-acciones" id="syscomRecientes" hidden></div>
        <div class="syscom-acciones"><button type="button" class="syscom-btn" id="syscomBuscar">Buscar en catálogo CSV</button><a class="syscom-btn syscom-secundario" href="syscom.php" target="_blank" rel="noopener">Importar / actualizar catálogo</a><a class="syscom-btn syscom-secundario" href="syscom_conexion.php" target="_blank" rel="noopener">Conectar API SYSCOM</a></div>
        <p class="syscom-ayuda" id="syscomFuente"></p><p id="syscomEstado" role="status"></p><div id="syscomResultados"></div>
        <div class="syscom-acciones" id="syscomPaginacion" hidden><button type="button" class="syscom-btn syscom-secundario" id="syscomPaginaAnterior">Anterior</button><span id="syscomPaginaActual"></span><button type="button" class="syscom-btn syscom-secundario" id="syscomPaginaSiguiente">Siguiente</button></div>
        <div id="syscomSeleccion" hidden>
          <h3 id="syscomProductoTitulo"></h3>
          <p class="syscom-ayuda" id="syscomProductoDatos"></p>
          <div class="syscom-acciones"><button type="button" class="syscom-btn" id="syscomActualizarAPI">Actualizar precio y existencia (API)</button><button type="button" class="syscom-btn syscom-secundario" id="syscomUsarCSV" hidden>Usar datos del CSV</button></div>
          <p class="syscom-ayuda" id="syscomEnLineaEstado" role="status" aria-live="polite"></p>
          <label for="syscomConcepto">Descripción que llevará el documento</label><input type="text" id="syscomConcepto" maxlength="255">
          <div class="syscom-grid">
            <div><label for="syscomTC">Tipo de cambio MXN por USD</label><input type="number" id="syscomTC" min="0.0001" max="999.9999" step="0.0001" inputmode="decimal" placeholder="Captura el tipo de cambio de tu compra"></div>
            <div><label for="syscomMetodo">Método de precio de venta</label><select id="syscomMetodo"><option value="margen">Margen sobre venta</option><option value="recargo">Recargo sobre costo</option></select></div>
            <div><label for="syscomPorcentaje">Porcentaje</label><input type="number" id="syscomPorcentaje" min="0" max="10000" step="0.01" value="30" inputmode="decimal"></div>
          </div>
          <div class="syscom-acciones"><button type="button" class="syscom-btn syscom-secundario" id="syscomGuardarPreferencias" disabled>Guardar estos parámetros</button><button type="button" class="syscom-btn syscom-secundario" id="syscomAplicarPreferencias">Aplicar guardados</button></div>
          <p class="syscom-ayuda" id="syscomPreferenciasEstado" role="status" aria-live="polite">Consultando tus parámetros guardados…</p>
          <p class="syscom-ayuda">Se guardan para tu usuario y se precargan al abrir el cotizador. El tipo de cambio es el que capturaste; revísalo antes de cotizar. Este guardado no guarda el borrador ni cambia precios de conceptos ya agregados.</p>
          <p class="syscom-ayuda">Margen: costo ÷ (1 − porcentaje/100). Recargo: costo × (1 + porcentaje/100). No incluye flete ni otros gastos; agrégalos a la propuesta cuando correspondan.</p>
          <div class="syscom-resultados-calculo"><div>Costo unitario MXN sin IVA<strong id="syscomCosto">—</strong></div><div>Venta unitaria MXN sin IVA<strong id="syscomVenta">—</strong></div><div>Venta unitaria MXN con IVA<strong id="syscomFinal">—</strong></div></div>
          <div class="syscom-grid"><div><label for="syscomCantidad">Cantidad a agregar</label><input type="number" id="syscomCantidad" min="1" step="1" value="1" inputmode="decimal"></div></div>
          <div class="syscom-acciones"><button type="button" class="syscom-btn" id="syscomAgregar" disabled>Agregar con este precio de venta</button></div>
          <p class="syscom-ayuda" id="syscomCalculoEstado" role="status"></p>
          <p class="syscom-ayuda">Se agrega el precio de venta en pesos sin IVA. El documento calcula su IVA al final. Costos, margen y tipo de cambio no se imprimen ni quedan guardados en el borrador; sí se conserva la descripción y el precio de venta agregado.</p>
        </div>
      </section>
      <div class="section">
        <h2>Productos / conceptos</h2>
        <p class="ayuda">Captura los precios de venta unitarios originales <strong>sin IVA</strong>. El documento suma el 16% al final. Incluye mano de obra, cableado y viáticos como conceptos independientes.</p>

        <table id="conceptosTabla">
          <thead>
            <tr>
              <th>Concepto</th>
              <th>Cantidad</th>
              <th>Precio original sin IVA</th>
              <th>Descuento %</th>
              <th>Precio final sin IVA</th>
              <th>Importe sin IVA</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody id="productosBody">
            <?php foreach ($itemsCopia ?: [['concepto' => '', 'cantidad' => '', 'precio' => '']] as $itemFormulario): ?>
            <tr>
              <td><input type="text" name="concepto[]" required value="<?php echo htmlspecialchars((string)$itemFormulario['concepto'], ENT_QUOTES, 'UTF-8'); ?>"></td>
              <td><input type="number" name="cantidad[]" class="cantidad" min="1" step="1" required value="<?php echo htmlspecialchars((string)$itemFormulario['cantidad'], ENT_QUOTES, 'UTF-8'); ?>"></td>
              <td><input type="number" name="precio[]" class="precio" min="0" step="0.01" required value="<?php echo htmlspecialchars((string)$itemFormulario['precio'], ENT_QUOTES, 'UTF-8'); ?>"></td>
              <td><input type="number" name="descuento[]" class="descuento" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string)($itemFormulario['descuento'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Porcentaje de descuento"></td>
              <td class="precio-final">$0.00</td>
              <td class="importe">$0.00</td>
              <td><button type="button" class="btn btn-secondary btn-duplicar">Duplicar</button> <button type="button" class="btn btn-danger btn-eliminar">Eliminar</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="actions">
          <button type="button" class="btn btn-secondary" id="agregar">+ Agregar producto</button>
        </div>

        <p class="ayuda">El descuento se aplica al precio unitario y se redondea a centavos. El importe es cantidad × precio final, antes de IVA.</p>
        <p class="ayuda" id="descuentosEstado" role="status"></p>
        <div class="totals resumen-totales" aria-live="polite">
          <div class="ayuda">Importe original sin IVA <span id="brutoVista">$0.00</span></div>
          <div class="ayuda">Descuento total <span id="descuentoVista">$0.00</span></div>
          <div>Subtotal final <span id="subtotalVista">$0.00</span></div>
          <div>IVA (16%) <span id="ivaVista">$0.00</span></div>
          <div>Total <span id="totalVista">$0.00</span></div>
          <div class="ayuda">Anticipo sugerido: <input id="anticipoPorcentaje" type="number" min="0" max="100" step="0.01" value="50" aria-label="Porcentaje de anticipo"> % = <span id="anticipoVista">$0.00</span></div>
          <div class="ayuda">Saldo estimado <span id="saldoVista">$0.00</span></div>
          <p class="ayuda">El anticipo es orientativo; no registra un pago ni modifica las condiciones del documento.</p>
        </div>
      </div>

      <div class="section">
        <h2>Revisar antes de generar</h2>
        <div class="grid">
          <div><label for="tipoVistaPrevia">Documento a revisar</label><select id="tipoVistaPrevia"><option value="cotizacion">Cotización</option><option value="nota_venta">Nota de venta</option><option value="nota_servicio">Nota de servicio</option></select></div>
          <div class="actions"><button class="btn btn-secondary" type="submit" name="vista_previa" value="1" onclick="setDoc(document.getElementById('tipoVistaPrevia').value,'ver')">Vista previa sin guardar</button></div>
        </div>
        <p class="ayuda">Abre un PDF de revisión sin asignar folio, guardar un documento ni completar el borrador. Guarda el borrador manualmente si necesitas retomar la propuesta después.</p>
      </div>
      <h2 style="color:#0d3b66">Generar documento definitivo</h2>
      <p class="ayuda">Estos botones guardan el documento y asignan su folio.</p>
      <div class="actions">
        <button class="btn btn-secondary" type="submit" onclick="setDoc('cotizacion','ver')">Generar y guardar cotización</button>
        <button class="btn btn-green" type="submit" onclick="setDoc('nota_venta','ver')">Generar y guardar nota de venta</button>
        <button class="btn btn-orange" type="submit" onclick="setDoc('nota_servicio','ver')">Generar y guardar nota de servicio</button>
        <button class="btn btn-primary" type="submit" onclick="setDoc('cotizacion','descargar')">Guardar y descargar cotización</button>
                            <button class="btn btn-secondary" type="submit" name="accion" value="plano" onclick="setDoc('cotizacion','ver')">
    Guardar cotización y diseñar plano
</button>
                            <button class="btn btn-primary" type="submit" name="accion" value="pdf" onclick="setDoc('cotizacion','ver')">
    Guardar y generar PDF
</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/cotizador_mejoras.js?v=20261016-57"></script>
<script src="assets/catalogo.js?v=20261016"></script>
<script src="assets/clientes.js?v=20261016-54"></script>
<script src="assets/calculadora_catalogo.js?v=20261016-55"></script>
<script src="assets/calculadora_disco.js?v=1"></script>
<script src="assets/aviso_guardado.js?v=20261016-58"></script>
<script src="assets/cotizador_movil.js?v=20261016-59"></script>
<script src="assets/syscom_comun.js?v=20261018-5121"></script>
<script src="assets/syscom_cotizador.js?v=20261020-516"></script>
<script src="assets/syscom_preferencias.js?v=20261019-513"></script>
</body>
</html>
