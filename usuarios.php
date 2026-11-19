<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/app/usuarios.php';
oj_admin($pdo);
$error=''; $message=''; $ready=false; $edit=false; $rows=[]; $roles=[];
try {
    oj_users_install($pdo); $ready=true; $roles=oj_roles($pdo);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        require_post_csrf();
        $action=(string)($_POST['action']??''); $id=(int)($_POST['id']??0);
        $pdo->beginTransaction();
        // Serializa cambios y evita desactivar/degradar al último administrador.
        $all=$pdo->query('SELECT id, rol, activo FROM usuarios ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        $target=false; $admins=0;
        foreach($all as $u){if((int)$u['id']===$id)$target=$u;if($u['rol']==='admin' && $u['activo'])$admins++;}
        oj_admin($pdo);
        if($action==='create' || $action==='edit') {
            $name=trim((string)($_POST['nombre']??'')); $user=trim((string)($_POST['usuario']??''));
            $email=strtolower(trim((string)($_POST['email']??''))); $role=(string)($_POST['rol']??'');
            if($name==='' || strlen($name)>100 || !preg_match('/^[A-Za-z0-9_.@-]{3,50}$/D',$user)) throw new InvalidArgumentException('Escribe un nombre (máximo 100 bytes) y un usuario de 3 a 50 caracteres, sin espacios.');
            if(strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Escribe un correo válido para recuperar el acceso.');
            if(!in_array($role,$roles,true)) throw new InvalidArgumentException('Selecciona un rol válido.');
            if($action==='edit' && !$target) throw new InvalidArgumentException('Usuario no encontrado.');
            if($action==='edit' && $target['rol']==='admin' && $target['activo'] && $role!=='admin' && $admins<=1) throw new InvalidArgumentException('Debe permanecer al menos un administrador activo.');
            if($action==='edit' && $id===(int)$_SESSION['usuario_id'] && $role!=='admin') throw new InvalidArgumentException('No puedes quitarte el permiso de administrador desde tu propia sesión.');
            $s=$pdo->prepare('SELECT id FROM usuarios WHERE usuario=? AND id<>?');$s->execute([$user,$id]);
            if($s->fetch()) throw new InvalidArgumentException('Ese nombre de usuario ya existe.');
            $s=$pdo->prepare('SELECT usuario_id FROM oj_usuario_contacto WHERE email=? AND usuario_id<>?');$s->execute([$email,$id]);
            if($s->fetch()) throw new InvalidArgumentException('Ese correo ya pertenece a otra cuenta.');
            if($action==='create') {
                $pass=(string)($_POST['password']??''); $err=oj_password_error($pass,(string)($_POST['confirm']??''));
                if($err!=='')throw new InvalidArgumentException($err);
                $pdo->prepare('INSERT INTO usuarios (usuario,password_hash,nombre,rol,activo) VALUES (?,?,?,?,1)')->execute([$user,password_hash($pass,PASSWORD_DEFAULT),$name,$role]);
                $id=(int)$pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE usuarios SET usuario=?,nombre=?,rol=? WHERE id=?')->execute([$user,$name,$role,$id]);
                oj_invalidate_tokens($pdo,$id);
            }
            $pdo->prepare('INSERT INTO oj_usuario_contacto (usuario_id,email) VALUES (?,?) ON DUPLICATE KEY UPDATE email=VALUES(email)')->execute([$id,$email]);
            $message=$action==='create'?'Usuario creado. Ya puede iniciar sesión.':'Datos actualizados.';
        } elseif($action==='toggle') {
            if(!$target)throw new InvalidArgumentException('Usuario no encontrado.');
            if($id===(int)$_SESSION['usuario_id'])throw new InvalidArgumentException('No puedes desactivar tu propia cuenta.');
            if($target['activo'] && $target['rol']==='admin' && $admins<=1)throw new InvalidArgumentException('Debe permanecer al menos un administrador activo.');
            $pdo->prepare('UPDATE usuarios SET activo=? WHERE id=?')->execute([$target['activo']?0:1,$id]);
            oj_invalidate_tokens($pdo,$id); $message='Estado actualizado.';
        } elseif($action==='reset') {
            if(!$target || !$target['activo'])throw new InvalidArgumentException('Selecciona una cuenta activa.');
            $s=$pdo->prepare('SELECT email FROM oj_usuario_contacto WHERE usuario_id=?');$s->execute([$id]);$email=$s->fetchColumn();
            if(!$email)throw new InvalidArgumentException('Primero registra el correo con Editar.');
            $raw=bin2hex(random_bytes(32)); oj_invalidate_tokens($pdo,$id);
            $pdo->prepare('INSERT INTO oj_password_tokens (usuario_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))')->execute([$id,hash('sha256',$raw)]);
            $message='Enlace de recuperación enviado al correo registrado.';
        } else throw new InvalidArgumentException('Acción no válida.');
        $pdo->commit();
        if($action==='reset' && !oj_recovery_send($email,$raw)) {
            $pdo->prepare('DELETE FROM oj_password_tokens WHERE token_hash=?')->execute([hash('sha256',$raw)]);
            $message='';$error='No se pudo enviar el correo. Revisa FluentSMTP e inténtalo nuevamente.';
        }
        if($error===''){$_SESSION['oj_users_message']=$message;header('Location: usuarios.php');exit;}
    }
    $message=(string)($_SESSION['oj_users_message']??$message);unset($_SESSION['oj_users_message']);
    $rows=$pdo->query('SELECT u.id,u.usuario,u.nombre,u.rol,u.activo,c.email FROM usuarios u LEFT JOIN oj_usuario_contacto c ON c.usuario_id=u.id ORDER BY u.id')->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row)if((int)$row['id']===(int)($_GET['edit']??0))$edit=$row;
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    $error=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo completar la operación. Si es la primera instalación, importa MIGRACION_USUARIOS.sql en la base del cotizador.';
    if(!($e instanceof InvalidArgumentException))error_log('Ojo Jarocho usuarios: '.$e->getMessage());
    if($ready) {$rows=$pdo->query('SELECT u.id,u.usuario,u.nombre,u.rol,u.activo,c.email FROM usuarios u LEFT JOIN oj_usuario_contacto c ON c.usuario_id=u.id ORDER BY u.id')->fetchAll(PDO::FETCH_ASSOC);}
}
oj_page_start('Usuarios y acceso');
?>
<a class="btn secondary" href="dashboard.php">Volver al panel</a><a class="btn secondary" href="cambiar_password.php">Cambiar mi contraseña</a>
<p>Gestiona las cuentas del personal. Para recuperar el acceso, cada usuario necesita un correo propio registrado aquí.</p>
<?php if($message):?><p class="notice" role="status"><?=oj_h($message)?></p><?php endif;?>
<?php if($error):?><p class="error" role="alert"><?=oj_h($error)?></p><?php endif;?>
<?php if($ready):?>
<section><h2><?=$edit?'Editar cuenta':'Crear usuario'?></h2>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="<?=$edit?'edit':'create'?>"><input type="hidden" name="id" value="<?=$edit?(int)$edit['id']:0?>">
<div class="grid"><div><label for="nombre">Nombre</label><input id="nombre" name="nombre" required maxlength="100" value="<?=oj_h($edit['nombre']??'')?>"></div><div><label for="usuario">Usuario de acceso</label><input id="usuario" name="usuario" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.@-]{3,50}" value="<?=oj_h($edit['usuario']??'')?>" autocomplete="off"></div><div><label for="email">Correo de recuperación</label><input id="email" type="email" name="email" required maxlength="190" value="<?=oj_h($edit['email']??'')?>"></div><div><label for="rol">Rol</label><select id="rol" name="rol" required><?php foreach($roles as $role):?><option value="<?=oj_h($role)?>" <?=($edit?($edit['rol']===$role):($role===(array_values(array_filter($roles, function($r){return $r!=='admin';}))[0]??'admin')))?'selected':''?>><?=oj_h($role==='admin'?'Administrador':$role)?></option><?php endforeach;?></select><small>Administrador: gestiona cuentas y dispone de las funciones administrativas existentes. Los demás roles conservan los permisos del cotizador.</small></div></div>
<?php if(!$edit):?><div class="grid"><div><label for="password">Contraseña inicial</label><input id="password" type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></div><div><label for="confirm">Confirmar contraseña</label><input id="confirm" type="password" name="confirm" required minlength="12" maxlength="72" autocomplete="new-password"></div></div><small>Mínimo 12 caracteres. El usuario podrá cambiarla después de entrar.</small><?php endif;?>
<button type="submit"><?=$edit?'Guardar cambios':'Crear usuario'?></button><?php if($edit):?><a class="btn secondary" href="usuarios.php">Cancelar</a><?php endif;?></form></section>
<section><h2>Cuentas registradas</h2><div class="scroll"><table><thead><tr><th>Nombre / usuario</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><?=oj_h($row['nombre'])?><small><?=oj_h($row['usuario'])?></small></td><td><?=oj_h($row['email']?:'Sin correo: editar')?></td><td><?=oj_h($row['rol'])?></td><td><?=$row['activo']?'Activo':'Inactivo'?></td><td><a href="usuarios.php?edit=<?=(int)$row['id']?>">Editar</a>
<?php if($row['activo'] && $row['email']):?><form class="inline" method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button name="action" value="reset">Enviar recuperación</button></form><?php endif;?>
<?php if((int)$row['id']!==(int)$_SESSION['usuario_id']):?><form class="inline" method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="<?=$row['activo']?'danger':'secondary'?>" name="action" value="toggle"><?=$row['activo']?'Desactivar':'Activar'?></button></form><?php endif;?></td></tr><?php endforeach;?>
</tbody></table></div></section><?php endif;oj_page_end();?>
