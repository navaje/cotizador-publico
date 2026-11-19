CREATE TABLE IF NOT EXISTS cotizador_seguimiento (
    documento_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    estado VARCHAR(24) NOT NULL,
    proximo_contacto DATE NULL,
    nota TEXT NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    actualizado_por BIGINT UNSIGNED NOT NULL,
    actualizado_en DATETIME NOT NULL,
    INDEX idx_seguimiento_contacto (proximo_contacto, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
