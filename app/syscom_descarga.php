<?php
function ojau_schema(PDO $pdo): void {
    try{$pdo->query('SELECT usuario_id FROM cotizador_syscom_descargas LIMIT 0');$pdo->query('SELECT fuente_hash FROM cotizador_syscom_descarga_limites LIMIT 0');return;}
    catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1146)throw $e;}
    foreach(explode(';',file_get_contents(dirname(__DIR__).'/MIGRACION_SYSCOM_DESCARGA.sql')) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
}
function ojau_url($valor): string {
    if(!is_string($valor))throw new InvalidArgumentException('Pega el enlace de descarga del reporte general 1758.');
    $url=trim($valor);
    if(!preg_match('~^https://www\.syscom\.mx/api/reportes-csv/publico/1758/[a-f0-9]{32}$~D',$url))throw new InvalidArgumentException('El enlace debe ser HTTPS de www.syscom.mx y corresponder al reporte 1758, sin parámetros adicionales.');
    return $url;
}
function ojau_fila(PDO $pdo,int $usuario,bool $bloqueo=false): ?array {
    $s=$pdo->prepare('SELECT * FROM cotizador_syscom_descargas WHERE usuario_id=?'.($bloqueo?' FOR UPDATE':''));$s->execute([$usuario]);return $s->fetch(PDO::FETCH_ASSOC)?:null;
}
function ojau_version(array $r,int $v):void{if((int)$r['version']!==$v)throw new RuntimeException('La actualización cambió en otra pestaña. Pulsa Revisar estado antes de continuar.',409);}
function ojau_fecha(int $t): string {return $t?(new DateTimeImmutable('@'.$t))->setTimezone(new DateTimeZone('America/Mexico_City'))->format('d/m/Y H:i:s'):'';}
function ojau_publico(PDO $pdo,int $u):array{
    $r=ojau_fila($pdo,$u);$activo=ojsc_actual($pdo,$u);
    if(!$r)return ['tarea'=>null,'activo'=>$activo];
    $restante=max(0,(int)$r['ultimo_intento']+3600-time());
    return ['activo'=>$activo,'tarea'=>['version'=>(int)$r['version'],'estado'=>$r['estado'],'filas'=>(int)$r['filas'],'bytes'=>(int)$r['bytes'],'offset'=>(int)$r['offset_bytes'],'mensaje'=>$r['mensaje'],'ultimo_intento'=>ojau_fecha((int)$r['ultimo_intento']),'ultimo_exito'=>ojau_fecha((int)$r['ultimo_exito']),'ultimas_filas'=>(int)$r['ultimas_filas'],'espera_segundos'=>$restante]];
}
function ojau_ruta(int $u,string $token):string{
    if(!preg_match('/^[a-f0-9]{32}$/D',$token))throw new RuntimeException('Identificador privado de descarga no válido.');
    ojso_clave(); // Verifica la carpeta privada y su clave existentes.
    $dir=ojso_directorio_clave();$ruta=$dir.'/csv-'.$u.'-'.$token.'.tmp';
    if(is_link($ruta))throw new RuntimeException('El archivo temporal no puede ser un enlace simbólico.');
    return $ruta;
}
function ojau_borrar(?string $ruta):void{if($ruta&&is_file($ruta)&&!is_link($ruta))@unlink($ruta);}
function ojau_guardar(PDO $pdo,int $u,int $v,$url):void{
    $url=ojau_url($url);$cifrado=ojso_cifrar(['url'=>$url],ojso_clave(),$u,'csv1758');unset($url);
    $pdo->beginTransaction();try{
        ojsc_lock_usuario($pdo,$u);$r=ojau_fila($pdo,$u,true);
        if($r){ojau_version($r,$v);if(in_array($r['estado'],['descargando','importando','pausado'],true))throw new RuntimeException('Termina o cancela la actualización pendiente antes de cambiar el enlace.',409);
            $s=$pdo->prepare("UPDATE cotizador_syscom_descargas SET fuente_cifrada=?,version=version+1,mensaje='Enlace guardado.',actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=?");$s->execute([$cifrado,$u]);
        }else{if($v!==0)throw new RuntimeException('Recarga el estado antes de guardar.',409);$s=$pdo->prepare("INSERT INTO cotizador_syscom_descargas (usuario_id,fuente_cifrada,actualizado_en,mensaje) VALUES (?,?,UTC_TIMESTAMP(),'Enlace guardado.')");$s->execute([$u,$cifrado]);}
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function ojau_reservar(PDO $pdo,int $u,int $v):array{
    $r=ojau_fila($pdo,$u);if(!$r)throw new RuntimeException('Guarda primero el enlace del reporte 1758.');
    $cred=ojso_descifrar($r['fuente_cifrada'],ojso_clave(),$u,'csv1758');$url=ojau_url($cred['url']??null);unset($cred);
    if(!function_exists('curl_init'))throw new RuntimeException('El hosting necesita la extensión cURL.');
    $token=bin2hex(random_bytes(16));$ruta=ojau_ruta($u,$token);$antigua=null;
    $pdo->beginTransaction();try{
        ojsc_lock_usuario($pdo,$u);$r=ojau_fila($pdo,$u,true);ojau_version($r,$v);
        if(in_array($r['estado'],['descargando','importando','pausado'],true))throw new RuntimeException('Ya hay una actualización pendiente. Revisa su estado o cancélala.',409);
        $s=$pdo->prepare("SELECT id FROM cotizador_syscom_cargas WHERE usuario_id=? AND estado='cargando' LIMIT 1");$s->execute([$u]);if($s->fetchColumn())throw new RuntimeException('Hay otra importación pendiente. Termínala o cancélala desde Importar catálogo.',409);
        $hash=hash('sha256',$url);$s=$pdo->prepare('INSERT IGNORE INTO cotizador_syscom_descarga_limites (fuente_hash,ultimo_intento) VALUES (?,0)');$s->execute([$hash]);
        $s=$pdo->prepare('SELECT ultimo_intento FROM cotizador_syscom_descarga_limites WHERE fuente_hash=? FOR UPDATE');$s->execute([$hash]);$ultimo=(int)$s->fetchColumn();$ahora=time();
        if($ultimo&&$ahora-$ultimo<3600)throw new RuntimeException('Este enlace ya se solicitó hace menos de una hora. Espera '.(3600-($ahora-$ultimo)).' segundos antes de descargar otra vez.',429);
        if($r['archivo_token']!=='')$antigua=ojau_ruta($u,$r['archivo_token']);
        $s=$pdo->prepare('UPDATE cotizador_syscom_descarga_limites SET ultimo_intento=? WHERE fuente_hash=?');$s->execute([$ahora,$hash]);
        $s=$pdo->prepare("INSERT INTO cotizador_syscom_cargas (usuario_id,archivo,estado,creado_en,actualizado_en) VALUES (?,'SYSCOM-1758-enlace.csv','cargando',NOW(),NOW())");$s->execute([$u]);$carga=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("UPDATE cotizador_syscom_descargas SET estado='descargando',archivo_token=?,carga_id=?,bytes=0,offset_bytes=0,encabezado=NULL,filas=0,ultimo_intento=?,mensaje='Descargando el reporte general 1758.',version=version+1,actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=?");$s->execute([$token,$carga,$ahora,$u]);$pdo->commit();
        ojau_borrar($antigua);return ['url'=>$url,'ruta'=>$ruta,'version'=>$v+1,'carga'=>$carga];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function ojau_descargar_archivo(string $url,string $ruta):int{
    $f=@fopen($ruta,'x+b');if(!$f)throw new RuntimeException('No se pudo crear el archivo temporal privado. Revisa espacio y permisos del hosting.');@chmod($ruta,0600);
    $bytes=0;$limite=false;$escritura=false;$c=curl_init($url);
    try{
        curl_setopt_array($c,[CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>55,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Accept: text/csv, application/octet-stream;q=0.9','Accept-Encoding: identity'],
            CURLOPT_WRITEFUNCTION=>static function($ch,string $datos)use($f,&$bytes,&$limite,&$escritura):int{
                $n=strlen($datos);if($bytes+$n>419430400){$limite=true;return 0;}
                $i=0;while($i<$n){$w=fwrite($f,substr($datos,$i));if($w===false||$w===0){$escritura=true;return 0;}$i+=$w;}$bytes+=$n;return $n;
            }]);
        $ok=curl_exec($c);$http=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);
        if($limite)throw new RuntimeException('El reporte supera el máximo de 400 MB. El catálogo anterior se conserva.');
        if($escritura)throw new RuntimeException('No se pudo guardar la descarga. Revisa el espacio del hosting.');
        if($http===429)throw new RuntimeException('SYSCOM limitó la descarga. Espera al menos una hora antes de un nuevo intento.',429);
        if($http!==200)throw new RuntimeException('La descarga respondió HTTP '.$http.'. No se aceptan redirecciones ni páginas de acceso.');
        if($ok===false)throw new RuntimeException('No se completó la descarga HTTPS en el hosting (límite de 55 segundos). Se conserva el catálogo anterior. Puedes usar la importación manual.');
        if($bytes===0||!fflush($f))throw new RuntimeException('La descarga está vacía o no se pudo guardar.');
        return $bytes;
    }finally{curl_close($c);fclose($f);}
}
function ojau_cabecera(string $ruta):array{
    $f=@fopen($ruta,'rb');if(!$f)throw new RuntimeException('No se pudo abrir la descarga privada.');
    try{
        $cab=fgetcsv($f,0,',','"','');if(!is_array($cab)||count($cab)>200)throw new RuntimeException('La descarga no tiene una cabecera CSV válida.');
        $cab=array_map(static function($s){return trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$s));},$cab);
        if(count(array_unique($cab))!==count($cab))throw new RuntimeException('El CSV repite columnas.');
        $mapa=[];foreach(['ID Producto'=>'id','Modelo'=>'modelo','Marca'=>'marca','Título'=>'titulo','Su Precio'=>'costo_usd','Stock Veracruz'=>'veracruz','Menu Nvl 1'=>'categoria','Menu Nvl 2'=>'subcategoria','Menu Nvl 3'=>'familia'] as $titulo=>$campo){
            $i=array_search($titulo,$cab,true);if($i===false)throw new RuntimeException('Falta la columna '.$titulo.'. Revisa las opciones del reporte 1758 en SYSCOM.');$mapa[$campo]=$i;
        }
        return ['offset'=>ftell($f),'columnas'=>count($cab),'mapa'=>$mapa];
    }finally{fclose($f);}
}
function ojau_descargar(PDO $pdo,int $u,int $v):void{
    $t=ojau_reservar($pdo,$u,$v);
    try{
        $bytes=ojau_descargar_archivo($t['url'],$t['ruta']);unset($t['url']);$cab=ojau_cabecera($t['ruta']);
        $pdo->beginTransaction();ojsc_lock_usuario($pdo,$u);$r=ojau_fila($pdo,$u,true);ojau_version($r,$t['version']);
        if($r['estado']!=='descargando')throw new RuntimeException('La descarga ya no está pendiente.',409);
        $c=ojsc_carga($pdo,$t['carga'],$u);if($c['estado']!=='cargando')throw new RuntimeException('La importación se canceló desde otra pantalla.',409);
        $s=$pdo->prepare("UPDATE cotizador_syscom_descargas SET estado='importando',bytes=?,offset_bytes=?,encabezado=?,mensaje='Descarga completa. Importando por lotes.',version=version+1,actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=?");$s->execute([$bytes,$cab['offset'],json_encode($cab,JSON_THROW_ON_ERROR),$u]);$pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();ojau_borrar($t['ruta']);
        // Sólo limpia la reserva propia; nunca una tarea nueva de otra pestaña.
        $pdo->beginTransaction();try{
            ojsc_lock_usuario($pdo,$u);$r=ojau_fila($pdo,$u,true);
            if($r&&(int)$r['version']===$t['version']&&$r['estado']==='descargando'){
                $s=$pdo->prepare("SELECT id FROM cotizador_syscom_cargas WHERE id=? AND usuario_id=? AND estado='cargando' FOR UPDATE");$s->execute([$t['carga'],$u]);
                if($s->fetchColumn()){$s=$pdo->prepare('DELETE FROM cotizador_syscom_productos WHERE carga_id=?');$s->execute([$t['carga']]);$s=$pdo->prepare('DELETE FROM cotizador_syscom_cargas WHERE id=? AND usuario_id=?');$s->execute([$t['carga'],$u]);}
                $s=$pdo->prepare("UPDATE cotizador_syscom_descargas SET estado='error',carga_id=NULL,mensaje='Descarga detenida. Se conserva el catálogo anterior; revisa el mensaje y espera una hora antes de descargar de nuevo.',version=version+1,actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=?");$s->execute([$u]);
            }$pdo->commit();
        }catch(Throwable $limpieza){if($pdo->inTransaction())$pdo->rollBack();error_log('CSV SYSCOM: limpieza de reserva pendiente.');}
        throw $e;
    }
}
function ojau_procesar(PDO $pdo,int $u,int $v):void{
    $f=null;$ruta=null;$fin=false;
    $pdo->beginTransaction();try{
        ojsc_lock_usuario($pdo,$u);$r=ojau_fila($pdo,$u,true);if(!$r)throw new RuntimeException('No hay una actualización pendiente.');ojau_version($r,$v);
        if(!in_array($r['estado'],['importando','pausado'],true))throw new RuntimeException('No hay un CSV pendiente de importar.',409);
        $c=ojsc_carga($pdo,(int)$r['carga_id'],$u);if($c['estado']!=='cargando')throw new RuntimeException('La carga se cerró desde otra pantalla.',409);
        if((int)$c['filas']!==(int)$r['filas'])throw new RuntimeException('La importación cambió desde otra pantalla. Cancela esta actualización.',409);
        $ruta=ojau_ruta($u,$r['archivo_token']);$f=@fopen($ruta,'rb');if(!$f||filesize($ruta)!==(int)$r['bytes']||fseek($f,(int)$r['offset_bytes'])!==0)throw new RuntimeException('La descarga privada no está completa o no está disponible. Cancela la actualización.');
        $cab=json_decode($r['encabezado'],true,32,JSON_THROW_ON_ERROR);$filas=[];$leidas=0;$inicio=microtime(true);
        while(count($filas)<100&&$leidas<200&&microtime(true)-$inicio<8){
            $antes=ftell($f);$fila=fgetcsv($f,0,',','"','');if($fila===false){if(!feof($f))throw new RuntimeException('Error al leer el CSV.');break;}
            ++$leidas;if(ftell($f)-$antes>2097152)throw new RuntimeException('Una fila del CSV supera 2 MB. Se detuvo la importación.');
            if($fila===[null])continue;
            if(count($fila)!==$cab['columnas'])throw new RuntimeException('Una fila del CSV no coincide con las columnas del encabezado. No se activó el catálogo.');
            $p=[];foreach($cab['mapa'] as $campo=>$i)$p[$campo]=trim((string)$fila[$i]);$filas[]=ojsc_producto($p);
        }
        $offset=ftell($f);$fin=feof($f)||$offset===(int)$r['bytes'];fclose($f);$f=null;
        $total=(int)$c['filas']+count($filas);if($total>100000)throw new RuntimeException('El CSV supera 100000 productos.');
        if($filas){
            $valores=[];$marcas=[];$ceros=0;
            foreach($filas as $p){$marcas[]='(?,?,?,?,?,?,?,?,?,?)';array_push($valores,(int)$r['carga_id'],$p['id'],$p['modelo'],$p['marca'],$p['titulo'],$p['costo_usd'],$p['veracruz'],$p['categoria'],$p['subcategoria'],$p['familia']);if((float)$p['costo_usd']==0)++$ceros;}
            $s=$pdo->prepare('INSERT INTO cotizador_syscom_productos (carga_id,producto_id,modelo,marca,titulo,costo_usd,veracruz,categoria,subcategoria,familia) VALUES '.implode(',',$marcas));$s->execute($valores);
            $s=$pdo->prepare('UPDATE cotizador_syscom_cargas SET filas=filas+?,sin_precio=sin_precio+?,ultimo_lote=ultimo_lote+1,hash_lote=?,actualizado_en=NOW() WHERE id=? AND usuario_id=?');$s->execute([count($filas),$ceros,hash('sha256',json_encode($filas,JSON_THROW_ON_ERROR)),(int)$r['carga_id'],$u]);
        }
        if($fin){
            if(!$total)throw new RuntimeException('El CSV no contiene productos. No se activó la descarga.');
            $s=$pdo->prepare('SELECT COUNT(*) FROM cotizador_syscom_productos WHERE carga_id=?');$s->execute([(int)$r['carga_id']]);if((int)$s->fetchColumn()!==$total)throw new RuntimeException('No coincide el total de productos. No se activó la descarga.');
            $s=$pdo->prepare("UPDATE cotizador_syscom_cargas SET estado='listo',actualizado_en=NOW() WHERE id=? AND usuario_id=?");$s->execute([(int)$r['carga_id'],$u]);
            $s=$pdo->prepare('DELETE FROM cotizador_syscom_productos WHERE carga_id IN (SELECT id FROM cotizador_syscom_cargas WHERE usuario_id=? AND id<>?)');$s->execute([$u,(int)$r['carga_id']]);
            $s=$pdo->prepare('DELETE FROM cotizador_syscom_cargas WHERE usuario_id=? AND id<>?');$s->execute([$u,(int)$r['carga_id']]);
            $s=$pdo->prepare("UPDATE cotizador_syscom_descargas SET estado='listo',offset_bytes=?,filas=?,ultimo_exito=?,ultimas_filas=?,mensaje='Catálogo actualizado correctamente desde el reporte 1758.',version=version+1,actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=?");$s->execute([$offset,$total,time(),$total,$u]);
        }else{
            $s=$pdo->prepare("UPDATE cotizador_syscom_descargas SET estado='importando',offset_bytes=?,filas=?,mensaje='Importación en curso; el catálogo anterior sigue activo.',version=version+1,actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=?");$s->execute([$offset,$total,$u]);
        }
        $pdo->commit();if($fin)ojau_borrar($ruta);
    }catch(Throwable $e){if(is_resource($f))fclose($f);if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function ojau_cancelar(PDO $pdo,int $u,int $v):void{
    $ruta=null;$pdo->beginTransaction();try{
        ojsc_lock_usuario($pdo,$u);$r=ojau_fila($pdo,$u,true);if(!$r)throw new RuntimeException('No hay una actualización pendiente.');ojau_version($r,$v);
        if(!in_array($r['estado'],['descargando','importando','pausado'],true))throw new RuntimeException('No hay una actualización pendiente que cancelar.',409);
        if($r['archivo_token']!=='')$ruta=ojau_ruta($u,$r['archivo_token']);
        if($r['carga_id']){
            $s=$pdo->prepare('SELECT estado FROM cotizador_syscom_cargas WHERE id=? AND usuario_id=? FOR UPDATE');$s->execute([(int)$r['carga_id'],$u]);$estado=$s->fetchColumn();
            if($estado==='cargando'){$s=$pdo->prepare('DELETE FROM cotizador_syscom_productos WHERE carga_id=?');$s->execute([(int)$r['carga_id']]);$s=$pdo->prepare('DELETE FROM cotizador_syscom_cargas WHERE id=? AND usuario_id=?');$s->execute([(int)$r['carga_id'],$u]);}
            elseif($estado==='listo')throw new RuntimeException('El catálogo ya terminó de actualizarse. Revisa el estado.',409);
        }
        $s=$pdo->prepare("UPDATE cotizador_syscom_descargas SET estado='cancelado',carga_id=NULL,mensaje='Actualización cancelada; se conserva el catálogo activo.',version=version+1,actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=?");$s->execute([$u]);$pdo->commit();ojau_borrar($ruta);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function ojau_pausar(PDO $pdo,int $u,int $v,string $mensaje):void{
    preg_match('/^.{0,480}/us',$mensaje,$m);$seguro=$m[0]??'Importación detenida; revisa el estado.';
    $s=$pdo->prepare("UPDATE cotizador_syscom_descargas SET estado='pausado',mensaje=?,version=version+1,actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=? AND version=? AND estado IN ('importando','pausado')");$s->execute([$seguro,$u,$v]);
}
