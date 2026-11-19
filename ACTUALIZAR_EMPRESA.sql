-- Ejecutar una sola vez en phpMyAdmin después de respaldar la base de datos.
-- Conserva la dirección registrada actualmente.
UPDATE empresa
SET nombre = 'Ojo Jarocho',
    telefono = '+52 999 918 5208',
    email = 'ventas@ojojarocho.online',
    web = 'https://ojojarocho.online',
    logo = 'assets/logo.png'
ORDER BY id ASC
LIMIT 1;

-- Tabla requerida para generar consecutivos sin repetir folios existentes.
-- Vincula una cotización o nota con una solicitud de servicio.
ALTER TABLE cotizaciones
    ADD COLUMN solicitud_id INT NULL DEFAULT NULL AFTER estado_pago;

CREATE INDEX idx_cotizaciones_solicitud_id
    ON cotizaciones (solicitud_id);

-- Crea el módulo de solicitudes si todavía no existe.
CREATE TABLE IF NOT EXISTS solicitudes_servicio (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    folio VARCHAR(50) NOT NULL,
    cliente_nombre VARCHAR(150) NOT NULL,
    cliente_telefono VARCHAR(30) NOT NULL,
    cliente_email VARCHAR(150) NULL,
    direccion TEXT NOT NULL,
    persona_atiende VARCHAR(150) NULL,
    tipo_equipo VARCHAR(100) NULL,
    marca VARCHAR(100) NULL,
    modelo VARCHAR(120) NULL,
    numero_serie VARCHAR(120) NULL,
    tipo_servicio VARCHAR(30) NOT NULL DEFAULT 'revision',
    falla_reportada TEXT NOT NULL,
    fecha_preferida DATE NULL,
    horario_preferido VARCHAR(80) NULL,
    observaciones TEXT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'nuevo',
    origen VARCHAR(30) NOT NULL DEFAULT 'web',
    producto_nombre VARCHAR(255) NULL,
    producto_sku VARCHAR(100) NULL,
    producto_url VARCHAR(500) NULL,
    proxima_accion VARCHAR(255) NULL,
    fecha_seguimiento DATETIME NULL,
    notas_internas TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_solicitudes_folio (folio),
    KEY idx_solicitudes_estado (estado),
    KEY idx_solicitudes_fecha (created_at),
    KEY idx_solicitudes_telefono (cliente_telefono),
    KEY idx_solicitudes_seguimiento (fecha_seguimiento),
    KEY idx_solicitudes_origen (origen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Amplía los estados comerciales si la tabla ya existía.
ALTER TABLE solicitudes_servicio
    MODIFY estado VARCHAR(30) NOT NULL DEFAULT 'nuevo';

UPDATE solicitudes_servicio SET estado = 'visita_programada' WHERE estado = 'programado';
UPDATE solicitudes_servicio SET estado = 'perdido' WHERE estado = 'cancelado';

CREATE TABLE IF NOT EXISTS folios (
    tipo VARCHAR(30) NOT NULL,
    consecutivo INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO folios (tipo, consecutivo)
SELECT 'cotizacion', COALESCE(MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)), 0)
FROM cotizaciones
WHERE tipo_documento = 'cotizacion' AND folio REGEXP '^COT-[0-9]+$'
ON DUPLICATE KEY UPDATE consecutivo = GREATEST(consecutivo, VALUES(consecutivo));

INSERT INTO folios (tipo, consecutivo)
SELECT 'nota_venta', COALESCE(MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)), 0)
FROM cotizaciones
WHERE tipo_documento = 'nota_venta' AND folio REGEXP '^NV-[0-9]+$'
ON DUPLICATE KEY UPDATE consecutivo = GREATEST(consecutivo, VALUES(consecutivo));

INSERT INTO folios (tipo, consecutivo)
SELECT 'nota_servicio', COALESCE(MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)), 0)
FROM cotizaciones
WHERE tipo_documento = 'nota_servicio' AND folio REGEXP '^NS-[0-9]+$'
ON DUPLICATE KEY UPDATE consecutivo = GREATEST(consecutivo, VALUES(consecutivo));
