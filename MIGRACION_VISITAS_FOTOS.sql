-- Referencia: estas columnas se agregan de forma idempotente desde el codigo
-- (ojv_schema_upgrade en app/visitas.php) porque ADD COLUMN IF NOT EXISTS no es
-- compatible con todos los MySQL/MariaDB de HostGator.
ALTER TABLE oj_visita_fotos ADD COLUMN thumb VARCHAR(80) NULL;
ALTER TABLE oj_visita_fotos ADD COLUMN lat VARCHAR(24) NULL;
ALTER TABLE oj_visita_fotos ADD COLUMN lng VARCHAR(24) NULL;
