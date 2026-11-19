<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/app/visitas.php';
try{
    $thumb=isset($_GET['thumb']);
    $q=$pdo->prepare('SELECT f.archivo,f.mime,f.thumb FROM oj_visita_fotos f JOIN oj_visitas v ON v.id=f.visita_id WHERE f.id=? AND f.usuario_id=? AND v.usuario_id=?');
    $q->execute([ojv_id($_GET['id']??null),(int)$_SESSION['usuario_id'],(int)$_SESSION['usuario_id']]);
    $f=$q->fetch(PDO::FETCH_ASSOC);
    if(!$f)throw new RuntimeException('No disponible.');
    if($thumb){
        if(empty($f['thumb'])||!preg_match('/^[a-f0-9]{48}\.bin\.thumb\.jpg$/D',$f['thumb']))throw new RuntimeException('No disponible.');
        $archivo=$f['thumb'];$mime='image/jpeg';
    }else{
        if(!preg_match('/^[a-f0-9]{48}\.bin$/D',$f['archivo']))throw new RuntimeException('No disponible.');
        $archivo=$f['archivo'];$mime=$f['mime'];
    }
    $path=__DIR__.'/app/visitas_fotos/'.$archivo;
    if(!is_file($path))throw new RuntimeException('No disponible.');
    header('Content-Type: '.$mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: inline; filename="evidencia.'.($mime==='image/png'?'png':'jpg').'"');
    readfile($path);
}catch(Throwable $e){http_response_code(404);exit('Fotografía no disponible.');}
