-- Solo si no se permite crear la tabla automáticamente. Usar la BD actual del cotizador.
CREATE TABLE IF NOT EXISTS cotizador_clientes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario_id BIGINT UNSIGNED NOT NULL,
        nombre VARCHAR(180) NOT NULL,
        telefono VARCHAR(60) NOT NULL DEFAULT '',
        email VARCHAR(254) NOT NULL DEFAULT '',
        direccion TEXT NOT NULL,
        activo TINYINT NOT NULL DEFAULT 1,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        creado_en DATETIME NOT NULL,
        actualizado_en DATETIME NOT NULL,
        INDEX idx_clientes_usuario (usuario_id, activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
