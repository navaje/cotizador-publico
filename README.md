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
3. Crea la base de datos en cPanel y ejecuta los `MIGRACION_*.sql` (solo esquema, sin datos). Las columnas nuevas de `oj_visita_fotos` (thumb/lat/lng) se agregan solas al entrar a Visitas (`ojv_schema_upgrade`) o desde `visitas_instalar.php`.
4. Crea el primer usuario desde `usuarios.php` (requiere admin) y restringe el acceso a las herramientas de instalacion.
5. Copia `fabric.min.js` (v5.3.1) a `assets/vendor/fabric.min.js` para que el editor de planos funcione sin internet; si no existe, hace fallback al CDN.

## Modulo de visitas (levantamientos con fotos)

- Las fotos se guardan en `app/visitas_fotos/` (bloqueada por `.htaccess`) y solo se sirven via `visita_foto.php` verificando dueño; `?thumb=1` regresa la miniatura generada con GD.
- Se suben en lotes de hasta 10 via `visita_fotos_subir.php` (el cliente las comprime a 1600 px antes de enviar). Se leen GPS EXIF cuando la foto lo trae y se puede ligar cada foto a un punto (CAM-01...).
- Los planos se guardan en `app/storage/planos/` con tabla `oj_planos` y solo se sirven via `plano/ver.php?id=N` verificando dueño. Los planos viejos en `plano/planos/` quedaron bloqueados por `.htaccess`.

## Datos bancarios y calculadora de disco

- Los datos de pago (banco, cuenta, CLABE) ya NO viven en el código: se capturan en `config_empresa.php` y quedan en la tabla `empresa` (columnas `banco/cuenta/clabe`, vía `oj_emp_upgrade`). La cotización los inserta en las condiciones solo si están capturados.
- `index.php` incluye calculadora de almacenamiento CCTV (`assets/calculadora_disco.js`): cámaras × resolución × fps × días × modo → GB/día, TB totales y disco recomendado, con botón para agregar el renglón a la cotización.
- En el panel SYSCOM hay acceso rápido a los últimos 8 conceptos agregados (localStorage del navegador).

## Seguridad

- El repositorio debe ser **privado**.
- Nunca subas credenciales (`db.php`, `private_config.php`, `*/config.php`), zips de respaldo, PDFs con datos de clientes, fotos de visitas, `error_log` o dumps SQL con datos: el `.gitignore` ya los bloquea.
- Rota cualquier clave que haya estado en un respaldo antiguo antes de publicar cambios.
