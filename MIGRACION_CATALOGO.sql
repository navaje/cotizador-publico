-- Usar solo si la creación automática de la tabla no está permitida.
-- Ejecutar en la base de datos actual del cotizador.
CREATE TABLE IF NOT EXISTS cotizador_catalogo (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario_id BIGINT UNSIGNED NOT NULL,
        codigo VARCHAR(80) NOT NULL DEFAULT '',
        nombre VARCHAR(300) NOT NULL,
        categoria VARCHAR(20) NOT NULL,
        unidad VARCHAR(20) NOT NULL,
        precio DECIMAL(12,2) NOT NULL,
        activo TINYINT NOT NULL DEFAULT 1,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        creado_en DATETIME NOT NULL,
        actualizado_en DATETIME NOT NULL,
        INDEX idx_catalogo_usuario (usuario_id, activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
