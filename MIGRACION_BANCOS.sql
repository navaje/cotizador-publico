-- Ejecutar en la base actual del cotizador. No borra datos existentes.
-- El panel crea las tablas oj_banco_movimientos y oj_banco_auditoria al abrirlo.
-- Este CREATE solo cubre instalaciones donde aún no existe la tabla de recibos.
CREATE TABLE IF NOT EXISTS oj_recibos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 raiz_id INT NOT NULL, documento_id INT NOT NULL,
 folio VARCHAR(40) NULL UNIQUE, solicitud_token CHAR(64) NOT NULL UNIQUE,
 importe DECIMAL(12,2) NOT NULL, tipo VARCHAR(20) NOT NULL,
 forma_pago VARCHAR(20) NOT NULL, fecha_pago DATETIME NOT NULL,
 creado_en DATETIME NOT NULL, creado_por VARCHAR(190) NOT NULL,
 snapshot LONGTEXT NOT NULL,
 anulado_en DATETIME NULL, anulado_por VARCHAR(190) NULL,
 motivo_anulacion VARCHAR(500) NULL, INDEX(raiz_id), INDEX(documento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Antes de aplicar cobros, verificar que cotizaciones y oj_recibos usen InnoDB.
