<?php
function ojsc_schema(PDO $pdo): void {
    try {
        $pdo->query('SELECT id FROM cotizador_syscom_cargas LIMIT 0');
        $pdo->query('SELECT producto_id FROM cotizador_syscom_productos LIMIT 0');
        return;
    } catch (PDOException $e) { if ((int)($e->errorInfo[1] ?? 0) !== 1146) throw $e; }
    foreach (explode(';',file_get_contents(dirname(__DIR__).'/MIGRACION_SYSCOM.sql')) as $sql) {
        if (trim($sql) !== '') $pdo->exec($sql);
    }
}
function ojsc_texto($valor, int $max): string {
    if (!is_string($valor) || strlen($valor)>$max*4 || preg_match('//u',$valor)!==1 || strpos($valor,"\0")!==false) throw new InvalidArgumentException('Uno de los campos de texto no es válido.');
    $valor = trim($valor);
    $len = function_exists('mb_strlen') ? mb_strlen($valor,'UTF-8') : preg_match_all('/./us',$valor);
    if ($len>$max) throw new InvalidArgumentException('Un campo supera la longitud admitida.');
    return $valor;
}
function ojsc_int($valor, int $min=1, int $max=2147483647): int {
    if (!is_scalar($valor)) throw new InvalidArgumentException('Número no válido.');
    $n = filter_var($valor,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);
    if ($n===false) throw new InvalidArgumentException('Número fuera de rango.');
    return $n;
}
function ojsc_producto(array $fila): array {
    $p = ['id'=>ojsc_int($fila['id']??null),'modelo'=>ojsc_texto($fila['modelo']??null,100)];
    if ($p['modelo']==='') throw new InvalidArgumentException('Un producto no tiene modelo.');
    foreach (['marca'=>100,'titulo'=>1024,'categoria'=>150,'subcategoria'=>150,'familia'=>150] as $campo=>$max) $p[$campo]=ojsc_texto($fila[$campo]??null,$max);
    $p['costo_usd']=ojsc_texto($fila['costo_usd']??null,15);
    if (!preg_match('/^\d{1,10}(?:\.\d{1,4})?$/D',$p['costo_usd'])) throw new InvalidArgumentException('Un costo USD no es válido.');
    $p['veracruz']=ojsc_int($fila['veracruz']??null,0,999999999);
    return $p;
}
function ojsc_lock_usuario(PDO $pdo, int $usuario): void {
    $stmt=$pdo->prepare('SELECT id FROM usuarios WHERE id=? FOR UPDATE'); $stmt->execute([$usuario]);
    if (!$stmt->fetchColumn()) throw new RuntimeException('Usuario no disponible.');
}
function ojsc_carga(PDO $pdo, int $id, int $usuario): array {
    $stmt=$pdo->prepare('SELECT * FROM cotizador_syscom_cargas WHERE id=? AND usuario_id=? FOR UPDATE');
    $stmt->execute([$id,$usuario]); $r=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) throw new InvalidArgumentException('Importación no disponible para tu usuario.');
    return $r;
}
function ojsc_actual(PDO $pdo, int $usuario): ?array {
    $stmt=$pdo->prepare("SELECT id,archivo,filas,sin_precio,actualizado_en FROM cotizador_syscom_cargas WHERE usuario_id=? AND estado='listo' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$usuario]); $r=$stmt->fetch(PDO::FETCH_ASSOC); return $r?:null;
}
function ojsc_iniciar(PDO $pdo,int $usuario,string $archivo): array {
    $pdo->beginTransaction();
    try {
        ojsc_lock_usuario($pdo,$usuario);
        $stmt=$pdo->prepare("SELECT id FROM cotizador_syscom_cargas WHERE usuario_id=? AND estado='cargando' LIMIT 1"); $stmt->execute([$usuario]);
        if ($stmt->fetchColumn()) throw new RuntimeException('Tienes una importación pendiente. Cancélala antes de comenzar otra.',409);
        $stmt=$pdo->prepare("INSERT INTO cotizador_syscom_cargas (usuario_id,archivo,estado,creado_en,actualizado_en) VALUES (?,?,'cargando',NOW(),NOW())");
        $stmt->execute([$usuario,$archivo]);$id=(int)$pdo->lastInsertId();$pdo->commit();return ['id'=>$id];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack();throw $e; }
}
function ojsc_lote(PDO $pdo,int $usuario,int $id,int $numero,string $json): array {
    if (strlen($json)>500000) throw new InvalidArgumentException('El lote es demasiado grande.');
    $filas=json_decode($json,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($filas) || count($filas)<1 || count($filas)>200) throw new InvalidArgumentException('Revisa el tamaño del lote.');
    $productos=[];
    foreach ($filas as $fila) {
        if (!is_array($fila)) throw new InvalidArgumentException('Producto no válido.');
        $productos[]=ojsc_producto($fila);
    }
    $hash=hash('sha256',$json);
    $pdo->beginTransaction();
    try {
        $c=ojsc_carga($pdo,$id,$usuario);
        if ($c['estado']!=='cargando') throw new RuntimeException('La importación ya no está abierta.',409);
        if ((int)$c['ultimo_lote']===$numero && hash_equals($c['hash_lote'],$hash)) { $pdo->commit();return ['filas'=>(int)$c['filas']]; }
        if ((int)$c['ultimo_lote']+1!==$numero) throw new RuntimeException('La secuencia de importación cambió. Cancela y vuelve a seleccionar el archivo.',409);
        if ((int)$c['filas']+count($productos)>100000) throw new InvalidArgumentException('Máximo 100000 productos por archivo.');
        $valores=[];$marcas=[];$ceros=0;
        foreach ($productos as $p) {
            $marcas[]='(?,?,?,?,?,?,?,?,?,?)';
            array_push($valores,$id,$p['id'],$p['modelo'],$p['marca'],$p['titulo'],$p['costo_usd'],$p['veracruz'],$p['categoria'],$p['subcategoria'],$p['familia']);
            if ((float)$p['costo_usd']==0) $ceros++;
        }
        $stmt=$pdo->prepare('INSERT INTO cotizador_syscom_productos (carga_id,producto_id,modelo,marca,titulo,costo_usd,veracruz,categoria,subcategoria,familia) VALUES '.implode(',',$marcas));
        $stmt->execute($valores);
        $stmt=$pdo->prepare('UPDATE cotizador_syscom_cargas SET filas=filas+?,sin_precio=sin_precio+?,ultimo_lote=?,hash_lote=?,actualizado_en=NOW() WHERE id=? AND usuario_id=?');
        $stmt->execute([count($productos),$ceros,$numero,$hash,$id,$usuario]);
        $pdo->commit();return ['filas'=>(int)$c['filas']+count($productos)];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack();throw $e; }
}
function ojsc_finalizar(PDO $pdo,int $usuario,int $id,int $esperados): array {
    $pdo->beginTransaction();
    try {
        ojsc_lock_usuario($pdo,$usuario);$c=ojsc_carga($pdo,$id,$usuario);
        if ($c['estado']==='listo') { $pdo->commit();return ['filas'=>(int)$c['filas']]; }
        if ($c['estado']!=='cargando' || (int)$c['filas']!==$esperados) throw new RuntimeException('La importación está incompleta. No se reemplazó el catálogo anterior.',409);
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM cotizador_syscom_productos WHERE carga_id=?');$stmt->execute([$id]);
        if ((int)$stmt->fetchColumn()!==$esperados) throw new RuntimeException('No coincide la cantidad de productos. No se activó la importación.',409);
        $stmt=$pdo->prepare("UPDATE cotizador_syscom_cargas SET estado='listo',actualizado_en=NOW() WHERE id=? AND usuario_id=?");$stmt->execute([$id,$usuario]);
        $stmt=$pdo->prepare('DELETE FROM cotizador_syscom_productos WHERE carga_id IN (SELECT id FROM cotizador_syscom_cargas WHERE usuario_id=? AND id<>?)');$stmt->execute([$usuario,$id]);
        $stmt=$pdo->prepare('DELETE FROM cotizador_syscom_cargas WHERE usuario_id=? AND id<>?');$stmt->execute([$usuario,$id]);
        $pdo->commit();return ['filas'=>$esperados];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack();throw $e; }
}
function ojsc_cancelar(PDO $pdo,int $usuario,int $id): void {
    $pdo->beginTransaction();
    try {
        ojsc_lock_usuario($pdo,$usuario);$c=ojsc_carga($pdo,$id,$usuario);
        if ($c['estado']!=='cargando') throw new RuntimeException('Sólo se puede cancelar una importación pendiente.',409);
        $stmt=$pdo->prepare('DELETE FROM cotizador_syscom_productos WHERE carga_id=?');$stmt->execute([$id]);
        $stmt=$pdo->prepare('DELETE FROM cotizador_syscom_cargas WHERE id=? AND usuario_id=?');$stmt->execute([$id,$usuario]);$pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack();throw $e; }
}
