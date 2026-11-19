<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/app/visitas.php';
header('Cache-Control: no-store');
if(($_SESSION['usuario_rol']??'')!=='admin'){http_response_code(403);exit('La instalación requiere un administrador.');}
$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_post_csrf();
    try{
        foreach(explode(';',file_get_contents(__DIR__.'/MIGRACION_VISITAS.sql')) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
        ojv_schema_upgrade($pdo);
        foreach(explode(';',file_get_contents(__DIR__.'/MIGRACION_PLANOS.sql')) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
        require_once __DIR__.'/app/borradores.php';oj_borradores_schema($pdo);
        require_once __DIR__.'/app/clientes.php';oj_clientes_schema($pdo);
        ojv_storage();$message='Instalación completa. Ya puedes abrir Visitas técnicas.';
    }catch(Throwable $e){error_log('Visitas instalación: '.$e->getMessage());http_response_code(500);$message='No se completó la instalación. Revisa permisos de base de datos y carpeta app/visitas_fotos. Puedes reintentar; no se borran tablas existentes.';}
}
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar visitas técnicas</title><link rel="stylesheet" href="assets/visitas.css"><main><h1>Instalar visitas técnicas</h1><p>Crea cuatro tablas nuevas, prepara evidencias y verifica clientes y borradores. No elimina ni reemplaza datos existentes.</p><p><?=ojv_e($message)?></p><form method="post"><?=csrf_field()?><button>Preparar módulo</button></form><p><a href="visitas.php">Abrir visitas</a> · <a href="dashboard.php">Volver al panel</a></p></main></html>
