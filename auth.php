<?php
require_once __DIR__.'/security.php';
require_once __DIR__.'/db.php';
function oj_logout_expired(): void {
    session_unset();session_destroy();header('Location: /cotizador/login.php?expired=1');exit;
}
if(empty($_SESSION['usuario_id'])){header('Location: /cotizador/login.php');exit;}
if(!empty($_SESSION['ultima_actividad']) && time()-(int)$_SESSION['ultima_actividad']>28800)oj_logout_expired();
$stmt=$pdo->prepare('SELECT nombre,rol,password_hash FROM usuarios WHERE id=? AND activo=1 LIMIT 1');
$stmt->execute([(int)$_SESSION['usuario_id']]);$oj_current=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$oj_current || empty($_SESSION['usuario_password_fingerprint']) || !hash_equals($_SESSION['usuario_password_fingerprint'],hash('sha256',$oj_current['password_hash'])))oj_logout_expired();
$_SESSION['usuario_nombre']=$oj_current['nombre'];$_SESSION['usuario_rol']=$oj_current['rol'];$_SESSION['ultima_actividad']=time();
unset($oj_current);
