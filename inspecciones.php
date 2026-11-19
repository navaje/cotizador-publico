<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/db.php';require_once __DIR__.'/app/inspecciones.php';
header('Cache-Control: private, no-store, max-age=0');
$ojcCssVersion=substr(hash_file('sha256',__DIR__.'/assets/inspecciones.css'),0,16);
$ojcJsVersion=substr(hash_file('sha256',__DIR__.'/assets/inspecciones.js'),0,16);
date_default_timezone_set('America/Mexico_City');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$id=(int)($_GET['id']??0);$docId=(int)($_GET['documento']??0);$error='';$loaded=null;$photos=[];$versions=[];$closed=false;
try{
    if($id>0){$loaded=ojc_load($pdo,$id);$base=$loaded['datos']['base'];$form=$loaded['datos']['form'];$photos=$loaded['datos']['fotos'];$docId=(int)$base['documento_id'];$closed=$loaded['registro']['estado']==='cerrado';
        $q=$pdo->prepare('SELECT version,autor,creado_en FROM oj_inspeccion_versiones WHERE inspeccion_id = ? ORDER BY version DESC');$q->execute([$id]);$versions=$q->fetchAll(PDO::FETCH_ASSOC);
    }elseif($docId>0){
        $base=ojc_base($pdo,$docId);$form=['fecha'=>date('Y-m-d'),'tecnico'=>$_SESSION['usuario_nombre']??'','sistema'=>'ip','visita'=>'inspeccion','instaladas'=>0,'operativas'=>0,'dias_requeridos'=>0,'dias_disponibles'=>0,'inventario'=>[],'camaras'=>[],'grupos'=>[],'hallazgos'=>[],'resultado'=>'pendiente','recepcion'=>'pendiente','recepcion_confirmada'=>false,'firma'=>''];
    }else{
        $search=trim(is_scalar($_GET['q']??'')?(string)($_GET['q']??''):'');$term='%'.substr($search,0,100).'%';
        $q=$pdo->prepare('SELECT i.*,v.datos FROM oj_inspecciones i JOIN oj_inspeccion_versiones v ON v.inspeccion_id=i.id AND v.version=i.version WHERE i.folio LIKE ? OR v.datos LIKE ? ORDER BY i.id DESC LIMIT 100');$q->execute([$term,$term]);$reports=$q->fetchAll(PDO::FETCH_ASSOC);
    }
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!$id&&!$docId)throw new InvalidArgumentException('Selecciona una nota de servicio.');
        if(empty($_POST)&&(int)($_SERVER['CONTENT_LENGTH']??0)>0)throw new InvalidArgumentException('La carga supera el límite del hosting. Vuelve a adjuntar menos fotografías.');
        require_post_csrf();
        $json=ojc_text($_POST,'datos',1200000,true);$raw=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($raw))throw new InvalidArgumentException('Datos de formulario inválidos.');
        $form=$raw;
        $saved=ojc_save($pdo,$id,$docId,(int)($_POST['version']??0),$raw,$_POST,$_FILES,$_SESSION,($_POST['accion']??'')==='cerrar');
        header('Location: inspecciones.php?id='.$saved.'&guardado=1');exit;
    }
}catch(PDOException $e){error_log('OJ checklist: '.$e->getMessage());http_response_code(503);exit('No se pudo acceder a las inspecciones. Importa MIGRACION_INSPECCIONES.sql en la base del cotizador. Si ya lo hiciste, revisa el registro de errores.');}
catch(Throwable $e){$error=$e->getMessage();if(!isset($base)){http_response_code(400);exit(ojc_e($error));}}
if(isset($base)){
    $target=$id>0?'i:'.$id:'d:'.$docId;$token=bin2hex(random_bytes(32));$_SESSION['ojc_tokens'][$token]=$target;
    while(count($_SESSION['ojc_tokens'])>30)array_shift($_SESSION['ojc_tokens']);
}
?><!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Inspección y mantenimiento CCTV - Ojo Jarocho</title><link rel="stylesheet" href="assets/inspecciones.css?v=<?=ojc_e($ojcCssVersion)?>"></head><body><main>
<nav><a href="dashboard.php">Panel</a> · <a href="listado.php">Documentos</a> · <a href="inspecciones.php">Historial de inspecciones</a></nav>
<h1>Inspección y mantenimiento de CCTV / IP</h1>
<?php if(!isset($base)): ?>
<p>Para iniciar una checklist, abre <strong>Checklist CCTV / IP</strong> en una Nota de servicio del listado. Puedes registrar varias visitas para la misma nota.</p>
<form method="get"><label>Buscar folio, cliente o proyecto<input name="q" value="<?=ojc_e($search)?>" maxlength="100"></label><button>Buscar</button></form>
<p>Últimos 100 resultados.</p><div class="scroll"><table><tr><th>Folio</th><th>Cliente</th><th>Proyecto</th><th>Estado</th><th>Versión</th><th>Acciones</th></tr>
<?php foreach($reports as $report):$snapshot=json_decode($report['datos'],true); ?><tr><td><?=ojc_e($report['folio'])?></td><td><?=ojc_e($snapshot['base']['cliente']['nombre'])?></td><td><?=ojc_e($snapshot['base']['proyecto'])?></td><td><?=ojc_e($report['estado'])?></td><td><?=(int)$report['version']?></td><td><a href="inspecciones.php?id=<?=(int)$report['id']?>">Abrir</a> · <a href="inspeccion_pdf.php?id=<?=(int)$report['id']?>" target="_blank" rel="noopener">PDF</a></td></tr><?php endforeach; ?>
<?php if(!$reports):?><tr><td colspan="6">No hay inspecciones registradas para esta búsqueda.</td></tr><?php endif;?></table></div>
<?php else: ?>
<p><strong><?=ojc_e($loaded['registro']['folio']??'Nueva checklist')?></strong> · <?=ojc_e($base['cliente']['nombre'])?> · <?=ojc_e($base['proyecto'])?></p>
<p>Documentos relacionados: <?php foreach($base['documentos'] as $doc):?><a href="ver-cotizacion.php?id=<?=(int)$doc['id']?>" target="_blank" rel="noopener"><?=ojc_e($doc['folio'])?></a> <?php endforeach;?></p>
<?php if($error):?><p class="error"><?=ojc_e($error)?> Si adjuntaste fotos en este intento, vuelve a seleccionarlas.</p><?php endif;?>
<?php if(isset($_GET['guardado'])):?><p class="success">Inspección guardada. Puedes revisar o descargar el PDF.</p><?php endif;?>
<?php if($id):?><p><a class="button" href="inspeccion_pdf.php?id=<?=$id?>" target="_blank" rel="noopener">Abrir PDF actual</a> <a class="button" href="inspeccion_pdf.php?id=<?=$id?>&descargar=1">Descargar PDF</a> <a href="inspecciones.php?documento=<?=$docId?>">Nueva visita para esta nota</a></p><?php endif;?>
<?php if($closed):?><p class="notice">Inspección cerrada. Se conserva para consulta; inicia una nueva visita para una revisión posterior.</p><?php else:?><p class="notice">Guarda borradores durante la visita. Para cerrar, completa la revisión inicial y final de todas las cámaras y puntos; usa No aplica donde corresponda. No escribas contraseñas.</p><?php endif;?>
<?php if(!$closed):?><p id="ojc-update-status" class="notice" role="status">Cargando ayuda de cierre…</p><?php endif;?>
<form id="checklist" method="post" enctype="multipart/form-data" action="inspecciones.php?<?= $id?'id='.$id:'documento='.$docId ?>">
<?=csrf_field()?><input type="hidden" name="solicitud_token" value="<?=$token?>"><input type="hidden" name="version" value="<?=(int)($loaded['registro']['version']??0)?>"><input id="datos" type="hidden" name="datos">
<div id="editor"></div>
<h2>Fotografías de evidencia</h2><p>Identifica la ubicación y describe el hallazgo o corrección. Máximo 12 fotos por reporte y 3 nuevas por guardado.</p>
<div class="photos"><?php foreach($photos as $idx=>$photo):?><article><a href="inspeccion_foto.php?id=<?=$id?>&indice=<?=$idx?>" target="_blank" rel="noopener"><img src="inspeccion_foto.php?id=<?=$id?>&indice=<?=$idx?>" alt="<?=ojc_e($photo['descripcion'])?>" loading="lazy"></a><p><strong><?=ojc_e($photo['etapa'])?> · <?=ojc_e($photo['ubicacion'])?></strong><br><?=ojc_e($photo['descripcion'])?></p><?php if(!$closed):?><label class="check"><input type="checkbox" name="quitar_fotos[]" value="<?=ojc_e($photo['archivo'])?>"> Quitar de la versión nueva</label><?php endif;?></article><?php endforeach;?></div>
<?php if(!$closed):?><details><summary>Adjuntar fotografías nuevas</summary><p>JPG/PNG. La página intenta reducir las imágenes a 1600 píxeles antes de enviarlas. Cada archivo recibido debe pesar hasta 2 MB.</p>
<?php for($i=0;$i<3;$i++):?><fieldset><legend>Foto <?=$i+1?></legend><div class="grid"><label>Imagen<input class="photo-upload" type="file" name="foto[]" accept="image/jpeg,image/png"></label><label>Etapa<select name="foto_etapa[]"><option value="inicial">Antes / inspección inicial</option><option value="final">Después / revisión final</option></select></label><label>Cámara, equipo o ubicación<input name="foto_ubicacion[]" maxlength="200"></label><label>Descripción<input name="foto_descripcion[]" maxlength="500"></label></div></fieldset><?php endfor;?></details><?php endif;?>
<div id="reception"></div>
<?php if(!$closed):?><div class="savebar"><button type="submit" name="accion" value="guardar">Guardar borrador</button><button class="finish" type="submit" name="accion" value="cerrar">Cerrar inspección y generar reporte</button></div><?php endif;?>
</form>
<?php if($versions):?><details><summary>Versiones guardadas</summary><p>Los PDF de versiones anteriores muestran los datos y evidencias de ese momento.</p><ul><?php foreach($versions as $version):?><li><a href="inspeccion_pdf.php?id=<?=$id?>&version=<?=(int)$version['version']?>" target="_blank" rel="noopener">PDF versión <?=(int)$version['version']?></a> · <?=ojc_e($version['creado_en'])?> · <?=ojc_e($version['autor'])?></li><?php endforeach;?></ul></details><?php endif;?>
<script id="form-data" type="application/json"><?=ojc_json($form)?></script><script id="schema" type="application/json"><?=ojc_json(ojc_schema())?></script><script id="statuses" type="application/json"><?=ojc_json(ojc_statuses())?></script><script>window.ojcClosed=<?= $closed?'true':'false' ?>;</script><script src="assets/inspecciones.js?v=<?=ojc_e($ojcJsVersion)?>"></script>
<?php endif;?></main></body></html>
