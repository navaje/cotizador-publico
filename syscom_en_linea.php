<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/app/syscom_conexion.php';
require_once __DIR__.'/app/syscom_en_linea.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_post_csrf();
try {
    $id=$_POST['producto']??null;
    if(!is_string($id)||!preg_match('/^[1-9]\d{0,8}$/D',$id))throw new InvalidArgumentException('Selecciona un ID de producto válido.');
    $usuario=(int)$_SESSION['usuario_id'];
    ojso_schema($pdo);
    $token=ojso_token($pdo,$usuario);
    $params=['moneda'=>'usd','iva'=>'false','financiero'=>'false','inventarios'=>'true'];
    try{$r=ojso_http('productos/'.$id,$params,$token);}
    catch(RuntimeException $e){
        if($e->getCode()!==401)throw $e;
        $token=ojso_token($pdo,$usuario,true);
        $r=ojso_http('productos/'.$id,$params,$token);
    }
    unset($token);
    $producto=ojso_producto_en_linea($r,$id);
    echo json_encode(['producto'=>$producto],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    $codigo=(int)$e->getCode();
    if($e instanceof PDOException){
        $codigo=500;$mensaje='No se pudo leer la conexión de tu usuario. Revisa Conectar API SYSCOM.';
        error_log('SYSCOM en línea: error de base de datos.');
    }elseif($e instanceof JsonException){$codigo=502;$mensaje='SYSCOM devolvió una respuesta que no se pudo interpretar.';}
    elseif($e instanceof RuntimeException||$e instanceof InvalidArgumentException){
        $mensaje=$e->getMessage();
        $codigo=in_array($codigo,[401,403,404,409,429],true)?$codigo:422;
    }else{
        $codigo=500;$mensaje='No se pudo completar la consulta en línea. Revisa el registro PHP del hosting.';
        error_log('SYSCOM en línea: error interno.');
    }
    http_response_code($codigo);
    echo json_encode(['error'=>$mensaje],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}
