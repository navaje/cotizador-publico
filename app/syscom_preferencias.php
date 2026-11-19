<?php
function ojsp_schema(PDO $pdo): void {
    try { $pdo->query('SELECT usuario_id FROM cotizador_syscom_preferencias LIMIT 0');return; }
    catch (PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1146) throw $e; }
    $pdo->exec(file_get_contents(dirname(__DIR__).'/MIGRACION_PARAMETROS_SYSCOM.sql'));
}
function ojsp_datos(array $entrada): array {
    $tc=$entrada['tipo_cambio']??null;$metodo=$entrada['metodo']??null;$pct=$entrada['porcentaje']??null;
    if (!is_string($tc)||!preg_match('/^\d{1,3}(?:\.\d{1,4})?$/D',$tc)||(float)$tc<=0) throw new InvalidArgumentException('Captura un tipo de cambio mayor que cero y hasta 999.9999, con máximo cuatro decimales.');
    if (!is_string($metodo)||!in_array($metodo,['margen','recargo'],true)) throw new InvalidArgumentException('Selecciona Margen o Recargo.');
    if (!is_string($pct)||!preg_match('/^\d{1,5}(?:\.\d{1,2})?$/D',$pct)||(float)$pct>10000||($metodo==='margen'&&(float)$pct>=100)) throw new InvalidArgumentException('Revisa el porcentaje: margen menor a 100%; recargo hasta 10000%, con máximo dos decimales.');
    return ['tipo_cambio'=>$tc,'metodo'=>$metodo,'porcentaje'=>$pct];
}
function ojsp_leer(PDO $pdo,int $usuario): ?array {
    $stmt=$pdo->prepare('SELECT tipo_cambio,metodo,porcentaje,version,actualizado_en FROM cotizador_syscom_preferencias WHERE usuario_id=?');
    $stmt->execute([$usuario]);$r=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;
    $r['version']=(int)$r['version'];
    $fecha=new DateTimeImmutable($r['actualizado_en'],new DateTimeZone('UTC'));
    $r['fecha_local']=$fecha->setTimezone(new DateTimeZone('America/Mexico_City'))->format('d/m/Y H:i');
    return $r;
}
function ojsp_guardar(PDO $pdo,int $usuario,int $version,array $datos): void {
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT id FROM usuarios WHERE id=? FOR UPDATE');$stmt->execute([$usuario]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Usuario no disponible.');
        $stmt=$pdo->prepare('SELECT version FROM cotizador_syscom_preferencias WHERE usuario_id=? FOR UPDATE');$stmt->execute([$usuario]);$actual=$stmt->fetchColumn();
        if (($actual===false&&$version!==0)||($actual!==false&&(int)$actual!==$version)) throw new RuntimeException('Tus parámetros cambiaron en otra pestaña. Puedes conservar los valores actuales o pulsar Aplicar guardados para cargar la versión más reciente.',409);
        if ($actual===false) {
            $stmt=$pdo->prepare('INSERT INTO cotizador_syscom_preferencias (tipo_cambio,metodo,porcentaje,usuario_id,version,actualizado_en) VALUES (?,?,?,?,1,UTC_TIMESTAMP())');
            $stmt->execute([$datos['tipo_cambio'],$datos['metodo'],$datos['porcentaje'],$usuario]);
        } else {
            $stmt=$pdo->prepare('UPDATE cotizador_syscom_preferencias SET tipo_cambio=?,metodo=?,porcentaje=?,version=version+1,actualizado_en=UTC_TIMESTAMP() WHERE usuario_id=? AND version=?');
            $stmt->execute([$datos['tipo_cambio'],$datos['metodo'],$datos['porcentaje'],$usuario,$version]);
            if ($stmt->rowCount()!==1) throw new RuntimeException('Los parámetros cambiaron. Pulsa Aplicar guardados para cargar la versión actual.',409);
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack();throw $e; }
}
