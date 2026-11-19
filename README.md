# cotizador

Panel de trabajo de Ojo Jarocho: presupuestos, notas de venta, enlace con SYSCOM, facturacion, recibos, bancos y visitas.

## Instalacion en HostGator

1. Sube el codigo a `/public_html/cotizador/` **sin** los archivos bloqueados por `.gitignore`.
2. En el servidor, copia los archivos de ejemplo y completa tus credenciales:
   - `db.ejemplo.php` -> `db.php`
   - `private_config.ejemplo.php` -> `private_config.php`
   - `facturacion/config.ejemplo.php` -> `facturacion/config.php`
   - `bancos/config.ejemplo.php` -> `bancos/config.php`
   - `app/db.ejemplo.php` -> `app/db.php` y `app/config.ejemplo.php` -> `app/config.php`
3. Crea la base de datos en cPanel y ejecuta los `MIGRACION_*.sql` (solo esquema, sin datos).
4. Crea el primer usuario desde `usuarios.php` (requiere admin) y restringe el acceso a las herramientas de instalacion.

## Seguridad

- El repositorio debe ser **privado**.
- Nunca subas credenciales (`db.php`, `private_config.php`, `*/config.php`), zips de respaldo, PDFs con datos de clientes, fotos de visitas, `error_log` o dumps SQL con datos: el `.gitignore` ya los bloquea.
- Rota cualquier clave que haya estado en un respaldo antiguo antes de publicar cambios.
