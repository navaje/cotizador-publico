<?php
function ojv_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ojv_json($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); }
function ojv_schema(): array { return json_decode(file_get_contents(__DIR__.'/visitas_campos.json'),true,512,JSON_THROW_ON_ERROR); }
function ojv_id($v, bool $empty=false): int {
    if ($empty && ($v==='' || $v===null || $v===0 || $v==='0')) return 0;
    $id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($id===false) throw new InvalidArgumentException('Identificador no válido.');
    return (int)$id;
}
function ojv_text($v,int $max=5000): string {
    if(!is_scalar($v) || strlen((string)$v)>$max) throw new InvalidArgumentException('Texto no válido o demasiado largo.');
    return trim((string)$v);
}
function ojv_empty(): array {
    $d=['cliente_id'=>'','fotos'=>[]];
    foreach(ojv_schema() as $k=>$s) {
        $d[$k]=[];
        if(isset($s['prefix'])) continue;
        foreach($s['fields'] as $f=>$v) $d[$k][$f]=isset($v[2])?$v[2][0]:'';
    }
    $d['general']['fecha']=date('Y-m-d'); return $d;
}
function ojv_validate(array $raw): array {
    $d=['cliente_id'=>ojv_id($raw['cliente_id']??null,true),'fotos'=>[]];
    foreach(ojv_schema() as $k=>$s) {
        $list=$raw[$k]??[];
        if(!is_array($list)) throw new InvalidArgumentException('Revisa la sección '.$s['title']);
        $repeat=isset($s['prefix']);
        if($repeat && (count($list)>$s['limit'] || array_values($list)!==$list)) throw new InvalidArgumentException('Demasiados registros o lista no válida.');
        $result=[]; $seen=[];
        foreach($repeat?$list:[$list] as $row) {
            if(!is_array($row)) throw new InvalidArgumentException('Registro no válido.');
            $out=[];
            foreach($s['fields'] as $f=>$spec) {
                $val=ojv_text($row[$f]??($spec[4]??(isset($spec[2])?$spec[2][0]:'')),($spec[1]??'')==='area'?6000:500);
                $type=$spec[1]??'text';
                if(isset($spec[2])&&!in_array($val,$spec[2],true)) throw new InvalidArgumentException('Opción no válida: '.$spec[0]);
                if(in_array($type,['num','int'],true)&&$val!=='') {
                    if(!preg_match('/^\d+(?:\.\d{1,2})?$/D',$val)||!is_finite((float)$val)||(float)$val>100000000||($type==='int'&&floor((float)$val)!=(float)$val)) throw new InvalidArgumentException('Revisa el número: '.$spec[0]);
                }
                if($type==='date'&&$val!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$val)||!($dt=DateTime::createFromFormat('!Y-m-d',$val))||$dt->format('Y-m-d')!==$val)) throw new InvalidArgumentException('Fecha no válida.');
                if($type==='email'&&$val!==''&&!filter_var($val,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Correo no válido.');
                $out[$f]=$val;
            }
            if($repeat) {
                if(!preg_match('/^'.preg_quote($s['prefix'],'/').'\-[0-9]{2,5}$/D',$out['id'])||isset($seen[$out['id']])) throw new InvalidArgumentException('Identificadores vacíos, repetidos o no válidos.');
                $seen[$out['id']]=true;$result[]=$out;
            } else $result=$out;
        }
        $d[$k]=$result;
    }
    foreach(['proyecto','cliente_nombre','tecnico'] as $f) if(strlen($d['general'][$f])>180) throw new InvalidArgumentException('Máximo 180 caracteres: '.$f);
    if(count($d['camaras'])+count($d['tramos'])+count($d['materiales'])>280) throw new InvalidArgumentException('Máximo 280 registros combinados por visita.');
    return $d;
}
function ojv_pending(array $d): array {
    $p=[];
    foreach(['cliente_nombre'=>'Nombre del cliente','proyecto'=>'Proyecto','fecha'=>'Fecha','cliente_direccion'=>'Dirección','necesidad'=>'Necesidad del cliente'] as $f=>$label) if(empty($d['general'][$f]))$p[]=$label;
    if(!count($d['camaras']))$p[]='Al menos un punto de cámara';
    $wired=false;
    foreach($d['camaras'] as $cam) {
        foreach(['ubicacion','objetivo','tecnologia','tipo','accion'] as $f) if(empty($cam[$f])||$cam[$f]==='Por definir')$p[]=$cam['id'].': '.$f.' pendiente';
        if($cam['altura']==='')$p[]=$cam['id'].': altura pendiente';
        if(in_array($cam['tecnologia'],['Wi-Fi','4G'],true)&&$cam['wifi_medida']!=='Medida')$p[]=$cam['id'].': prueba de señal pendiente';
        if(in_array($cam['tecnologia'],['Analógica','IP cableada / PoE'],true)&&$cam['accion']==='Instalar nuevo')$wired=true;
    }
    if($wired&&!count($d['tramos']))$p[]='Recorrido de cableado para puntos nuevos';
    foreach($d['tramos'] as $t) {
        foreach(['origen','destino'] as $f)if($t[$f]==='')$p[]=$t['id'].': '.$f.' pendiente';
        if($t['accion']==='Por definir')$p[]=$t['id'].': estado del cableado pendiente';
        if($t['accion']==='Instalar nuevo'&&((float)$t['metros']<=0||(int)$t['cables']<=0||$t['cable']==='Por definir'))$p[]=$t['id'].': longitud, número o tipo de cable pendientes';
        if(($t['presentacion']??'')==='Armado con conectores'&&$t['accion']==='Instalar nuevo'){
            if((float)($t['largo_armado']??0)<=0)$p[]=$t['id'].': indica la longitud de cada cable armado';
            elseif((float)$t['metros']+(float)$t['reserva']>(float)$t['largo_armado'])$p[]=$t['id'].': el recorrido más reserva supera el cable armado; selecciona otro largo o cable por metro (no unir cables automáticamente)';
        }
        if($t['canal_accion']==='Instalar nuevo'&&((float)$t['canal_metros']<=0||$t['instalacion']==='Por definir'))$p[]=$t['id'].': canalización pendiente';
    }
    foreach($d['materiales'] as $m)if($m['concepto']===''||(int)$m['cantidad']<=0)$p[]=$m['id'].': descripción o cantidad pendiente';
    if($d['cierre']['recomendacion']==='')$p[]='Recomendación técnica';
    if($d['cierre']['resueltos']!=='Resueltos / no hay pendientes')$p[]='Resolver o documentar los pendientes antes de marcar listo';
    return $p;
}
function ojv_load(PDO $pdo,int $id,int $user,int $version=0,bool $lock=false): array {
    $q=$pdo->prepare('SELECT * FROM oj_visitas WHERE id=? AND usuario_id=?'.($lock?' FOR UPDATE':''));$q->execute([$id,$user]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new InvalidArgumentException('Visita no disponible para tu usuario.');
    if($version){$q=$pdo->prepare('SELECT datos,version FROM oj_visita_versiones WHERE visita_id=? AND version=?');$q->execute([$id,$version]);$v=$q->fetch(PDO::FETCH_ASSOC);if(!$v)throw new InvalidArgumentException('Versión no disponible.');$r['datos']=$v['datos'];$r['version']=$v['version'];}
    $r['form']=json_decode($r['datos'],true,512,JSON_THROW_ON_ERROR);return $r;
}
function ojv_storage(): string {
    $p=__DIR__.'/visitas_fotos';
    if(!is_dir($p)&&!mkdir($p,0700,true)&&!is_dir($p))throw new RuntimeException('No se pudo crear la carpeta de evidencias.');
    return $p;
}
function ojv_save(PDO $pdo,int $id,int $user,int $version,array $raw,array $post,array $files,string $author,bool $ready): int {
    $d=ojv_validate($raw);$newFiles=[];
    if($ready&&($pending=ojv_pending($d)))throw new InvalidArgumentException('Falta completar: '.implode('; ',array_slice($pending,0,12)));
    if($d['general']['proyecto']==='')$d['general']['proyecto']='Visita sin título';
    $pdo->beginTransaction();
    try {
        $existing=$id?ojv_load($pdo,$id,$user,0,true):null;
        if($existing&&(int)$existing['version']!==$version)throw new RuntimeException('La visita cambió en otra pestaña. Tus cambios no se sobrescribieron; abre la versión actual en otra pestaña para compararlos.');
        if($d['cliente_id']){
            $q=$pdo->prepare('SELECT id FROM cotizador_clientes WHERE id=? AND usuario_id=?');$q->execute([$d['cliente_id'],$user]);if(!$q->fetchColumn())throw new InvalidArgumentException('Cliente no disponible para tu usuario.');
        }
        $remove=$post['quitar_fotos']??[];
        if(!is_array($remove)||count($remove)>20)throw new InvalidArgumentException('Selección de fotos no válida.');
        foreach($remove as $x)ojv_id($x);
        foreach($existing['form']['fotos']??[] as $f)if(!in_array((string)$f['id'],array_map('strval',$remove),true))$d['fotos'][]=$f;
        if(!$id){$q=$pdo->prepare("INSERT INTO oj_visitas (usuario_id,cliente_id,titulo,cliente_nombre,estado,version,datos,creado_en,actualizado_en) VALUES (?,?,?,?,?,1,'{}',NOW(),NOW())");$q->execute([$user,$d['cliente_id']?:null,$d['general']['proyecto'],$d['general']['cliente_nombre'],'borrador']);$id=(int)$pdo->lastInsertId();}
        if(isset($files['fotos'])){
            $up=$files['fotos'];if(!is_array($up['error']??null)||count($up['error'])>3)throw new InvalidArgumentException('Máximo 3 fotos nuevas por guardado.');
            foreach($up['error'] as $i=>$err){
                if($err===UPLOAD_ERR_NO_FILE)continue;
                if($err!==UPLOAD_ERR_OK)throw new InvalidArgumentException('La fotografía no se recibió completa. Reduce su tamaño e inténtalo de nuevo.');
                $tmp=$up['tmp_name'][$i]??'';
                if(!is_string($tmp)||!is_uploaded_file($tmp)||filesize($tmp)>2097152)throw new InvalidArgumentException('Cada foto debe pesar hasta 2 MB.');
                $info=@getimagesize($tmp);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                if(!$info||!in_array($mime,['image/jpeg','image/png'],true)||$mime!==($info['mime']??'')||$info[0]*$info[1]>24000000)throw new InvalidArgumentException('Usa JPG/PNG válidos de hasta 24 megapíxeles.');
                $name=bin2hex(random_bytes(24)).'.bin';$path=ojv_storage().'/'.$name;
                if(!move_uploaded_file($tmp,$path))throw new RuntimeException('No se pudo guardar la foto.');$newFiles[]=$path;@chmod($path,0600);
                $q=$pdo->prepare('INSERT INTO oj_visita_fotos (visita_id,usuario_id,archivo,mime,creado_en) VALUES (?,?,?,?,NOW())');$q->execute([$id,$user,$name,$mime]);
                $d['fotos'][]=['id'=>(int)$pdo->lastInsertId(),'ubicacion'=>ojv_text($post['foto_ubicacion'][$i]??'',180),'descripcion'=>ojv_text($post['foto_descripcion'][$i]??'',500)];
            }
        }
        if(count($d['fotos'])>20)throw new InvalidArgumentException('Máximo 20 fotografías activas por visita.');
        $v=$existing?$version+1:1;$json=ojv_json($d);$state=$ready?'listo':'borrador';
        $q=$pdo->prepare('UPDATE oj_visitas SET cliente_id=?,titulo=?,cliente_nombre=?,estado=?,version=?,datos=?,actualizado_en=NOW() WHERE id=? AND usuario_id=?');$q->execute([$d['cliente_id']?:null,$d['general']['proyecto'],$d['general']['cliente_nombre'],$state,$v,$json,$id,$user]);
        $q=$pdo->prepare('INSERT INTO oj_visita_versiones (visita_id,version,datos,autor,creado_en) VALUES (?,?,?,?,NOW())');$q->execute([$id,$v,$json,substr($author,0,180)]);$pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();foreach($newFiles as $p)@unlink($p);throw $e;}
}
function ojv_items(array $d): array {
    $items=[];$add=static function($text,$qty=1)use(&$items){if($qty>0)$items[]=['concepto'=>$text,'cantidad'=>(string)$qty,'precio'=>'0','descuento'=>'0'];};
    foreach($d['camaras'] as $c){
        if($c['accion']==='Instalar nuevo')$add($c['id'].' '.$c['ubicacion'].' - Cámara '.$c['tipo'].' / '.$c['tecnologia'].' / '.$c['modelo'].' / '.$c['resolucion']);
        foreach(['caja'=>'Caja de conexiones','soporte'=>'Soporte de cámara'] as $k=>$label)if($c[$k]==='Instalar nuevo')$add($label.' - '.$c['id']);
    }
    foreach($d['tramos'] as $t){
        if($t['accion']==='Instalar nuevo'&&($t['suministro']??'Cotizar por separado')==='Cotizar por separado'){
            if(($t['presentacion']??'')==='Armado con conectores'){
                $assembly=$t['cable']==='Coaxial RG59 + alimentación (siamés)'?'Cable armado BNC + alimentación':'Cable armado con conectores';
                $add($assembly.' '.$t['cable'].' '.$t['categoria'].' / '.($t['largo_armado']??'').' m por pieza - '.$t['id'].' '.$t['origen'].' a '.$t['destino'].' (piezas)',(int)$t['cables']);
            }
            else $add('Cable '.$t['cable'].' '.$t['categoria'].' - '.$t['id'].' '.$t['origen'].' a '.$t['destino'].' (metros, reserva incluida)',ceil(((float)$t['metros']+(float)$t['reserva'])*(int)$t['cables']));
        }
        if($t['canal_accion']==='Instalar nuevo')$add('Canalización '.$t['instalacion'].' '.$t['diametro'].' - '.$t['id'].' (metros)',ceil((float)$t['canal_metros']));
    }
    foreach(['grabador'=>'Grabador','disco'=>'Disco para CCTV','gabinete'=>'Resguardo / gabinete','ups'=>'Respaldo UPS','monitor'=>'Monitor'] as $k=>$label)if($d['infra'][$k.'_accion']==='Instalar nuevo')$add($label.' - '.$d['infra'][$k]);
    if($d['infra']['punto_nuevo']==='Sí')$add('Punto eléctrico nuevo - alcance y ejecución a confirmar con electricista');
    if(count($d['camaras']))$add('Instalación / configuración / pruebas de puntos - revisar alcance y trabajos en altura');
    foreach($d['materiales'] as $m)if($m['concepto']!==''&&(int)$m['cantidad']>0)$add($m['concepto'].' ('.$m['unidad'].')',(int)$m['cantidad']);
    if(count($items)>300)throw new InvalidArgumentException('Demasiados conceptos para un borrador (máximo 300). Divide el proyecto.');
    return $items;
}
function ojv_convert(PDO $pdo,int $id,int $user,int $version): int {
    $pdo->beginTransaction();
    try{
        $v=ojv_load($pdo,$id,$user,0,true);
        if((int)$v['version']!==$version||$v['estado']!=='listo')throw new RuntimeException('Guarda la versión actual como lista antes de preparar la cotización.');
        $q=$pdo->prepare('SELECT b.id FROM oj_visita_cotizaciones x JOIN cotizador_borradores b ON b.id=x.borrador_id AND b.usuario_id=? WHERE x.visita_id=? AND x.version=?');$q->execute([$user,$id,$version]);$found=$q->fetchColumn();
        if($found){$pdo->commit();return (int)$found;}
        $d=$v['form'];$g=$d['general'];$draft=[];
        foreach(['nombre','telefono','email','direccion'] as $f)$draft['cliente_'.$f]=$g['cliente_'.$f];
        $draft+=['proyecto'=>$g['proyecto'],'vigencia'=>'15 días','metodo_pago'=>'Por acordar','tiempo_instalacion'=>$d['ejecucion']['tiempo'],'descripcion'=>'Levantamiento VT-'.str_pad((string)$id,5,'0',STR_PAD_LEFT).' v'.$version."\n".$d['cierre']['recomendacion'],'observaciones'=>$d['cierre']['acuerdos'],'condiciones'=>$d['cierre']['exclusiones'],'solicitud_id'=>'','items'=>ojv_items($d)];
        $q=$pdo->prepare('INSERT INTO cotizador_borradores (usuario_id,titulo,datos,creado_en,actualizado_en) VALUES (?,?,?,NOW(),NOW())');$q->execute([$user,substr($g['proyecto'],0,180),ojv_json($draft)]);$b=(int)$pdo->lastInsertId();
        $q=$pdo->prepare('INSERT INTO oj_visita_cotizaciones (visita_id,version,borrador_id,creado_en) VALUES (?,?,?,NOW())');$q->execute([$id,$version,$b]);$pdo->commit();return $b;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function ojv_report(array $v,bool $client=false,bool $pdf=false): string {
    $d=$v['form'];$g=$d['general'];$folio='VT-'.str_pad((string)$v['id'],5,'0',STR_PAD_LEFT);$out='<h1>Ojo Jarocho | Visita técnica</h1><p>'.ojv_e($folio).' · versión '.(int)$v['version'].' · '.ojv_e($client?'Resumen para el cliente':'Uso interno').'</p>';
    $out.='<p><b>'.ojv_e($g['proyecto']).'</b><br>'.ojv_e($g['cliente_nombre']).'<br>'.nl2br(ojv_e($g['cliente_direccion'])).'<br>Fecha: '.ojv_e($g['fecha']).'</p>';
    foreach(ojv_schema() as $k=>$s){
        if($client&&!in_array($k,['camaras','cierre'],true))continue;
        $rows=isset($s['prefix'])?$d[$k]:[$d[$k]];if(!$rows)continue;
        $out.='<h2>'.ojv_e($s['title']).'</h2>';
        foreach($rows as $row){$out.='<table>';foreach($s['fields'] as $f=>$spec){
            if($client&&$k==='camaras'&&!in_array($f,['id','ubicacion','objetivo'],true))continue;
            if($client&&$k==='cierre'&&!in_array($f,['recomendacion','alternativa','exclusiones','acuerdos','recepcion'],true))continue;
            if(($spec[3]??'')==='inalambrico'&&!in_array($row['tecnologia']??'',['Wi-Fi','4G'],true))continue;
            if(($spec[3]??'')==='armado'&&($row['presentacion']??'')!=='Armado con conectores')continue;
            if(($row[$f]??'')==='')continue;
            $out.='<tr><th>'.ojv_e($spec[0]).'</th><td>'.nl2br(ojv_e($row[$f])).'</td></tr>';
        }$out.='</table>';}
    }
    if(!$client){$out.='<h2>Conceptos preliminares (sin precio)</h2><table>';foreach(ojv_items($d) as $it)$out.='<tr><th>'.ojv_e($it['cantidad']).'</th><td>'.ojv_e($it['concepto']).'</td></tr>';$out.='</table>';
        if($d['fotos']){$out.='<h2>Fotografías de evidencia</h2>';foreach($d['fotos'] as $f){$out.='<div class="foto"><p>'.ojv_e($f['ubicacion']).' - '.ojv_e($f['descripcion']).'</p>';if(!$pdf)$out.='<img src="visita_foto.php?id='.(int)$f['id'].'" alt="Evidencia">';elseif(isset($f['src']))$out.='<img src="'.ojv_e($f['src']).'" alt="Evidencia">';$out.='</div>';}}
    }
    $out.='<p>Esta visita registra una evaluación previa. No constituye aceptación de precios ni autorización de trabajos. La cotización se revisa y acepta por separado.</p>';
    return '<!doctype html><html lang="es"><meta charset="utf-8"><title>'.ojv_e($folio).'</title><style>body{font:12px DejaVu Sans,Arial;color:#17334b;margin:24px}h1{font-size:21px}h2{font-size:15px;border-bottom:2px solid #82b60b;margin-top:22px}table{width:100%;border-collapse:collapse;margin:8px 0 15px}th,td{border:1px solid #d5dfe5;padding:7px;text-align:left;vertical-align:top;word-wrap:break-word}th{width:32%;background:#edf3f6}img{max-width:100%;max-height:280px}.foto{page-break-inside:avoid}tr{page-break-inside:avoid}h2{page-break-after:avoid}@media print{.no-print{display:none}}</style><body>'.$out.'</body></html>';
}
