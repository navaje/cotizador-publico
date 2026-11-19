<?php
function ojsb_parametros(array $datos): array {
    $q=$datos['q']??null;$tipo=$datos['tipo']??'texto';$stock=$datos['veracruz']??'0';$pagina=$datos['pagina']??'1';
    if(!is_string($q)||strlen($q)>480||preg_match('//u',$q)!==1||preg_match('/[\x00-\x1f\x7f]/',$q))throw new InvalidArgumentException('Escribe un modelo o texto válido.');
    $q=trim($q);$longitud=preg_match_all('/./us',$q);
    if($longitud<2||$longitud>120)throw new InvalidArgumentException('Escribe entre 2 y 120 caracteres para buscar en línea.');
    if(!is_string($tipo)||!in_array($tipo,['texto','modelo'],true))throw new InvalidArgumentException('Tipo de búsqueda no válido.');
    if(!is_string($stock)||!in_array($stock,['0','1'],true))throw new InvalidArgumentException('Filtro de existencia no válido.');
    if(!is_string($pagina)||!preg_match('/^[1-9]\d{0,3}$/D',$pagina)||(int)$pagina>1000)throw new InvalidArgumentException('Página de resultados no válida.');
    if($tipo==='texto'&&count(preg_split('/\s+/u',$q))>10)throw new InvalidArgumentException('Usa como máximo 10 palabras en la búsqueda.');
    $n=$tipo==='modelo'?1:(int)$pagina;
    $params=['moneda'=>'usd','iva'=>'false','financiero'=>'false','inventarios'=>'true','descripcion'=>'false','imagenes'=>'false','recursos'=>'false'];
    if($tipo==='modelo')$params['modelo']=$q;
    else{$params['busqueda']=$q;$params['pagina']=$n;$params['limit']=25;}
    if($stock==='1'){$params['sucursal']='veracruz';$params['stock_nuevo']='true';}
    return ['params'=>$params,'pagina'=>$n,'tipo'=>$tipo,'veracruz'=>$stock==='1'];
}
function ojsb_resultados(array $r,array $consulta): array {
    if(isset($r['productos'])&&is_array($r['productos']))$filas=$r['productos'];
    elseif(isset($r['producto_id']))$filas=[$r];
    elseif($r===[]||array_keys($r)===range(0,count($r)-1))$filas=$r;
    else throw new RuntimeException('SYSCOM devolvió una estructura de búsqueda no reconocida. Comparte este mensaje para revisar la integración.');
    if($filas!==[]&&array_keys($filas)!==range(0,count($filas)-1))throw new RuntimeException('La lista de productos recibida no tiene un formato válido.');
    $limite=$consulta['tipo']==='modelo'?100:25;
    if(count($filas)>$limite)throw new RuntimeException('SYSCOM devolvió más resultados que el límite solicitado. Revisa la integración antes de continuar.');
    $items=[];$omitidos=0;$sinStock=0;$ids=[];
    foreach($filas as $fila){
        if(!is_array($fila))throw new RuntimeException('La búsqueda contiene un producto con formato no reconocido.');
        $id=$fila['producto_id']??null;
        if((!is_string($id)&&!is_int($id))||!preg_match('/^[1-9]\d{0,8}$/D',(string)$id)){++$omitidos;continue;}
        if(isset($ids[(string)$id]))continue;
        $ids[(string)$id]=true;
        try{$p=ojso_producto_en_linea($fila,(string)$id);}
        catch(RuntimeException $e){++$omitidos;continue;}
        if($consulta['veracruz']&&($p['veracruz']===null||$p['veracruz']<=0)){++$sinStock;continue;}
        $items[]=$p;
    }
    $paginas=filter_var($r['paginas']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
    $mas=$consulta['tipo']!=='modelo'&&$consulta['pagina']<1000&&($paginas!==false?$consulta['pagina']<$paginas:count($filas)===25);
    return ['items'=>$items,'pagina'=>$consulta['pagina'],'hay_mas'=>$mas,'omitidos'=>$omitidos,'sin_stock'=>$sinStock,'fuente'=>'api'];
}
