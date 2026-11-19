CREATE TABLE IF NOT EXISTS oj_planos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 usuario_id BIGINT UNSIGNED NOT NULL,
 visita_id BIGINT UNSIGNED NULL,
 cotizacion_id BIGINT UNSIGNED NULL,
 archivo VARCHAR(80) NOT NULL,
 creado_en DATETIME NOT NULL,
 UNIQUE KEY idx_ojp_archivo (archivo),
 INDEX idx_ojp_usuario (usuario_id, creado_en),
 INDEX idx_ojp_visita (visita_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
