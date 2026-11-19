<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/db.php';require_once __DIR__.'/app/inspecciones.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
try{
    $loaded=ojc_load($pdo,(int)($_GET['id']??0),(int)($_GET['version']??0));$idx=(int)($_GET['indice']??-1);
    $photo=$loaded['datos']['fotos'][$idx]??null;if(!$photo)throw new InvalidArgumentException('Foto no encontrada.');
    $uri=ojc_photo_uri($photo);if(!$uri)throw new InvalidArgumentException('La evidencia no está disponible. Revisa el respaldo de fotografías.');
    [$header,$encoded]=explode(',',$uri,2);$mime=strpos($header,'image/jpeg')!==false?'image/jpeg':'image/png';
    header('Content-Type: '.$mime);header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');echo base64_decode($encoded,true);
}catch(Throwable $e){http_response_code(404);exit('Evidencia no encontrada o no disponible.');}
