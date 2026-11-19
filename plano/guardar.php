<?php
require_once __DIR__.'/../auth.php';
require_once __DIR__.'/../app/visitas.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
try {
    require_post_csrf();
    $user=(int)$_SESSION['usuario_id'];
    $imagen=(string)($_POST['imagen']??'');
    $visitaId=ojv_id($_POST['visita_id']??null,true);
    $cotizaId=ojv_id($_POST['id']??null,true);
    if($imagen===''||strlen($imagen)>8*1024*1024)throw new InvalidArgumentException('Imagen vacía o demasiado grande.');
    if(strpos($imagen,'data:image/png;base64,')!==0)throw new InvalidArgumentException('Formato de imagen no permitido.');
    $data=base64_decode(str_replace(' ','+',substr($imagen,22)),true);
    if($data===false||$data===''||strlen($data)>3*1024*1024)throw new InvalidArgumentException('Imagen inválida o muy grande (máx 3 MB).');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($data);
    $info=@getimagesizefromstring($data);
    if($mime!=='image/png'||!$info||($info['mime']??'')!=='image/png')throw new InvalidArgumentException('El plano debe ser una imagen PNG real.');
    $dir=__DIR__.'/../app/storage/planos';
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
    $name=bin2hex(random_bytes(24)).'.bin';
    $ruta=$dir.'/'.$name;
    if(file_put_contents($ruta,$data)===false)throw new RuntimeException('No se pudo guardar el archivo.');
    @chmod($ruta,0600);
    $q=$pdo->prepare('INSERT INTO oj_planos (usuario_id,visita_id,cotizacion_id,archivo,creado_en) VALUES (?,?,?,?,NOW())');
    $q->execute([$user,$visitaId?:null,$cotizaId?:null,$name]);
    $pid=(int)$pdo->lastInsertId();
    echo json_encode(['status'=>'ok','plano_id'=>$pid,'archivo'=>'ver.php?id='.$pid]);
} catch(Throwable $e){
    error_log('Guardar plano: '.$e->getMessage());
    http_response_code(400);
    echo json_encode(['status'=>'error','mensaje'=>$e->getMessage()]);
}
