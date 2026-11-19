<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/app/visitas.php';
date_default_timezone_set('America/Mexico_City');header('Cache-Control: private, no-store');
$user=(int)$_SESSION['usuario_id'];$error='';$dbError=false;$id=0;$v=null;$rows=[];$versions=[];$links=[];$form=ojv_empty();$schema=ojv_schema();$isForm=isset($_GET['nueva'])||isset($_GET['id']);$search='';$nonce='';$editVersion=0;$preserved=false;
try{
    $pdo->query('SELECT id FROM oj_visitas LIMIT 1');
    $id=ojv_id($_GET['id']??null,true);
    if($id){$v=ojv_load($pdo,$id,$user);$form=$v['form'];$editVersion=(int)$v['version'];}
    elseif($isForm&&isset($_GET['origen'])&&$_SERVER['REQUEST_METHOD']!=='POST'){
        $source=ojv_load($pdo,ojv_id($_GET['origen']),$user);$form['cliente_id']=$source['form']['cliente_id'];
        foreach(['cliente_nombre','cliente_telefono','cliente_email','cliente_direccion','mapa','contacto'] as $f)$form['general'][$f]=$source['form']['general'][$f];
        $form['general']['proyecto']=$source['form']['general']['proyecto'];
    }
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(empty($_POST)&&(int)($_SERVER['CONTENT_LENGTH']??0)>0)throw new InvalidArgumentException('La carga excede post_max_size del servidor. Reduce o divide las fotos.');
        require_post_csrf();
        $action=ojv_text($_POST['accion']??'',30);
        if($action==='cotizar'){
            if(!$id)throw new InvalidArgumentException('Guarda primero la visita.');
            require_once __DIR__.'/app/borradores.php';oj_borradores_schema($pdo);
            $b=ojv_convert($pdo,$id,$user,ojv_id($_POST['version']??null));
            $q=$pdo->prepare('SELECT documento_id FROM cotizador_borradores WHERE id=? AND usuario_id=?');$q->execute([$b,$user]);$doc=$q->fetchColumn();
            header('Location: '.($doc?'ver-cotizacion.php?id='.(int)$doc:'index.php?borrador_id='.$b),true,303);exit;
        }
        if(!in_array($action,['guardar','listo'],true))throw new InvalidArgumentException('Acción no válida.');
        if(!$isForm)throw new InvalidArgumentException('Abre una visita antes de guardar.');
        $editVersion=$id?ojv_id($_POST['version']??null):0;
        $json=ojv_text($_POST['datos']??'',650000);$raw=json_decode($json,true,512,JSON_THROW_ON_ERROR);if(!is_array($raw))throw new InvalidArgumentException('Datos no válidos.');$form=$raw;$preserved=true;
        $nonce=ojv_text($_POST['visita_token']??'',80);$target=$id?'id:'.$id:'nueva';
        $token=$_SESSION['ojv_tokens'][$nonce]??null;
        if(!is_array($token)||$token['target']!==$target)throw new InvalidArgumentException('Formulario vencido. Conserva tus datos y vuelve a abrir la visita.');
        if(!empty($token['saved'])){header('Location: visitas.php?id='.(int)$token['saved'],true,303);exit;}
        $saved=ojv_save($pdo,$id,$user,$editVersion,$raw,$_POST,$_FILES,(string)($_SESSION['usuario_nombre']??''),$action==='listo');
        $_SESSION['ojv_tokens'][$nonce]['saved']=$saved;header('Location: visitas.php?id='.$saved.'&guardado=1',true,303);exit;
    }
    if($id){
        $q=$pdo->prepare('SELECT version,autor,creado_en FROM oj_visita_versiones WHERE visita_id=? ORDER BY version DESC LIMIT 100');$q->execute([$id]);$versions=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare('SELECT x.version,b.id,b.documento_id FROM oj_visita_cotizaciones x JOIN cotizador_borradores b ON b.id=x.borrador_id AND b.usuario_id=? WHERE x.visita_id=? ORDER BY x.version DESC');$q->execute([$user,$id]);$links=$q->fetchAll(PDO::FETCH_ASSOC);
    }elseif(!$isForm){
        $search=ojv_text($_GET['q']??'',100);$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
        $q=$pdo->prepare("SELECT id,titulo,cliente_nombre,estado,version,actualizado_en FROM oj_visitas WHERE usuario_id=? AND (titulo LIKE ? ESCAPE '!' OR cliente_nombre LIKE ? ESCAPE '!') ORDER BY actualizado_en DESC,id DESC LIMIT 101");$q->execute([$user,$term,$term]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    }
}catch(PDOException $e){error_log('Visitas: '.$e->getMessage());http_response_code(503);$error='No se pudo acceder a visitas. Si es la primera instalación, prepara el módulo. Si ya estaba instalado, revisa el registro del servidor.';$dbError=true;}
catch(Throwable $e){http_response_code(400);$error=$e->getMessage();}
if($isForm&&!$dbError){
    if(!$nonce){$nonce=bin2hex(random_bytes(24));$_SESSION['ojv_tokens'][$nonce]=['target'=>$id?'id:'.$id:'nueva'];while(count($_SESSION['ojv_tokens'])>40)array_shift($_SESSION['ojv_tokens']);}
    if(!$id&&$_SERVER['REQUEST_METHOD']!=='POST')$form['general']['tecnico']=(string)($_SESSION['usuario_nombre']??'');
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Visitas técnicas · Ojo Jarocho</title><link rel="stylesheet" href="assets/visitas.css?v=1"></head><body><main>
<header><p class="eyebrow">OJO JAROCHO · LEVANTAMIENTO PREVIO</p><h1>Visitas técnicas</h1><nav><a href="dashboard.php">Panel</a><a href="index.php">Cotizador</a><a href="clientes.php">Clientes</a><a href="visitas.php">Historial</a><a href="visitas.php?nueva=1">+ Nueva visita</a></nav></header>
<?php if($error):?><p class="error" role="alert"><?=ojv_e($error)?><?php if($preserved):?> Los campos capturados se conservan en esta pantalla; vuelve a seleccionar las fotos del intento fallido.<?php elseif($_SERVER['REQUEST_METHOD']==='POST'):?> Si los datos no llegaron, vuelve con Atrás para recuperar el formulario anterior.<?php endif;?></p><?php endif;?>
<?php if($dbError):?><p><a href="visitas_instalar.php">Preparar módulo (administrador)</a></p>
<?php elseif(!$isForm):?>
<p>Evalúa el lugar, registra puntos y recorridos, y prepara una propuesta con cantidades. Solo ves las visitas de tu usuario.</p><form method="get" class="search"><label>Buscar cliente o proyecto<input name="q" maxlength="100" value="<?=ojv_e($search)?>"></label><button>Buscar</button></form>
<?php if(count($rows)>100):?><p>Se muestran los últimos 100 resultados. Acota la búsqueda.</p><?php endif;?>
<div class="scroll"><table><thead><tr><th>Visita</th><th>Cliente / proyecto</th><th>Estado</th><th>Actualización</th><th></th></tr></thead><tbody>
<?php foreach(array_slice($rows,0,100) as $r):?><tr><td>VT-<?=str_pad((string)$r['id'],5,'0',STR_PAD_LEFT)?></td><td><b><?=ojv_e($r['cliente_nombre'])?></b><br><?=ojv_e($r['titulo'])?></td><td><?=ojv_e($r['estado'])?> · v<?=(int)$r['version']?></td><td><?=ojv_e($r['actualizado_en'])?></td><td><a href="visitas.php?id=<?=(int)$r['id']?>">Abrir</a></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5">No hay visitas para esta búsqueda.</td></tr><?php endif;?></tbody></table></div>
<?php else:?>
<h2><?= $id?'VT-'.str_pad((string)$id,5,'0',STR_PAD_LEFT):'Nueva visita' ?></h2>
<?php if(isset($_GET['guardado'])):?><p class="ok">Visita guardada correctamente.</p><?php endif;?>
<p class="notice">Guarda durante la visita. “Listo para cotizar” requiere completar los datos esenciales y resolver pendientes. Una modificación se conserva como versión nueva; el guardado es manual.</p>
<?php if($id):?><nav class="actions"><a target="_blank" rel="noopener" href="visita_reporte.php?id=<?=$id?>">Resumen interno</a><a target="_blank" rel="noopener" href="visita_reporte.php?id=<?=$id?>&pdf=1">PDF interno</a><a target="_blank" rel="noopener" href="visita_reporte.php?id=<?=$id?>&cliente=1&pdf=1">PDF para cliente</a><a href="visitas.php?nueva=1&origen=<?=$id?>">Otra visita / mismo cliente</a></nav><?php endif;?>
<form id="visita-form" method="post" enctype="multipart/form-data" action="visitas.php?<?=$id?'id='.$id:'nueva=1'?>">
<?=csrf_field()?><input type="hidden" name="version" value="<?=$editVersion?>"><input type="hidden" name="visita_token" value="<?=ojv_e($nonce)?>"><input id="visita-datos" type="hidden" name="datos">
<p id="save-status" role="status" aria-live="polite"><?= $error?'Hay cambios sin guardar.':'Sin cambios pendientes.' ?></p><noscript><p class="error">Activa JavaScript para capturar y guardar este formulario.</p></noscript>
<section><h2>Elegir cliente guardado (opcional)</h2><p>También puedes escribir sus datos en Cliente y visita. No se modifica la ficha del directorio.</p><div class="search"><label>Nombre, correo o teléfono<input id="client-query" type="search" maxlength="100"></label><button id="client-search" type="button">Buscar</button></div><div id="client-results" aria-live="polite"></div></section>
<div id="visita-editor"></div>
<section><h2>Fotos y croquis</h2><p>Hasta 20 fotos activas por visita y 3 nuevas por guardado. JPG/PNG hasta 2 MB cada una. Los croquis pueden adjuntarse como imagen. Las evidencias quedan fuera del PDF para cliente.</p><div class="photos">
<?php foreach($v['form']['fotos']??[] as $f):?><article><a href="visita_foto.php?id=<?=(int)$f['id']?>" target="_blank" rel="noopener"><img src="visita_foto.php?id=<?=(int)$f['id']?>" alt="<?=ojv_e($f['ubicacion'])?>" loading="lazy"></a><p><b><?=ojv_e($f['ubicacion'])?></b><br><?=ojv_e($f['descripcion'])?></p><label><input type="checkbox" name="quitar_fotos[]" value="<?=(int)$f['id']?>"> Quitar en versión nueva</label></article><?php endforeach;?></div>
<?php for($i=0;$i<3;$i++):?><details><summary>Adjuntar imagen <?=$i+1?></summary><div class="grid"><label>Archivo<input type="file" name="fotos[]" accept="image/jpeg,image/png" class="photo-input"></label><label>Punto / ubicación<input name="foto_ubicacion[]" maxlength="180" placeholder="CAM-01 / recorrido / croquis"></label><label>Descripción<input name="foto_descripcion[]" maxlength="500"></label></div></details><?php endfor;?></section>
<section><h2>Resumen de cantidades</h2><div id="visita-summary"></div><p>Los recorridos compartidos cuentan canalización una vez. El cable por metro se redondea hacia arriba; el cable armado se cuenta por pieza y longitud elegida. El cable incluido en kit no se cotiza de nuevo. Materiales adicionales se suman: evita duplicar los ya calculados.</p></section>
<section><h2>Revisión para cotizar</h2><div id="visita-pending"></div></section>
<div class="savebar"><button name="accion" value="guardar">Guardar borrador</button><button name="accion" value="listo" class="primary">Guardar listo para cotizar</button></div></form>
<?php if($id):?><section><h2>Preparar cotización</h2><p>Usa la última versión guardada y lista. Crea un borrador con cantidades y precios en cero: revisa modelos, conceptos, precios y condiciones antes de emitir. Las fotos y notas internas no se trasladan.</p><form id="convert-form" method="post" action="visitas.php?id=<?=$id?>"><?=csrf_field()?><input type="hidden" name="version" value="<?=(int)$v['version']?>"><button name="accion" value="cotizar" <?= $v['estado']!=='listo'?'disabled':'' ?>>Preparar / abrir borrador de cotización</button></form>
<?php foreach($links as $l):?><p>Versión <?=(int)$l['version']?>: <?php if($l['documento_id']):?>Borrador #<?=(int)$l['id']?> completado · <a href="ver-cotizacion.php?id=<?=(int)$l['documento_id']?>" target="_blank" rel="noopener">Documento generado</a><?php else:?><a href="index.php?borrador_id=<?=(int)$l['id']?>">Borrador #<?=(int)$l['id']?></a><?php endif;?></p><?php endforeach;?></section>
<details><summary>Versiones guardadas (últimas 100)</summary><?php foreach($versions as $r):?><p>v<?=(int)$r['version']?> · <?=ojv_e($r['autor'])?> · <?=ojv_e($r['creado_en'])?> · <a href="visita_reporte.php?id=<?=$id?>&version=<?=(int)$r['version']?>&pdf=1" target="_blank" rel="noopener">PDF interno</a> · <a href="visita_reporte.php?id=<?=$id?>&version=<?=(int)$r['version']?>&pdf=1&cliente=1" target="_blank" rel="noopener">PDF para cliente</a></p><?php endforeach;?></details><?php endif;?>
<script id="visita-state" type="application/json"><?=ojv_json($form)?></script><script id="visita-schema" type="application/json"><?=ojv_json($schema)?></script><script src="assets/visitas.js?v=1.1-cables" defer></script>
<?php endif;?></main></body></html>
