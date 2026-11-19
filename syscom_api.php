<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/app/syscom.php';
require_post_csrf();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function ojsc_json(array $datos): void { $datos['api_version']='5.12.1';echo json_encode($datos,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit; }
try {
    ojsc_schema($pdo);$usuario=(int)$_SESSION['usuario_id'];
    $accion=ojsc_texto($_POST['accion']??'',20);
    if ($accion==='estado') {
        $activa=ojsc_actual($pdo,$usuario);
        $stmt=$pdo->prepare("SELECT id,archivo,filas FROM cotizador_syscom_cargas WHERE usuario_id=? AND estado='cargando' ORDER BY id DESC LIMIT 1");$stmt->execute([$usuario]);$pendiente=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
        $categorias=[];
        if ($activa) { $stmt=$pdo->prepare("SELECT DISTINCT categoria FROM cotizador_syscom_productos WHERE carga_id=? AND categoria<>'' ORDER BY categoria");$stmt->execute([(int)$activa['id']]);$categorias=$stmt->fetchAll(PDO::FETCH_COLUMN); }
        ojsc_json(['activa'=>$activa,'pendiente'=>$pendiente,'categorias'=>$categorias]);
    }
    if ($accion==='iniciar') {
        $archivo=ojsc_texto($_POST['archivo']??'',200);
        if ($archivo==='' || strtolower(pathinfo($archivo,PATHINFO_EXTENSION))!=='csv') throw new InvalidArgumentException('Selecciona un archivo CSV de SYSCOM.');
        ojsc_json(ojsc_iniciar($pdo,$usuario,$archivo));
    }
    if (in_array($accion,['lote','finalizar','cancelar'],true)) {
        $id=ojsc_int($_POST['id']??null);
        if ($accion==='lote') {
            if(isset($_POST['filas_b64'])) {
                $codificado=$_POST['filas_b64'];
                if(!is_string($codificado)||strlen($codificado)>700000)throw new InvalidArgumentException('Lote codificado no válido.');
                $json=base64_decode($codificado,true);
            } else $json=$_POST['filas']??null;
            if (!is_string($json)) throw new InvalidArgumentException('Lote no válido.');
            ojsc_json(ojsc_lote($pdo,$usuario,$id,ojsc_int($_POST['numero']??null,1,1000),$json));
        }
        if ($accion==='finalizar') ojsc_json(ojsc_finalizar($pdo,$usuario,$id,ojsc_int($_POST['esperados']??null,1,100000)));
        ojsc_cancelar($pdo,$usuario,$id);ojsc_json(['cancelada'=>true]);
    }
    if ($accion==='buscar' || $accion==='producto') {
        $activa=ojsc_actual($pdo,$usuario);
        if (!$activa) throw new InvalidArgumentException('Primero importa tu catálogo desde Importar catálogo SYSCOM.');
        $carga=(int)$activa['id'];
        $select='SELECT producto_id AS id,modelo,marca,titulo,costo_usd,veracruz,categoria,subcategoria,familia FROM cotizador_syscom_productos';
        if ($accion==='producto') {
            if (ojsc_int($_POST['carga']??null)!==$carga) throw new RuntimeException('El catálogo se actualizó. Busca de nuevo el producto antes de seleccionarlo.',409);
            $stmt=$pdo->prepare($select.' WHERE carga_id=? AND producto_id=?');$stmt->execute([$carga,ojsc_int($_POST['producto']??null)]);
            $p=$stmt->fetch(PDO::FETCH_ASSOC);if (!$p) throw new InvalidArgumentException('Producto no disponible en este catálogo.');
            ojsc_json(['producto'=>$p,'activa'=>$activa]);
        }
        $q=ojsc_texto($_POST['q']??'',100);$categoria=ojsc_texto($_POST['categoria']??'',150);$veracruz=ojsc_texto($_POST['veracruz']??'0',1);
        if (!in_array($veracruz,['0','1'],true)) throw new InvalidArgumentException('Filtro de existencia no válido.');
        if (strlen($q)<2 && $categoria==='' && $veracruz!=='1') throw new InvalidArgumentException('Escribe al menos dos caracteres o elige una categoría/existencia.');
        $where=['carga_id=:carga'];$params=[':carga'=>$carga,':modelo'=>$q];
        if ($q!=='') {
            $patron='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$partes=[];
            foreach (['modelo','titulo','marca'] as $i=>$campo) { $partes[]=$campo." LIKE :q".$i." ESCAPE '!'";$params[':q'.$i]=$patron; }
            $where[]='('.implode(' OR ',$partes).')';
        }
        if ($categoria!=='') { $where[]='categoria=:categoria';$params[':categoria']=$categoria; }
        if ($veracruz==='1') $where[]='veracruz>0';
        $stmt=$pdo->prepare($select.' WHERE '.implode(' AND ',$where).' ORDER BY (modelo=:modelo) DESC,modelo ASC,producto_id ASC LIMIT 26');$stmt->execute($params);$items=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $mas=count($items)>25;ojsc_json(['items'=>array_slice($items,0,25),'hay_mas'=>$mas,'activa'=>$activa]);
    }
    throw new InvalidArgumentException('Acción no disponible.');
} catch (Throwable $e) {
    $codigo=$e instanceof PDOException ? 500 : ($e->getCode()===409 ? 409 : 422);
    $mensaje=$e instanceof PDOException ? 'No se pudo acceder al catálogo. Revisa la instalación de sus tablas y el registro del hosting.' : $e->getMessage();
    if ($e instanceof PDOException) {
        error_log('SYSCOM: '.$e->getMessage());
        if ((int)($e->errorInfo[1]??0)===1062) { $codigo=409;$mensaje='El archivo repite un ID de producto. No se activó esta importación; conserva el catálogo anterior. Cancela y revisa el archivo.'; }
    }
    if ($e instanceof JsonException) $mensaje='El lote de productos no tiene un formato JSON válido.';
    http_response_code($codigo);ojsc_json(['error'=>$mensaje]);
}
