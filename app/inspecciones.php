<?php
require_once __DIR__.'/inspecciones_schema.php';
function ojc_e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function ojc_json($v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); }
function ojc_text(array $a,string $k,int $max,bool $required=false): string {
    if (isset($a[$k]) && !is_scalar($a[$k])) throw new InvalidArgumentException('Formato inválido en '.$k.'.');
    $s=trim((string)($a[$k]??''));
    if (($required&&$s==='') || mb_strlen($s,'UTF-8')>$max) throw new InvalidArgumentException('Revisa '.$k.' (máximo '.$max.' caracteres).');
    return $s;
}
function ojc_enum(array $a,string $k,array $valid,string $default): string {
    $v=ojc_text($a,$k,40);if($v==='') $v=$default;
    if(!in_array($v,$valid,true)) throw new InvalidArgumentException('Opción inválida en '.$k.'.');return $v;
}
function ojc_date(string $v,bool $required=false): string {
    if($v===''&&!$required)return '';
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);
    if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Fecha inválida.');return $v;
}
function ojc_pairs($raw,array $checks): array {
    if(!is_array($raw))$raw=[];$out=[];
    foreach($checks as $key=>$label){$r=$raw[$key]??[];if(!is_array($r))$r=[];
        $out[$key]=['inicial'=>ojc_enum($r,'inicial',array_keys(ojc_statuses()),'pendiente'),'final'=>ojc_enum($r,'final',array_keys(ojc_statuses()),'pendiente')];
    }return $out;
}
function ojc_array(array $data,string $key,int $max): array {
    $v=$data[$key]??[];if(!is_array($v)||count($v)>$max)throw new InvalidArgumentException('Revisa '.$key.': máximo '.$max.' registros.');
    foreach($v as $r)if(!is_array($r))throw new InvalidArgumentException('Registro inválido en '.$key.'.');return array_values($v);
}
function ojc_normalize(array $raw): array {
    $schema=ojc_schema();$out=[];
    foreach(['fecha'=>20,'tecnico'=>160,'motivo_visita'=>1000,'inventario_notas'=>1000,'trabajos'=>2000,'materiales'=>1500,'recomendaciones'=>1500,'receptor'=>160,'motivo_sin_firma'=>500] as $k=>$max)$out[$k]=ojc_text($raw,$k,$max);
    $out['fecha']=ojc_date($out['fecha'],true);
    $out['sistema']=ojc_enum($raw,'sistema',['analogico','ip','hibrido'],'ip');
    $out['visita']=ojc_enum($raw,'visita',['inspeccion','preventivo','falla'],'inspeccion');
    $out['resultado']=ojc_enum($raw,'resultado',['pendiente','operativo','con_pendientes','critico'],'pendiente');
    $out['recepcion']=ojc_enum($raw,'recepcion',['pendiente','firmado'],'pendiente');
    $out['recepcion_confirmada']=($raw['recepcion_confirmada']??false)===true;
    $out['proxima_revision']=ojc_date(ojc_text($raw,'proxima_revision',20));
    foreach(['instaladas','operativas','dias_requeridos','dias_disponibles'] as $k){
        $v=ojc_text($raw,$k,5);if($v===''||!ctype_digit($v)||(int)$v>10000)throw new InvalidArgumentException('Indica un número válido en '.$k.'.');$out[$k]=(int)$v;
    }
    if($out['instaladas']>64||$out['operativas']>$out['instaladas'])throw new InvalidArgumentException('Revisa las cámaras instaladas y operativas (máximo 64 fichas).');
    $out['inventario']=[];
    foreach(ojc_array($raw,'inventario',50) as $row){$r=[];foreach(['tipo'=>100,'marca'=>100,'modelo'=>160,'ubicacion'=>200] as $k=>$max)$r[$k]=ojc_text($row,$k,$max);
        $r['estado']=ojc_enum($row,'estado',['pendiente','operativo','atencion','falla'],'pendiente');$out['inventario'][]=$r;}
    $out['camaras']=[];$seen=[];
    foreach(ojc_array($raw,'camaras',64) as $row){
        $r=['uid'=>ojc_text($row,'uid',40,true)];
        if(!preg_match('/^cam-[a-f0-9]{12}$/D',$r['uid'])||isset($seen[$r['uid']]))throw new InvalidArgumentException('Identificador de cámara inválido o repetido.');$seen[$r['uid']]=true;
        foreach(['numero'=>30,'canal'=>30,'ubicacion'=>200,'marca_modelo'=>200,'observacion_inicial'=>1000,'mantenimiento'=>1500,'observacion_final'=>1000] as $k=>$max)$r[$k]=ojc_text($row,$k,$max);
        $r['checks']=ojc_pairs($row['checks']??[],$schema['camera']);$out['camaras'][]=$r;
    }
    $out['grupos']=[];
    foreach($schema['groups'] as $key=>$g){$row=$raw['grupos'][$key]??[];if(!is_array($row))$row=[];
        $out['grupos'][$key]=['checks'=>ojc_pairs($row['checks']??[],$g['checks']),'observacion_inicial'=>ojc_text($row,'observacion_inicial',1200),'accion'=>ojc_text($row,'accion',1500),'observacion_final'=>ojc_text($row,'observacion_final',1200)];}
    $out['hallazgos']=[];
    foreach(ojc_array($raw,'hallazgos',80) as $row){$r=[];
        foreach(['equipo'=>200,'hallazgo'=>1500,'accion'=>1500,'responsable'=>160] as $k=>$max)$r[$k]=ojc_text($row,$k,$max);
        $r['prioridad']=ojc_enum($row,'prioridad',['critica','alta','media','baja'],'media');
        $r['estado']=ojc_enum($row,'estado',['abierto','en_proceso','corregido'],'abierto');
        $r['fecha_objetivo']=ojc_date(ojc_text($row,'fecha_objetivo',20));$out['hallazgos'][]=$r;
    }
    $out['firma']=ojc_text($raw,'firma',350000);
    if($out['firma']!=='')ojc_signature($out['firma']);
    return $out;
}
function ojc_image(string $bytes,int $maxBytes=2097152): array {
    if(strlen($bytes)>$maxBytes||strlen($bytes)<30)throw new InvalidArgumentException('La imagen supera el tamaño permitido o no es válida.');
    $info=@getimagesizefromstring($bytes);
    if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true)||$info[0]>4096||$info[1]>4096||$info[0]*$info[1]>8000000)throw new InvalidArgumentException('Usa una imagen JPG/PNG de hasta 2 MB y 4096 píxeles por lado.');
    return ['mime'=>$info[2]===IMAGETYPE_JPEG?'image/jpeg':'image/png','extension'=>$info[2]===IMAGETYPE_JPEG?'jpg':'png'];
}
function ojc_signature(string $uri): void {
    if(!preg_match('#^data:image/(jpeg|png);base64,([A-Za-z0-9+/=]+)$#D',$uri,$m))throw new InvalidArgumentException('Firma inválida.');
    $bytes=base64_decode($m[2],true);if($bytes===false)throw new InvalidArgumentException('Firma inválida.');
    $info=ojc_image($bytes,250000);
    if($info['mime']!=='image/'.$m[1])throw new InvalidArgumentException('Formato de firma inválido.');
    $size=getimagesizefromstring($bytes);if($size[0]>1600||$size[1]>800)throw new InvalidArgumentException('Firma demasiado grande.');
}
function ojc_require_final(array $d): void {
    if($d['tecnico']===''||$d['motivo_visita']===''||$d['resultado']==='pendiente'||$d['fecha']>date('Y-m-d'))throw new InvalidArgumentException('Completa técnico, motivo, fecha de visita y resultado general antes de cerrar.');
    if(!$d['camaras']||count($d['camaras'])!==$d['instaladas'])throw new InvalidArgumentException('Debe existir una ficha por cada cámara instalada antes de cerrar.');
    if(!$d['inventario'])throw new InvalidArgumentException('Registra el equipo principal en el inventario.');
    foreach($d['inventario'] as $r)if($r['tipo']===''||$r['ubicacion']===''||$r['estado']==='pendiente')throw new InvalidArgumentException('Completa tipo, ubicación y estado de cada equipo del inventario.');
    $finalProblems=0;$allProblems=0;
    foreach($d['inventario'] as $eq)if(in_array($eq['estado'],['atencion','falla'],true)){$allProblems++;$finalProblems++;}
    if($d['operativas']<$d['instaladas']){$allProblems++;$finalProblems++;}
    $schema=ojc_schema();$missing=[];$blocks=[];
    foreach($d['camaras'] as $c){
        if($c['numero']===''||$c['ubicacion']==='')throw new InvalidArgumentException('Completa número y ubicación de cada cámara.');
        $blocks[]=$c['checks'];
        foreach($c['checks'] as $key=>$pair)foreach(['inicial','final'] as $stage)if($pair[$stage]==='pendiente')$missing[]='Cámara '.$c['numero'].' ('.$c['ubicacion'].') / '.$schema['camera'][$key].' / '.ucfirst($stage);
    }
    foreach($d['grupos'] as $key=>$g){
        $blocks[]=$g['checks'];
        foreach($g['checks'] as $check=>$pair)foreach(['inicial','final'] as $stage)if($pair[$stage]==='pendiente')$missing[]=$schema['groups'][$key]['label'].' / '.$schema['groups'][$key]['checks'][$check].' / '.ucfirst($stage);
    }
    if($missing)throw new InvalidArgumentException('Faltan '.count($missing).' resultados por revisar. Puntos pendientes: '.implode('; ',array_slice($missing,0,8)).(count($missing)>8?'... Completa estos y vuelve a intentar para ver los siguientes.':'. Selecciona el resultado real o No aplica si la función no existe en este equipo.'));
    foreach($blocks as $checks)foreach($checks as $pair)foreach(['inicial','final'] as $stage){
        if(in_array($pair[$stage],['atencion','no_cumple'],true)){$allProblems++;if($stage==='final')$finalProblems++;}
    }
    if($d['dias_disponibles']<$d['dias_requeridos']&&$d['grupos']['grabador']['checks']['retencion']['final']==='cumple')throw new InvalidArgumentException('Los días disponibles no alcanzan los requeridos: la retención no puede marcarse Cumple al cierre.');
    if($allProblems&&!$d['hallazgos'])throw new InvalidArgumentException('Documenta los puntos que requieren atención en Hallazgos.');
    $open=0;$critical=0;
    foreach($d['hallazgos'] as $h){
        if($h['equipo']===''||$h['hallazgo']===''||$h['accion']==='')throw new InvalidArgumentException('Completa equipo, hallazgo y acción en cada registro.');
        if($h['estado']!=='corregido'){$open++;if($h['responsable']===''||$h['fecha_objetivo']==='')throw new InvalidArgumentException('Los hallazgos pendientes requieren responsable y fecha objetivo.');if($h['prioridad']==='critica')$critical++;}
    }
    if($finalProblems&&!$open)throw new InvalidArgumentException('Los puntos con problemas al cierre deben tener al menos un hallazgo pendiente.');
    if(($finalProblems||$open||$d['operativas']<$d['instaladas'])&&$d['resultado']==='operativo')throw new InvalidArgumentException('El resultado general debe indicar los pendientes o fallas existentes.');
    if($critical&&$d['resultado']!=='critico')throw new InvalidArgumentException('Hay un hallazgo crítico sin resolver: selecciona Con fallas críticas.');
    if($d['recepcion']==='firmado'){
        if($d['receptor']===''||$d['firma']===''||!$d['recepcion_confirmada'])throw new InvalidArgumentException('Para registrar recepción firmada, completa nombre, firma y confirmación de recepción.');
    }elseif($d['motivo_sin_firma']==='')throw new InvalidArgumentException('Indica por qué queda pendiente la firma de recepción del reporte.');
}
function ojc_documents(PDO $pdo,int $id): array {
    $out=[];$seen=[];$q=$pdo->prepare('SELECT * FROM cotizaciones WHERE id = ?');
    while($id>0){if(isset($seen[$id])||count($out)>=50)throw new RuntimeException('Revisa la relación entre documentos.');$seen[$id]=true;$q->execute([$id]);$doc=$q->fetch(PDO::FETCH_ASSOC);if(!$doc)throw new RuntimeException('No se encontró la nota de servicio o su documento de origen.');$out[]=$doc;$id=(int)($doc['documento_origen_id']??0);}
    return $out;
}
function ojc_base(PDO $pdo,int $docId): array {
    $chain=ojc_documents($pdo,$docId);$doc=$chain[0]??null;
    if(!$doc||($doc['tipo_documento']??'')!=='nota_servicio')throw new InvalidArgumentException('La checklist se inicia desde una Nota de servicio. Convierte la cotización a Nota de servicio desde el listado.');
    $references=[];foreach(array_reverse($chain) as $r)$references[]=['id'=>(int)$r['id'],'folio'=>$r['folio'],'tipo'=>$r['tipo_documento'],'archivo'=>$r['folio'].'.pdf'];
    return ['documento_id'=>$docId,'raiz_id'=>(int)end($chain)['id'],'documentos'=>$references,
        'empresa'=>['nombre'=>$doc['empresa_nombre'],'direccion'=>$doc['empresa_direccion'],'telefono'=>$doc['empresa_telefono'],'email'=>$doc['empresa_email'],'web'=>$doc['empresa_web']],
        'cliente'=>['nombre'=>$doc['cliente_nombre'],'direccion'=>$doc['cliente_direccion'],'telefono'=>$doc['cliente_telefono'],'email'=>$doc['cliente_email']], 'proyecto'=>$doc['proyecto']];
}
function ojc_load(PDO $pdo,int $id,int $version=0): array {
    $q=$pdo->prepare('SELECT * FROM oj_inspecciones WHERE id = ?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new InvalidArgumentException('Inspección no encontrada.');
    $v=$version>0?$version:(int)$r['version'];
    $q=$pdo->prepare('SELECT * FROM oj_inspeccion_versiones WHERE inspeccion_id = ? AND version = ?');$q->execute([$id,$v]);$rev=$q->fetch(PDO::FETCH_ASSOC);
    if(!$rev)throw new InvalidArgumentException('Versión de inspección no encontrada.');
    return ['registro'=>$r,'revision'=>$rev,'datos'=>json_decode($rev['datos'],true,512,JSON_THROW_ON_ERROR)];
}
function ojc_engine(PDO $pdo): void {
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')return;
    $q=$pdo->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('cotizaciones','oj_inspecciones','oj_inspeccion_versiones')");$engines=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach(['cotizaciones','oj_inspecciones','oj_inspeccion_versiones'] as $table)if(strcasecmp((string)($engines[$table]??''),'InnoDB')!==0)throw new RuntimeException('Verifica en phpMyAdmin que las tablas de inspecciones y cotizaciones existan y usen InnoDB.');
}
function ojc_uploads(array $files,array $post,array $existing,array &$written): array {
    $photos=$existing;
    $names=$files['foto']['name']??[];
    if(!is_array($names)||count($names)>3)throw new InvalidArgumentException('Adjunta como máximo tres fotos por guardado.');
    foreach($names as $i=>$name){
        $error=$files['foto']['error'][$i]??UPLOAD_ERR_NO_FILE;if($error===UPLOAD_ERR_NO_FILE)continue;
        if($error!==UPLOAD_ERR_OK)throw new InvalidArgumentException('No se pudo recibir una foto. Revisa su tamaño y vuelve a adjuntarla.');
        if(count($photos)>=12)throw new InvalidArgumentException('Máximo 12 fotografías por reporte.');
        $tmp=$files['foto']['tmp_name'][$i]??'';
        if(!is_string($tmp)||!is_uploaded_file($tmp))throw new InvalidArgumentException('La carga de imagen no es válida.');
        if(filesize($tmp)>2097152)throw new InvalidArgumentException('Cada foto debe pesar hasta 2 MB.');
        $bytes=file_get_contents($tmp);$info=ojc_image($bytes);
        $meta=['ubicacion'=>$post['foto_ubicacion'][$i]??'','descripcion'=>$post['foto_descripcion'][$i]??'','etapa'=>$post['foto_etapa'][$i]??'inicial'];
        $meta=['ubicacion'=>ojc_text($meta,'ubicacion',200,true),'descripcion'=>ojc_text($meta,'descripcion',500,true),'etapa'=>ojc_enum($meta,'etapa',['inicial','final'],'inicial')];
        $filename=bin2hex(random_bytes(16)).'.'.$info['extension'];$dir=__DIR__.'/../storage/inspecciones';
        if(!is_dir($dir)||!is_file($dir.'/.htaccess')||!is_writable($dir))throw new RuntimeException('La carpeta storage/inspecciones debe existir, conservar .htaccess y permitir escritura.');
        if(file_put_contents($dir.'/'.$filename,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('No se pudo guardar la fotografía.');
        $written[]=$dir.'/'.$filename;$photos[]=array_merge($meta,['archivo'=>$filename,'mime'=>$info['mime']]);
    }
    return $photos;
}
function ojc_save(PDO $pdo,int $id,int $docId,int $expected,array $raw,array $input,array $files,array $session,bool $final): int {
    ojc_engine($pdo);
    $token=ojc_text($input,'solicitud_token',64,true);$target=$id>0?'i:'.$id:'d:'.$docId;
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)||($session['ojc_tokens'][$token]??'')!==$target)throw new InvalidArgumentException('El formulario venció. Recarga la página.');
    $find=$pdo->prepare('SELECT inspeccion_id FROM oj_inspeccion_versiones WHERE solicitud_token = ?');$find->execute([$token]);$old=$find->fetchColumn();if($old)return(int)$old;
    $data=ojc_normalize($raw);
    if($final)ojc_require_final($data);
    $pdo->beginTransaction();$written=[];
    try{
        $find->execute([$token]);$old=$find->fetchColumn();if($old){$pdo->commit();return(int)$old;}
        if($id>0){
            $current=ojc_load($pdo,$id);$r=$current['registro'];
            if($r['estado']==='cerrado')throw new InvalidArgumentException('Esta inspección está cerrada. Crea una nueva inspección para otra visita.');
            if((int)$r['version']!==$expected)throw new InvalidArgumentException('Otra sesión guardó cambios. Abre la versión actual antes de continuar; no se sobrescribió su trabajo.');
            $base=$current['datos']['base'];$photos=$current['datos']['fotos'];
            $q=$pdo->prepare('UPDATE oj_inspecciones SET version = version + 1, estado = ?, actualizado_en = ? WHERE id = ? AND version = ? AND estado = ?');
            $q->execute([$final?'cerrado':'borrador',date('Y-m-d H:i:s'),$id,$expected,'borrador']);
            if($q->rowCount()!==1)throw new InvalidArgumentException('La inspección cambió en otra sesión. Recarga la página; no se sobrescribieron sus cambios.');
            $version=$expected+1;
        }else{
            $base=ojc_base($pdo,$docId);$photos=[];$version=1;
            // Coordinar el alta con la protección de eliminación de documentos.
            $lock=$pdo->prepare('SELECT id FROM cotizaciones WHERE id = ? FOR UPDATE');$lock->execute([$base['raiz_id']]);
            if(!$lock->fetchColumn())throw new InvalidArgumentException('El documento de origen ya no está disponible.');
            $lock->execute([$docId]);if(!$lock->fetchColumn())throw new InvalidArgumentException('La nota de servicio ya no está disponible.');
            $q=$pdo->prepare('INSERT INTO oj_inspecciones (documento_id,raiz_id,version,estado,creado_en,actualizado_en) VALUES (?,?,?,?,?,?)');
            $q->execute([$docId,$base['raiz_id'],1,$final?'cerrado':'borrador',date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);$id=(int)$pdo->lastInsertId();
            $q=$pdo->prepare('UPDATE oj_inspecciones SET folio = ? WHERE id = ?');$q->execute(['IM-'.str_pad((string)$id,6,'0',STR_PAD_LEFT),$id]);
        }
        // El cliente solo puede quitar referencias de fotos actuales, nunca inventar archivos.
        $remove=$input['quitar_fotos']??[];if(!is_array($remove))throw new InvalidArgumentException('Selección de fotos inválida.');
        $photos=array_values(array_filter($photos,fn($p)=>!in_array($p['archivo'],$remove,true)));
        $photos=ojc_uploads($files,$input,$photos,$written);
        if($data['recepcion']!=='firmado'){$data['firma']='';$data['recepcion_confirmada']=false;}
        $payload=['base'=>$base,'form'=>$data,'fotos'=>$photos,'estado'=>$final?'cerrado':'borrador','version'=>$version];
        $q=$pdo->prepare('INSERT INTO oj_inspeccion_versiones (inspeccion_id,version,solicitud_token,datos,autor,creado_en) VALUES (?,?,?,?,?,?)');
        $q->execute([$id,$version,$token,ojc_json($payload),(string)($session['usuario_nombre']??'Técnico').' (#'.(int)$session['usuario_id'].')',date('Y-m-d H:i:s')]);
        $pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();foreach($written as $path)@unlink($path);throw $e;}
}
function ojc_photo_uri(array $photo): string {
    if(!preg_match('/^[a-f0-9]{32}\.(jpg|png)$/D',(string)($photo['archivo']??'')))return '';
    $path=__DIR__.'/../storage/inspecciones/'.$photo['archivo'];if(!is_file($path))return '';
    $bytes=file_get_contents($path);$info=ojc_image($bytes);return 'data:'.$info['mime'].';base64,'.base64_encode($bytes);
}
function ojc_html(array $loaded): string {
    $payload=$loaded['datos'];$d=$payload['form'];$b=$payload['base'];$r=$loaded['registro'];$rev=$loaded['revision'];
    ob_start();include __DIR__.'/inspeccion_template.php';return ob_get_clean();
}
