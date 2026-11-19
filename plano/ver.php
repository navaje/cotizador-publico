<?php
require_once __DIR__.'/../auth.php';
header('Cache-Control: private, no-store');
try {
    $id=(int)($_GET['id']??0);
    if($id<=0)throw new RuntimeException('Plano no disponible.');
    $q=$pdo->prepare('SELECT archivo FROM oj_planos WHERE id=? AND usuario_id=?');
    $q->execute([$id,(int)$_SESSION['usuario_id']]);
    $r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r||!preg_match('/^[a-f0-9]{48}\.bin$/D',$r['archivo']))throw new RuntimeException('Plano no disponible.');
    $ruta=__DIR__.'/../app/storage/planos/'.$r['archivo'];
    if(!is_file($ruta))throw new RuntimeException('Plano no disponible.');
    header('Content-Type: image/png');
    header('Content-Length: '.filesize($ruta));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="plano-'.$id.'.png"');
    readfile($ruta);
} catch(Throwable $e){
    http_response_code(404);
    exit('Plano no disponible.');
}
