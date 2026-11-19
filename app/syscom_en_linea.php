<?php
// Contrato observado en la consulta real de un producto de esta cuenta.
function ojso_producto_en_linea(array $r,string $id): array {
    $recibido=$r['producto_id']??null;
    if ((!is_string($recibido)&&!is_int($recibido))||(string)$recibido!==$id) throw new RuntimeException('SYSCOM respondió con un producto distinto al solicitado. No se sustituyó el costo.');
    $precios=$r['precios']??null;
    $costo=is_array($precios)?($precios['precio_descuento']??null):null;
    if ((!is_string($costo)&&!is_int($costo)&&!is_float($costo))||!preg_match('/^\d{1,10}(?:\.\d{1,4})?$/D',(string)$costo)||(float)$costo<=0) throw new RuntimeException('SYSCOM no devolvió un costo de cuenta mayor que cero y utilizable. No se sustituyó por el precio de lista ni por otro precio.');
    $modelo=$r['modelo']??null;
    if(!is_string($modelo)||trim($modelo)===''||strlen($modelo)>800)throw new RuntimeException('La respuesta de producto no contiene un modelo válido.');
    $stock=$r['existencia']['detalle']['nuevo']['veracruz']??null;
    if($stock!==null){
        if((!is_string($stock)&&!is_int($stock))||!preg_match('/^\d{1,9}$/D',(string)$stock))throw new RuntimeException('La existencia Veracruz recibida no es válida.');
        $stock=(int)$stock;
    }
    $texto=static function($valor,int $limite):string{
        if(!is_string($valor))return '';
        preg_match('/^.{0,'.$limite.'}/us',trim($valor),$m);return $m[0]??'';
    };
    $ahora=new DateTimeImmutable('now',new DateTimeZone('America/Mexico_City'));
    // Sólo devuelve los campos necesarios; excluye enlaces privados y credenciales.
    return ['id'=>$id,'modelo'=>$texto($modelo,200),'marca'=>$texto($r['marca']??'',100),'titulo'=>$texto($r['titulo']??'',1024),'costo_usd'=>(string)$costo,'veracruz'=>$stock,'fuente'=>'api','fecha_local'=>$ahora->format('d/m/Y H:i:s'),'consultado_en'=>$ahora->format(DATE_ATOM)];
}
