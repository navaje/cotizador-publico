<?php
function ojv_origin(PDO $pdo,int $draft,int $user): string {
    try{
        $q=$pdo->prepare('SELECT v.id,x.version FROM oj_visita_cotizaciones x JOIN oj_visitas v ON v.id=x.visita_id AND v.usuario_id=? JOIN cotizador_borradores b ON b.id=x.borrador_id AND b.usuario_id=? WHERE x.borrador_id=?');$q->execute([$user,$user,$draft]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return '';
        return '<div class="section" role="status"><h2>Propuesta desde visita técnica</h2><p>Origen: <a href="visitas.php?id='.(int)$r['id'].'">VT-'.str_pad((string)$r['id'],5,'0',STR_PAD_LEFT).'</a> · versión '.(int)$r['version'].'. Revisa conceptos, cantidades, precios y condiciones antes de emitir. Los conceptos se prepararon inicialmente sin precio.</p></div>';
    }catch(PDOException $e){return '';}
}
