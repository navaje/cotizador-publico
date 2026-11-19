<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/app/facturacion.php';ojf_admin();date_default_timezone_set('America/Mexico_City');header('Cache-Control: no-store');
$error='';$notice='';$doc=null;$items=[];$invoice=null;$root=null;$search='';$docs=[];$history=[];
$direct=($_POST['modo']??'')==='directa' || isset($_GET['nueva']) || isset($_GET['factura']);
$docId=(int)($_POST['documento_id']??$_GET['id']??0);$invoiceId=(int)($_POST['factura_id']??$_GET['factura']??0);
try {
 ojf_schema($pdo);
 if(($_GET['descarga']??'')==='xml'){
  $q=$pdo->prepare('SELECT xml,uuid FROM oj_facturas_prueba WHERE id=? AND estado=?');$q->execute([(int)($_GET['factura_id']??0),'timbrada']);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r || !$r['xml']){http_response_code(404);exit('XML no disponible.');}
  header('Content-Type: application/xml; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="PRUEBA-'.$r['uuid'].'.xml"');echo $r['xml'];exit;
 }
 if($direct && $invoiceId){
  $q=$pdo->prepare('SELECT * FROM oj_facturas_prueba WHERE id=? AND raiz_id IS NULL AND documento_id IS NULL');$q->execute([$invoiceId]);$invoice=$q->fetch(PDO::FETCH_ASSOC);
  if(!$invoice)throw new InvalidArgumentException('Factura directa no encontrada.');
 }elseif(!$direct && $docId){
  $chain=ojr_cadena($pdo,$docId);$doc=$chain[0];$root=(int)end($chain)['id'];$q=$pdo->prepare('SELECT concepto,cantidad,precio,importe FROM cotizacion_items WHERE cotizacion_id=? ORDER BY id');$q->execute([$docId]);$items=$q->fetchAll(PDO::FETCH_ASSOC);
  $q=$pdo->prepare('SELECT * FROM oj_facturas_prueba WHERE raiz_id=?');$q->execute([$root]);$invoice=$q->fetch(PDO::FETCH_ASSOC);
 }
 if($_SERVER['REQUEST_METHOD']==='POST'){
  require_post_csrf();if(!$direct && !$doc)throw new InvalidArgumentException('Selecciona un documento.');$action=ojr_text($_POST,'accion',20,true);
  if($action==='preparar'){
   if($direct)[$doc,$items]=ojf_direct_document($_POST);
   $payload=ojf_payload($doc,$items,$_POST);$pdo->beginTransaction();
   $q=$pdo->prepare($direct?'SELECT * FROM oj_facturas_prueba WHERE id=? AND raiz_id IS NULL AND documento_id IS NULL FOR UPDATE':'SELECT * FROM oj_facturas_prueba WHERE raiz_id=? FOR UPDATE');$q->execute([$direct?$invoiceId:$root]);$existing=$q->fetch(PDO::FETCH_ASSOC);
   if($direct && $invoiceId && !$existing)throw new InvalidArgumentException('Factura directa no encontrada.');
   $editable=$existing && ($existing['estado']==='borrador' || ($existing['estado']==='rechazada' && ojf_rejection(json_decode($existing['respuesta']??'null',true))!==''));
   if($existing && !$editable)throw new InvalidArgumentException('Este trabajo ya tiene un envío de prueba. Revisa su resultado; no se enviará otra factura.');
   if($existing){$q=$pdo->prepare('UPDATE oj_facturas_prueba SET documento_id=?,solicitud=?,source_hash=?,estado=? WHERE id=?');$q->execute([$direct?null:$docId,json_encode($payload,JSON_THROW_ON_ERROR),($direct?hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)):ojf_source_hash($doc,$items)),'borrador',$existing['id']]);$invoiceId=(int)$existing['id'];}
   else{$q=$pdo->prepare('INSERT INTO oj_facturas_prueba (raiz_id,documento_id,estado,solicitud,source_hash,creado_por,creado_en) VALUES (?,?,?,?,?,?,?)');$q->execute([$direct?null:$root,$direct?null:$docId,'borrador',json_encode($payload,JSON_THROW_ON_ERROR),($direct?hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)):ojf_source_hash($doc,$items)),(int)$_SESSION['usuario_id'],date('Y-m-d H:i:s')]);$invoiceId=(int)$pdo->lastInsertId();}
   $pdo->commit();$notice='Borrador preparado. Revisa todos los datos antes de timbrar.';
  }elseif($action==='timbrar'){
   if(($_POST['confirmar']??'')!=='1')throw new InvalidArgumentException('Confirma el timbrado de prueba.');
   if(!class_exists('DOMDocument') || !function_exists('curl_init'))throw new RuntimeException('El servidor necesita las extensiones PHP DOM y cURL.');
   if(empty(ojf_config()['api_key']))throw new RuntimeException('Configura primero la conexión.');
   $pdo->beginTransaction();$q=$pdo->prepare($direct?'SELECT * FROM oj_facturas_prueba WHERE id=? AND raiz_id IS NULL AND documento_id IS NULL FOR UPDATE':'SELECT * FROM oj_facturas_prueba WHERE raiz_id=? FOR UPDATE');$q->execute([$direct?$invoiceId:$root]);$inv=$q->fetch(PDO::FETCH_ASSOC);
   if(!$inv || $inv['estado']!=='borrador' || (!$direct && (int)$inv['documento_id']!==$docId))throw new InvalidArgumentException('No hay un borrador disponible para este documento.');
   if($direct && !hash_equals($inv['source_hash'],ojr_text($_POST,'revision',64,true)))throw new InvalidArgumentException('El borrador cambió. Abre nuevamente la factura y revisa los importes antes de emitir.');
   $invoiceId=(int)$inv['id'];$payload=json_decode($inv['solicitud'],true,32,JSON_THROW_ON_ERROR);
   if(!$direct){
    if(!hash_equals($inv['source_hash'],($direct?hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)):ojf_source_hash($doc,$items))))throw new InvalidArgumentException('Los conceptos o importes del documento cambiaron. Vuelve a preparar el borrador.');
    $q=$pdo->prepare('SELECT subtotal,iva,total FROM cotizaciones WHERE id=?');$q->execute([$docId]);$current=$q->fetch(PDO::FETCH_ASSOC);
    if(ojr_centavos($current['total'])!==ojr_centavos($payload['Total']) || ojr_centavos($current['subtotal'])!==ojr_centavos($payload['SubTotal']) || ojr_centavos($current['iva'])!==ojr_centavos($payload['Impuestos']['TotalImpuestosTrasladados']))throw new InvalidArgumentException('El documento cambió. Vuelve a preparar el borrador.');
   }
   $payload['Folio']=(string)$inv['id'];$q=$pdo->prepare('UPDATE oj_facturas_prueba SET estado=?,solicitud=?,respuesta=NULL,enviado_en=? WHERE id=?');$q->execute(['enviando',json_encode($payload,JSON_THROW_ON_ERROR),date('Y-m-d H:i:s'),$inv['id']]);$pdo->commit();
   // El estado se guarda antes del POST: una interrupción nunca autoriza reenvío.
   $response=null;
   try {
    $response=ojf_request('invoice/create',$payload);
    $q=$pdo->prepare('UPDATE oj_facturas_prueba SET respuesta=?,estado=? WHERE id=?');$q->execute([json_encode($response,JSON_THROW_ON_ERROR),'revisar',$inv['id']]);
    if($response['http']!==200)throw new RuntimeException('El proveedor no confirmó el timbrado.');
    $result=ojf_result($response['data'],$payload);
    $q=$pdo->prepare('UPDATE oj_facturas_prueba SET estado=?,uuid=?,xml=?,pdf_url=? WHERE id=?');$q->execute(['timbrada',$result['uuid'],$result['xml'],$result['pdf'],$inv['id']]);$notice='Factura de prueba timbrada. No tiene validez fiscal.';
   }catch(Throwable $e){
    $rejection=ojf_rejection($response);$state=$rejection!==''?'rechazada':'revisar';
    $q=$pdo->prepare('UPDATE oj_facturas_prueba SET estado=? WHERE id=? AND estado<>?');$q->execute([$state,$inv['id'],'timbrada']);
    if($rejection!=='')$error='Rechazo del proveedor: '.$rejection;
    else $error='El envío requiere revisión en el portal de pruebas de Factura Digital. No repitas la emisión: podría haberse timbrado.';
   }
  }else throw new InvalidArgumentException('Acción inválida.');
 }
 if($direct && $invoiceId){$q=$pdo->prepare('SELECT * FROM oj_facturas_prueba WHERE id=? AND raiz_id IS NULL AND documento_id IS NULL');$q->execute([$invoiceId]);$invoice=$q->fetch(PDO::FETCH_ASSOC);}
 elseif($doc){$q=$pdo->prepare('SELECT * FROM oj_facturas_prueba WHERE raiz_id=?');$q->execute([$root]);$invoice=$q->fetch(PDO::FETCH_ASSOC);}
 $search=ojr_text($_GET,'q',100);$q=$pdo->prepare('SELECT id,folio,cliente_nombre,total FROM cotizaciones WHERE folio LIKE ? OR cliente_nombre LIKE ? ORDER BY id DESC LIMIT 30');$q->execute(['%'.$search.'%','%'.$search.'%']);$docs=$q->fetchAll(PDO::FETCH_ASSOC);
 $history=$pdo->query('SELECT id,documento_id,estado,solicitud,creado_en FROM oj_facturas_prueba ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e instanceof PDOException?'No se pudo consultar o guardar la factura. Revisa la instalación.':$e->getMessage();}
$preview=$invoice?json_decode($invoice['solicitud'],true):null;
$canEdit=!$invoice || $invoice['estado']==='borrador' || ($invoice['estado']==='rechazada' && ojf_rejection(json_decode($invoice['respuesta']??'null',true))!=='');
$form=$_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='preparar'?$_POST:[];
if(!$form && $preview){
 $receiver=$preview['Receptor'];$form=['rfc'=>$receiver['Rfc'],'nombre'=>$receiver['Nombre'],'cp'=>$receiver['DomicilioFiscalReceptor'],'regimen'=>$receiver['RegimenFiscalReceptor'],'uso'=>$receiver['UsoCFDI'],'metodo'=>$preview['MetodoPago'],'forma'=>$preview['FormaPago']];
 foreach($preview['Conceptos'] as $c){$form['concepto'][]=$c['Descripcion'];$form['cantidad'][]=$c['Cantidad'];$form['precio'][]=$c['ValorUnitario'];$form['clave'][]=$c['ClaveProdServ'];$form['unidad'][]=$c['ClaveUnidad'];}
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Facturas de prueba — Ojo Jarocho</title><link rel="stylesheet" href="assets/bancos.css"><script src="assets/facturas.js" defer></script></head><body><main><nav><a href="dashboard.php">Panel</a> · <a href="factura_conexion.php">Conexión</a> · <a href="facturas.php">Facturas</a></nav><h1>Facturación — entorno de pruebas</h1><p>CFDI de ingreso de prueba, sin validez fiscal. Los recibos y movimientos Banorte mantienen su funcionamiento.</p>
<?php if($error):?><p class="error"><?=ojr_e($error)?></p><?php endif;?><?php if($notice):?><p><?=ojr_e($notice)?></p><?php endif;?>
<?php if(!$doc && !$direct):?><p><a href="facturas.php?nueva=1">Nueva factura directa</a> · Selecciona un documento para facturar sus conceptos.</p><form method="get"><label>Buscar folio o cliente<input name="q" value="<?=ojr_e($search)?>" maxlength="100"></label><button>Buscar</button></form><table><thead><tr><th>Documento</th><th>Cliente/contacto</th><th>Total</th></tr></thead><tbody><?php foreach($docs as $d):?><tr><td><a href="facturas.php?id=<?=(int)$d['id']?>"><?=ojr_e($d['folio'])?></a></td><td><?=ojr_e($d['cliente_nombre'])?></td><td><?=ojr_e($d['total'])?></td></tr><?php endforeach;?></tbody></table>
<h2>Facturas recientes</h2><table><thead><tr><th>Folio de prueba</th><th>Receptor fiscal</th><th>Total</th><th>Estado</th></tr></thead><tbody><?php foreach($history as $h):$hp=json_decode($h['solicitud'],true);?><tr><td><a href="facturas.php?<?=$h['documento_id']?'id='.(int)$h['documento_id']:'factura='.(int)$h['id']?>">OJPRUEBA-<?=(int)$h['id']?></a></td><td><?=ojr_e($hp['Receptor']['Nombre']??'')?></td><td><?=ojr_e($hp['Total']??'')?></td><td><?=ojr_e($h['estado'])?></td></tr><?php endforeach;?></tbody></table>
<?php else:?><h2><?=$direct?'Factura directa'.($invoice?' · OJPRUEBA-'.(int)$invoice['id']:''):ojr_e($doc['folio'].' · '.$doc['cliente_nombre'])?></h2>
<?php if($direct):?><p>Captura una factura sin crear una cotización o nota adicional.</p><?php else:?><p>Cliente/contacto del trabajo: <?=ojr_e($doc['cliente_nombre'])?>. El receptor fiscal se captura por separado.</p><p>Total del documento: <?=ojr_e($doc['total'])?> MXN.</p><?php endif;?>
<?php if($invoice):?><section><h2>Estado: <?=ojr_e($invoice['estado'])?></h2><p>Receptor: <?=ojr_e(($preview['Receptor']['Nombre']??'').' · '.($preview['Receptor']['Rfc']??''))?></p><p>CP: <?=ojr_e($preview['Receptor']['DomicilioFiscalReceptor']??'')?> · Régimen: <?=ojr_e($preview['Receptor']['RegimenFiscalReceptor']??'')?> · Uso: <?=ojr_e($preview['Receptor']['UsoCFDI']??'')?></p><p>Método: <?=ojr_e($preview['MetodoPago']??'')?> · Forma: <?=ojr_e($preview['FormaPago']??'')?> · Subtotal: <?=ojr_e($preview['SubTotal']??'')?> · IVA: <?=ojr_e($preview['Impuestos']['TotalImpuestosTrasladados']??'')?> · Total: <?=ojr_e($preview['Total']??'')?> MXN</p><table><tr><th>Concepto</th><th>Clave SAT / unidad</th><th>Cantidad</th><th>Precio / importe</th></tr><?php foreach($preview['Conceptos']??[] as $c):?><tr><td><?=ojr_e($c['Descripcion'])?></td><td><?=ojr_e($c['ClaveProdServ'].' / '.$c['ClaveUnidad'])?></td><td><?=ojr_e($c['Cantidad'])?></td><td><?=ojr_e($c['ValorUnitario'].' / '.$c['Importe'])?></td></tr><?php endforeach;?></table>
<?php if($invoice['estado']==='borrador' && ($direct || (int)$invoice['documento_id']===$docId)):?><form method="post"><?=csrf_field()?><input type="hidden" name="modo" value="<?=$direct?'directa':'documento'?>"><input type="hidden" name="factura_id" value="<?=(int)$invoice['id']?>"><input type="hidden" name="documento_id" value="<?=$docId?>"><input type="hidden" name="accion" value="timbrar"><input type="hidden" name="revision" value="<?=ojr_e($invoice['source_hash'])?>"><label><input type="checkbox" name="confirmar" value="1" required> Revisé los datos fiscales, claves, impuestos e importes. Confirmo emitir una factura de prueba.</label><button>Timbrar factura de prueba</button></form><?php elseif($invoice['estado']==='timbrada'):?><p>UUID: <?=ojr_e($invoice['uuid'])?></p><a href="facturas.php?descarga=xml&amp;factura_id=<?=(int)$invoice['id']?>">Descargar XML de prueba</a><?php if($invoice['pdf_url']):?> · <a href="<?=ojr_e($invoice['pdf_url'])?>" target="_blank" rel="noopener">Abrir PDF de prueba</a><?php else:?><p>Descarga el PDF desde el portal del proveedor.</p><?php endif;?><?php elseif($invoice['estado']==='rechazada'):?><p class="error"><?=ojr_e(ojf_rejection(json_decode($invoice['respuesta']??'null',true)))?></p><p>Corrige los datos y prepara nuevamente el borrador antes de emitir.</p><?php elseif($invoice['estado']!=='borrador'):?><p>Revisa el resultado en el portal del proveedor. El reenvío está bloqueado para evitar duplicados.</p><?php endif;?></section><?php endif;?>
<?php if($canEdit): require __DIR__.'/app/factura_form.php'; endif;?><?php endif;?></main></body></html>
