<?php
function oj_users_install(PDO $pdo): void {
    try {
        $pdo->query('SELECT usuario_id, email FROM oj_usuario_contacto LIMIT 0');
        $pdo->query('SELECT token_hash, expires_at, used_at FROM oj_password_tokens LIMIT 0');
        $pdo->query('SELECT clave, ventana, intentos FROM oj_acceso_intentos LIMIT 0');
        return;
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') throw $e;
    }
    foreach (oj_users_schema() as $sql) $pdo->exec($sql);
}
function oj_users_schema(): array {
    return [
        "CREATE TABLE IF NOT EXISTS oj_usuario_contacto (usuario_id BIGINT NOT NULL PRIMARY KEY, email VARCHAR(190) NOT NULL, UNIQUE KEY email_unico (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS oj_password_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id BIGINT NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL, UNIQUE KEY token_unico (token_hash), KEY usuario (usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS oj_acceso_intentos (clave CHAR(64) PRIMARY KEY, ventana BIGINT NOT NULL, intentos INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
}
