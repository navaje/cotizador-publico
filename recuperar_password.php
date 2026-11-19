<?php
require_once __DIR__.'/security.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/app/usuarios.php';
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_post_csrf();
    $email=strtolower(trim((string)($_POST['email']??'')));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190){$error='Escribe un correo válido.';}
    else {
        $message='Si el correo corresponde a una cuenta activa, recibirás un enlace para restablecer tu contraseña. Revisa también la carpeta de spam.';
        try {
            $ipOk=oj_limit($pdo,'recovery_ip',(string)($_SERVER['REMOTE_ADDR']??''),10);
            $emailOk=oj_limit($pdo,'recovery_email',$email,3);
            if($ipOk && $emailOk){
                $s=$pdo->prepare('SELECT u.id FROM usuarios u JOIN oj_usuario_contacto c ON c.usuario_id=u.id WHERE c.email=? AND u.activo=1');$s->execute([$email]);$id=$s->fetchColumn();
                if($id){
                    $raw=bin2hex(random_bytes(32));
                    $pdo->beginTransaction();
                    $s=$pdo->prepare('SELECT id FROM usuarios WHERE id=? AND activo=1 FOR UPDATE');$s->execute([$id]);
                    if($s->fetchColumn()){
                        oj_invalidate_tokens($pdo,(int)$id);
                        $pdo->prepare('INSERT INTO oj_password_tokens (usuario_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))')->execute([$id,hash('sha256',$raw)]);
                        $pdo->commit();
                        if(!oj_recovery_send($email,$raw)){
                            $pdo->prepare('DELETE FROM oj_password_tokens WHERE token_hash=?')->execute([hash('sha256',$raw)]);
                            error_log('Ojo Jarocho: no se pudo enviar un correo de recuperación.');
                        }
                    }else{$pdo->rollBack();}
                }
            }
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Ojo Jarocho recuperación: '.$e->getMessage());}
    }
}
oj_page_start('Recuperar contraseña');
?>
<p>Escribe el correo que el administrador registró para tu cuenta. El enlace vence en 30 minutos.</p>
<?php if($message):?><p class="notice" role="status"><?=oj_h($message)?></p><?php endif;?>
<?php if($error):?><p class="error" role="alert"><?=oj_h($error)?></p><?php endif;?>
<form class="narrow" method="post"><?=csrf_field()?><label for="email">Correo de recuperación</label><input id="email" type="email" name="email" required maxlength="190" autocomplete="email"><button type="submit">Solicitar enlace</button><a class="btn secondary" href="login.php">Volver al acceso</a></form>
<p>Si aún no tienes un correo registrado, pide al administrador que lo agregue desde Usuarios y acceso.</p>
<?php oj_page_end();?>
