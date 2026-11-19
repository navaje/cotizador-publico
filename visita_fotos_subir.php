<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/app/visitas.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
try {
    require_post_csrf();
    $user=(int)$_SESSION['usuario_id'];
    $vid=ojv_id($_POST['visita_id']??null);
    $up=$_FILES['fotos']??null;
    if(!$up||!is_array($up['error']??null)||count($up['error'])>10)throw new InvalidArgumentException('Máximo 10 fotos por carga.');
    $pdo->beginTransaction();
    $v=ojv_load($pdo,$vid,$user,0,true);
    $d=$v['form'];
    if(!isset($d['fotos'])||!is_array($d['fotos']))$d['fotos']=[];
    $ids=[];$nuevas=0;$paths=[];
    foreach($up['error'] as $i=>$err){
        if($err===UPLOAD_ERR_NO_FILE)continue;
        if($err!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Una fotografía no se recibió completa. Reduce su tamaño e inténtalo de nuevo.');
        $punto=ojv_text($_POST['foto_punto'][$i]??'',20);
        if($punto!==''&&!preg_match('/^(CAM|TR|MAT)-[0-9]{2,5}$/D',$punto))throw new InvalidArgumentException('Punto no válido: usa el identificador (CAM-01, TR-02...).');
        $f=ojv_store_foto($pdo,$vid,$user,$up['tmp_name'][$i]??'',(int)($up['size'][$i]??0),
            ojv_text($_POST['foto_ubicacion'][$i]??'',180),
            ojv_text($_POST['foto_descripcion'][$i]??'',500),
            $punto);
        $paths[]=$f['ruta'];
        $d['fotos'][]=['id'=>$f['id'],'ubicacion'=>$f['ubicacion'],'descripcion'=>$f['descripcion'],'punto'=>$f['punto'],'lat'=>$f['lat'],'lng'=>$f['lng']];
        $ids[]=$f['id'];$nuevas++;
    }
    if(count($d['fotos'])>20)throw new InvalidArgumentException('Máximo 20 fotografías activas por visita.');
    $q=$pdo->prepare('UPDATE oj_visitas SET datos=?,actualizado_en=NOW() WHERE id=? AND usuario_id=?');
    $q->execute([ojv_json($d),$vid,$user]);
    $pdo->commit();
    echo json_encode(['ok'=>true,'subidas'=>$nuevas,'ids'=>$ids]);
} catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    foreach($paths??[] as $p)@unlink($p);
    error_log('Subida fotos visita: '.$e->getMessage());
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
