-- Referencia: estas columnas se agregan de forma idempotente desde el codigo
-- (oj_emp_upgrade en app/empresa.php). Nunca guardes datos bancarios en el codigo fuente.
ALTER TABLE empresa ADD COLUMN banco VARCHAR(80) NULL;
ALTER TABLE empresa ADD COLUMN cuenta VARCHAR(40) NULL;
ALTER TABLE empresa ADD COLUMN clabe VARCHAR(30) NULL;
