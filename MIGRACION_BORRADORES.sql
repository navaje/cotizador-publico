-- Solo si tu usuario de PHP no puede crear la tabla automáticamente.
-- Ejecutar en la base de datos actual del cotizador desde phpMyAdmin.
CREATE TABLE IF NOT EXISTS cotizador_borradores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    datos LONGTEXT NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    documento_id BIGINT UNSIGNED NULL,
    creado_en DATETIME NOT NULL,
    actualizado_en DATETIME NOT NULL,
    INDEX idx_borrador_usuario (usuario_id, actualizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
