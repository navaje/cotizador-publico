<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/app/syscom_conexion.php';
require_once __DIR__.'/app/syscom_en_linea.php';
require_once __DIR__.'/app/syscom_busqueda.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_post_csrf();
try{
    $consulta=ojsb_parametros($_POST);$usuario=(int)$_SESSION['usuario_id'];
    ojso_schema($pdo);$token=ojso_token($pdo,$usuario);
    try{$r=ojso_http('productos',$consulta['params'],$token);}
    catch(RuntimeException $e){
        if($e->getCode()!==401)throw $e;
        $token=ojso_token($pdo,$usuario,true);
        $r=ojso_http('productos',$consulta['params'],$token);
    }
    unset($token);
    echo json_encode(ojsb_resultados($r,$consulta),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    if($e instanceof RuntimeException&&$e->getCode()===404&&isset($consulta)&&$consulta['tipo']==='modelo'){
        echo json_encode(['items'=>[],'pagina'=>1,'hay_mas'=>false,'omitidos'=>0,'sin_stock'=>0,'fuente'=>'api']);exit;
    }
    $codigo=(int)$e->getCode();
    if($e instanceof PDOException){$codigo=500;$mensaje='No se pudo leer la conexión de tu usuario. Revisa Conectar API SYSCOM.';error_log('Búsqueda SYSCOM: error de base de datos.');}
    elseif($e instanceof JsonException){$codigo=502;$mensaje='SYSCOM devolvió una respuesta que no se pudo interpretar.';}
    elseif($e instanceof RuntimeException||$e instanceof InvalidArgumentException){$mensaje=$e->getMessage();$codigo=in_array($codigo,[401,403,404,409,429],true)?$codigo:422;}
    else{$codigo=500;$mensaje='No se pudo completar la búsqueda en línea. Revisa el registro PHP del hosting.';error_log('Búsqueda SYSCOM: error interno.');}
    http_response_code($codigo);echo json_encode(['error'=>$mensaje],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}
