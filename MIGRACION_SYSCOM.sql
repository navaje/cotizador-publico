CREATE TABLE IF NOT EXISTS cotizador_syscom_cargas (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 usuario_id BIGINT UNSIGNED NOT NULL,
 archivo VARCHAR(200) NOT NULL,
 estado VARCHAR(16) NOT NULL DEFAULT 'cargando',
 filas INT UNSIGNED NOT NULL DEFAULT 0,
 sin_precio INT UNSIGNED NOT NULL DEFAULT 0,
 ultimo_lote INT UNSIGNED NOT NULL DEFAULT 0,
 hash_lote CHAR(64) NOT NULL DEFAULT '',
 creado_en DATETIME NOT NULL,
 actualizado_en DATETIME NOT NULL,
 INDEX idx_syscom_usuario (usuario_id,estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cotizador_syscom_productos (
 carga_id BIGINT UNSIGNED NOT NULL,
 producto_id BIGINT UNSIGNED NOT NULL,
 modelo VARCHAR(100) NOT NULL,
 marca VARCHAR(100) NOT NULL,
 titulo VARCHAR(1024) NOT NULL,
 costo_usd DECIMAL(14,4) NOT NULL,
 veracruz INT UNSIGNED NOT NULL,
 categoria VARCHAR(150) NOT NULL,
 subcategoria VARCHAR(150) NOT NULL,
 familia VARCHAR(150) NOT NULL,
 PRIMARY KEY (carga_id,producto_id),
 INDEX idx_syscom_modelo (carga_id,modelo),
 INDEX idx_syscom_categoria (carga_id,categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
