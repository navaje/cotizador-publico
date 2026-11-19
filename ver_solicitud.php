<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido');
}

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM solicitudes_servicio WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    die('Solicitud no encontrada');
}

function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function estadoClass($estado) {
    if ($estado === 'nuevo') return 'badge-blue';
    if ($estado === 'contactado') return 'badge-yellow';
    if ($estado === 'visita_programada') return 'badge-purple';
    if ($estado === 'cotizado') return 'badge-orange';
    if ($estado === 'aceptado') return 'badge-teal';
    if ($estado === 'atendido') return 'badge-green';
    if ($estado === 'perdido') return 'badge-red';
    return 'badge-gray';
}

function estadoLabel($estado) {
    $labels = [
        'nuevo'=>'Nuevo', 'contactado'=>'Contactado',
        'visita_programada'=>'Visita programada', 'cotizado'=>'Cotizado',
        'aceptado'=>'Aceptado', 'atendido'=>'Atendido', 'perdido'=>'Perdido'
    ];
    return $labels[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitud <?php echo e($solicitud['folio']); ?> - Ojo Jarocho</title>
<style>
body{
  font-family:Arial, Helvetica, sans-serif;
  background:#eef3f8;
  margin:0;
  padding:24px;
  color:#1f2937;
}
.wrap{
  max-width:1000px;
  margin:0 auto;
  background:#fff;
  border-radius:14px;
  box-shadow:0 10px 30px rgba(0,0,0,.08);
  overflow:hidden;
}
.topbar{
  background:#0d3b66;
  color:#fff;
  padding:22px 28px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
}
.topbar h1{
  margin:0;
  font-size:26px;
}
.content{
  padding:24px;
}
.actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom:18px;
}
.btn{
  display:inline-block;
  background:#0d3b66;
  color:#fff;
  text-decoration:none;
  padding:10px 14px;
  border-radius:8px;
  font-weight:bold;
  font-size:14px;
}
.btn-secondary{
  background:#dbe7f3;
  color:#0d3b66;
}
.btn-red{
  background:#fee2e2;
  color:#b91c1c;
}
.btn-yellow{
  background:#fef3c7;
  color:#b45309;
}
.btn-purple{
  background:#ede9fe;
  color:#6d28d9;
}
.btn-green{
  background:#dcfce7;
  color:#15803d;
}
.btn-orange{ background:#ffedd5; color:#c2410c; }
.btn-teal{ background:#ccfbf1; color:#0f766e; }
.grid{
  display:grid;
  grid-template-columns:repeat(2,1fr);
  gap:16px;
}
.card{
  background:#f8fafc;
  border:1px solid #d6dee8;
  border-radius:12px;
  padding:16px;
}
.card.full{
  grid-column:1/-1;
}
.card h2{
  margin:0 0 12px;
  font-size:18px;
  color:#0d3b66;
}
.row{
  margin-bottom:8px;
  line-height:1.4;
}
.label{
  font-weight:bold;
  color:#111827;
}
.badge{
  display:inline-block;
  padding:7px 11px;
  border-radius:999px;
  font-size:12px;
  font-weight:bold;
}
.badge-blue{ background:#dbeafe; color:#1d4ed8; }
.badge-yellow{ background:#fef3c7; color:#b45309; }
.badge-purple{ background:#ede9fe; color:#6d28d9; }
.badge-orange{ background:#ffedd5; color:#c2410c; }
.badge-teal{ background:#ccfbf1; color:#0f766e; }
.badge-green{ background:#dcfce7; color:#15803d; }
.badge-red{ background:#fee2e2; color:#b91c1c; }
.badge-gray{ background:#e5e7eb; color:#374151; }
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;}
.form-grid .full{grid-column:1/-1;}
.form-grid label{display:block;font-weight:bold;font-size:13px;margin-bottom:6px;}
.form-grid input,.form-grid select,.form-grid textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;}
.form-grid textarea{min-height:110px;resize:vertical;}
@media(max-width:800px){
  .grid{
    grid-template-columns:1fr;
  }
  body{
    padding:12px;
  }
  .content{
    padding:16px;
  }
}
</style>
</head>
<body>

<div class="wrap">
  <div class="topbar">
    <h1>Solicitud <?php echo e($solicitud['folio']); ?></h1>
    <span class="badge <?php echo estadoClass($solicitud['estado']); ?>">
      <?php echo e(estadoLabel($solicitud['estado'])); ?>
    </span>
  </div>

  <div class="content">
    <div class="actions">
      
      <a class="btn btn-secondary" href="solicitudes.php">Volver</a>
<a class="btn" href="whatsapp_solicitud.php?id=<?php echo $id; ?>" target="_blank">WhatsApp</a>
       <a class="btn btn-green" href="index.php?solicitud_id=<?php echo $id; ?>">
  Crear cotización
</a>
      <?php foreach (['contactado'=>'Contactado','visita_programada'=>'Visita programada','cotizado'=>'Cotizado','aceptado'=>'Aceptado','atendido'=>'Atendido','perdido'=>'Perdido'] as $estado => $etiqueta): ?>
      <form method="post" action="cambiar_estado_solicitud.php" style="display:inline-block;margin:0">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="estado" value="<?php echo $estado; ?>">
        <button type="submit" class="btn <?php echo $estado === 'contactado' ? 'btn-yellow' : ($estado === 'visita_programada' ? 'btn-purple' : ($estado === 'cotizado' ? 'btn-orange' : ($estado === 'aceptado' ? 'btn-teal' : ($estado === 'atendido' ? 'btn-green' : 'btn-red')))); ?>"><?php echo $etiqueta; ?></button>
      </form>
      <?php endforeach; ?>
    </div>

    <div class="grid">
      <div class="card">
        <h2>Datos del cliente</h2>
        <div class="row"><span class="label">Nombre:</span> <?php echo e($solicitud['cliente_nombre']); ?></div>
        <div class="row"><span class="label">Teléfono:</span> <?php echo e($solicitud['cliente_telefono']); ?></div>
        <div class="row"><span class="label">Correo:</span> <?php echo e($solicitud['cliente_email']); ?></div>
        <div class="row"><span class="label">Persona que atiende:</span> <?php echo e($solicitud['persona_atiende']); ?></div>
        <div class="row"><span class="label">Dirección:</span><br><?php echo nl2br(e($solicitud['direccion'])); ?></div>
      </div>

      <div class="card">
        <h2>Agenda</h2>
        <div class="row"><span class="label">Fecha preferida:</span> <?php echo e($solicitud['fecha_preferida']); ?></div>
        <div class="row"><span class="label">Horario preferido:</span> <?php echo e($solicitud['horario_preferido']); ?></div>
        <div class="row"><span class="label">Fecha de solicitud:</span> <?php echo e($solicitud['created_at']); ?></div>
        <div class="row"><span class="label">Origen:</span> <?php echo e(ucfirst($solicitud['origen'] ?? 'web')); ?></div>
      </div>

      <div class="card">
        <h2>Datos del equipo</h2>
        <div class="row"><span class="label">Tipo de equipo:</span> <?php echo e($solicitud['tipo_equipo']); ?></div>
        <div class="row"><span class="label">Marca:</span> <?php echo e($solicitud['marca']); ?></div>
        <div class="row"><span class="label">Modelo:</span> <?php echo e($solicitud['modelo']); ?></div>
        <div class="row"><span class="label">Número de serie:</span> <?php echo e($solicitud['numero_serie']); ?></div>
        <div class="row"><span class="label">Tipo de servicio:</span> <?php echo e($solicitud['tipo_servicio']); ?></div>
      </div>

      <div class="card">
        <h2>Falla reportada</h2>
        <div class="row"><?php echo nl2br(e($solicitud['falla_reportada'])); ?></div>
      </div>

      <div class="card full">
        <h2>Observaciones adicionales</h2>
        <div class="row"><?php echo nl2br(e($solicitud['observaciones'])); ?></div>
      </div>

      <?php if (!empty($solicitud['producto_nombre']) || !empty($solicitud['producto_sku']) || !empty($solicitud['producto_url'])): ?>
      <div class="card full">
        <h2>Producto del catálogo</h2>
        <div class="row"><span class="label">Producto:</span> <?php echo e($solicitud['producto_nombre']); ?></div>
        <div class="row"><span class="label">SKU:</span> <?php echo e($solicitud['producto_sku']); ?></div>
        <?php if (!empty($solicitud['producto_url'])): ?><a href="<?php echo e($solicitud['producto_url']); ?>" target="_blank" rel="noopener">Abrir producto</a><?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="card full">
        <h2>Seguimiento interno</h2>
        <form method="post" action="actualizar_seguimiento.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo $id; ?>">
          <div class="form-grid">
            <div><label>Origen</label><select name="origen"><?php foreach (['web'=>'Web','catalogo'=>'Catálogo','facebook'=>'Facebook','whatsapp'=>'WhatsApp','recomendacion'=>'Recomendación','otro'=>'Otro'] as $valor=>$texto): ?><option value="<?php echo $valor; ?>" <?php echo ($solicitud['origen'] ?? 'web') === $valor ? 'selected' : ''; ?>><?php echo $texto; ?></option><?php endforeach; ?></select></div>
            <div><label>Próximo seguimiento</label><input type="datetime-local" name="fecha_seguimiento" value="<?php echo !empty($solicitud['fecha_seguimiento']) ? e(date('Y-m-d\TH:i', strtotime($solicitud['fecha_seguimiento']))) : ''; ?>"></div>
            <div class="full"><label>Próxima acción</label><input type="text" name="proxima_accion" maxlength="255" value="<?php echo e($solicitud['proxima_accion'] ?? ''); ?>" placeholder="Ej. llamar para confirmar visita"></div>
            <div><label>Producto</label><input type="text" name="producto_nombre" maxlength="255" value="<?php echo e($solicitud['producto_nombre'] ?? ''); ?>"></div>
            <div><label>SKU</label><input type="text" name="producto_sku" maxlength="100" value="<?php echo e($solicitud['producto_sku'] ?? ''); ?>"></div>
            <div class="full"><label>URL del producto</label><input type="url" name="producto_url" maxlength="500" value="<?php echo e($solicitud['producto_url'] ?? ''); ?>"></div>
            <div class="full"><label>Notas internas (no se envían al cliente)</label><textarea name="notas_internas"><?php echo e($solicitud['notas_internas'] ?? ''); ?></textarea></div>
            <div class="full"><button class="btn" type="submit">Guardar seguimiento</button></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

</body>
</html>
