-- Solo si el hosting no puede crear esta tabla automáticamente.
-- Ejecutar en la base de datos actual del cotizador. No modifica tablas existentes.
CREATE TABLE IF NOT EXISTS cotizacion_descuentos (
        item_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        precio_original DECIMAL(12,2) NOT NULL,
        porcentaje DECIMAL(5,2) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
