<?php
function ojso_e($valor): string { return htmlspecialchars((string)$valor,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function ojso_schema(PDO $pdo): void {
    try { $pdo->query('SELECT usuario_id FROM cotizador_syscom_conexion LIMIT 0');return; }
    catch (PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1146) throw $e; }
    $pdo->exec(file_get_contents(dirname(__DIR__).'/MIGRACION_SYSCOM_CONEXION.sql'));
}
function ojso_directorio_clave(): string {
    $raiz=realpath($_SERVER['DOCUMENT_ROOT']??'');
    $app=realpath(dirname(__DIR__));
    if (!$raiz||!$app||strpos($app,$raiz.DIRECTORY_SEPARATOR)!==0) throw new RuntimeException('No se pudo verificar una ubicación privada para la clave de cifrado.');
    $partes=explode(DIRECTORY_SEPARATOR,$raiz);$indice=array_search('public_html',$partes,true);
    if($indice!==false)$padre=realpath(implode(DIRECTORY_SEPARATOR,array_slice($partes,0,$indice)));
    // Raíz adicional confirmada en el Administrador de archivos de esta cuenta.
    elseif($raiz==='/home4/jesusna1/ojojarocho.online')$padre=realpath('/home4/jesusna1');
    elseif(in_array(basename($raiz),['htdocs','httpdocs','www'],true))$padre=realpath(dirname($raiz));
    else throw new RuntimeException('No se pudo identificar la carpeta privada de esta cuenta de hosting. Se necesita revisar la ubicación pública del dominio antes de guardar credenciales.');
    if (!$padre||$padre===$raiz||$padre===DIRECTORY_SEPARATOR) throw new RuntimeException('El hosting no ofrece una ubicación privada verificable para la clave de cifrado.');
    return $padre.'/.ojo_jarocho_syscom_'.substr(hash('sha256',$app),0,16);
}
function ojso_clave(bool $crear=false): string {
    if (!function_exists('openssl_encrypt')||!in_array('aes-256-gcm',openssl_get_cipher_methods(),true)) throw new RuntimeException('El hosting necesita OpenSSL con AES-256-GCM para guardar las credenciales.');
    $dir=ojso_directorio_clave();
    if (!is_dir($dir)&&(!$crear||(!@mkdir($dir,0700,true)&&!is_dir($dir)))) throw new RuntimeException('No se pudo abrir la carpeta privada de credenciales. Revisa permisos de la cuenta del hosting.');
    if (is_link($dir)) throw new RuntimeException('La carpeta privada no puede ser un enlace simbólico.');
    $real=realpath($dir);$raiz=realpath($_SERVER['DOCUMENT_ROOT']);
    if (!$real||$real===$raiz||strpos($real,$raiz.DIRECTORY_SEPARATOR)===0) throw new RuntimeException('La clave debe permanecer fuera de la carpeta pública del sitio.');
    $ruta=$real.'/clave.bin';
    if (is_link($ruta)) throw new RuntimeException('El archivo de clave no puede ser un enlace simbólico.');
    $f=@fopen($ruta,$crear?'c+b':'rb');
    if (!$f) throw new RuntimeException('No se pudo abrir la clave privada del hosting.');
    try {
        if (!flock($f,LOCK_EX)) throw new RuntimeException('No se pudo bloquear la clave privada.');
        $clave=stream_get_contents($f);
        if ($clave===''&&$crear) {
            $clave=random_bytes(32);rewind($f);
            if (fwrite($f,$clave)!==32||!fflush($f)) throw new RuntimeException('No se pudo guardar la clave privada.');
            @chmod($ruta,0600);
        }
        if (strlen($clave)!==32) throw new RuntimeException('La clave privada no es válida. Restaura su respaldo; no la reemplaces mientras existan credenciales guardadas.');
        return $clave;
    } finally { flock($f,LOCK_UN);fclose($f); }
}
function ojso_cifrar(array $datos,string $clave,int $usuario,string $tipo): string {
    $iv=random_bytes(12);$tag='';$json=json_encode($datos,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $cipher=openssl_encrypt($json,'aes-256-gcm',$clave,OPENSSL_RAW_DATA,$iv,$tag,'ojo-syscom:'.$usuario.':'.$tipo,16);
    if ($cipher===false) throw new RuntimeException('No se pudieron cifrar las credenciales.');
    return base64_encode($iv.$tag.$cipher);
}
function ojso_descifrar(string $datos,string $clave,int $usuario,string $tipo): array {
    $raw=base64_decode($datos,true);
    if ($raw===false||strlen($raw)<29) throw new RuntimeException('El registro cifrado no es válido.');
    $json=openssl_decrypt(substr($raw,28),'aes-256-gcm',$clave,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'ojo-syscom:'.$usuario.':'.$tipo);
    if ($json===false) throw new RuntimeException('No se pudo descifrar la conexión. Revisa el respaldo de la clave privada del hosting.');
    $r=json_decode($json,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($r)) throw new RuntimeException('Registro de conexión no válido.');
    return $r;
}
function ojso_registro(PDO $pdo,int $usuario): ?array {
    $stmt=$pdo->prepare('SELECT * FROM cotizador_syscom_conexion WHERE usuario_id=?');$stmt->execute([$usuario]);return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
}
function ojso_http(string $ruta,array $params=[],?string $token=null,bool $oauth=false): array {
    // Lista cerrada: nunca recibe una URL proporcionada por el usuario.
    if (!($oauth&&$ruta==='oauth/token')&&!(!$oauth&&($ruta==='categorias'||$ruta==='productos'||preg_match('/^productos\/[1-9]\d{0,8}$/D',$ruta)))) throw new InvalidArgumentException('Ruta de consulta no permitida.');
    if (!function_exists('curl_init')) throw new RuntimeException('El hosting necesita la extensión PHP cURL para conectar con SYSCOM.');
    $url='https://developers.syscom.mx/api/v1/'.$ruta;
    if (!$oauth&&$params) $url.='?'.http_build_query($params);
    $headers=['Accept: application/json'];
    if ($token!==null) {
        if ($token===''||strlen($token)>16000||preg_match('/[\r\n]/',$token)) throw new RuntimeException('Token de acceso no válido.');
        $headers[]='Authorization: Bearer '.$token;
    }
    if ($oauth) $headers[]='Content-Type: application/x-www-form-urlencoded';
    $c=curl_init($url);$body='';$limite=false;$retry='';
    curl_setopt_array($c,[CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_WRITEFUNCTION=>static function($ch,string $parte)use(&$body,&$limite):int{if(strlen($body)+strlen($parte)>2097152){$limite=true;return 0;}$body.=$parte;return strlen($parte);},
        CURLOPT_HEADERFUNCTION=>static function($ch,string $linea)use(&$retry):int{if(stripos($linea,'Retry-After:')===0)$retry=trim(substr($linea,12));return strlen($linea);}]);
    if ($oauth) {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($params));}
    $ok=curl_exec($c);$http=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    if ($limite) throw new RuntimeException('SYSCOM devolvió una respuesta demasiado grande.');
    if ($ok===false) throw new RuntimeException('No se pudo establecer la conexión HTTPS con SYSCOM. Revisa cURL, certificados y conexiones salientes del hosting.');
    if ($http<200||$http>=300) {
        $mensajes=[400=>'Revisa los datos de la solicitud.',401=>'Credenciales o token no válidos, o cuenta sin acceso API.',403=>'Cuenta sin acceso o sin permiso ver-productos.',404=>'No se encontró el recurso o producto solicitado.',429=>'Límite de solicitudes alcanzado.'];
        $espera=$http===429&&ctype_digit($retry)?' Espera '.$retry.' segundos antes de reintentar.':'';
        throw new RuntimeException('SYSCOM HTTP '.$http.'. '.($mensajes[$http]??'No se recibió una respuesta satisfactoria.').$espera,$http);
    }
    $r=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($r)) throw new RuntimeException('SYSCOM respondió con un formato no válido.');
    return $r;
}
function ojso_nuevo_token(string $id,string $secret): array {
    $r=ojso_http('oauth/token',['grant_type'=>'client_credentials','client_id'=>$id,'client_secret'=>$secret],null,true);
    if (!isset($r['access_token'])||!is_string($r['access_token'])||$r['access_token']===''||strlen($r['access_token'])>16000||preg_match('/[\r\n]/',$r['access_token'])) throw new RuntimeException('La respuesta no contiene un token válido.');
    if (strtolower((string)($r['token_type']??''))!=='bearer') throw new RuntimeException('SYSCOM devolvió un tipo de token no admitido.');
    $duracion=filter_var($r['expires_in']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>60,'max_range'=>315360000]]);
    if ($duracion===false) throw new RuntimeException('La respuesta no indica una vigencia de token válida.');
    return ['valor'=>$r['access_token'],'hasta'=>time()+$duracion-30];
}
function ojso_guardar(PDO $pdo,int $usuario,int $version,string $cred,string $token,int $hasta): void {
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT id FROM usuarios WHERE id=? FOR UPDATE');$stmt->execute([$usuario]);if(!$stmt->fetchColumn())throw new RuntimeException('Usuario no disponible.');
        $stmt=$pdo->prepare('SELECT version FROM cotizador_syscom_conexion WHERE usuario_id=? FOR UPDATE');$stmt->execute([$usuario]);$actual=$stmt->fetchColumn();
        if(($actual===false&&$version!==0)||($actual!==false&&(int)$actual!==$version))throw new RuntimeException('La conexión cambió en otra pestaña. Recarga antes de guardar otra configuración.',409);
        if($actual===false){$stmt=$pdo->prepare('INSERT INTO cotizador_syscom_conexion (credenciales,token,token_hasta,usuario_id,version,actualizado_en) VALUES (?,?,?,?,1,UTC_TIMESTAMP())');$stmt->execute([$cred,$token,$hasta,$usuario]);}
        else{$stmt=$pdo->prepare('UPDATE cotizador_syscom_conexion SET credenciales=?,token=?,token_hasta=?,version=version+1,actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=? AND version=?');$stmt->execute([$cred,$token,$hasta,$usuario,$version]);if($stmt->rowCount()!==1)throw new RuntimeException('La conexión cambió. Recarga la página.',409);}
        $pdo->commit();
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function ojso_token(PDO $pdo,int $usuario,bool $renovar=false): string {
    $r=ojso_registro($pdo,$usuario);if(!$r)throw new RuntimeException('Guarda primero las credenciales de tu cuenta.');
    $clave=ojso_clave();
    if(!$renovar&&(int)$r['token_hasta']>time()){$t=ojso_descifrar($r['token'],$clave,$usuario,'token');return $t['valor'];}
    $cred=ojso_descifrar($r['credenciales'],$clave,$usuario,'credenciales');$t=ojso_nuevo_token($cred['id'],$cred['secret']);
    ojso_guardar($pdo,$usuario,(int)$r['version'],$r['credenciales'],ojso_cifrar(['valor'=>$t['valor']],$clave,$usuario,'token'),$t['hasta']);return $t['valor'];
}
function ojso_resumen_producto(array $r): array {
    // Muestra sólo estructura y datos del producto necesarios para verificar el contrato.
    $salida=[];
    foreach($r as $k=>$v){
        $nombre=strtolower((string)$k);
        if(preg_match('/token|secret|password|authorization|cookie|credencial/',$nombre))continue;
        if(preg_match('/descripcion|imagen|recurso|html|caracteristica/',$nombre))continue;
        if(is_array($v))$salida[$k]=ojso_resumen_producto($v);
        elseif(is_string($v)){preg_match('/^.{0,1500}/us',$v,$m);$salida[$k]=$m[0]??'';}
        elseif(is_scalar($v)||$v===null)$salida[$k]=$v;
    }
    return $salida;
}
