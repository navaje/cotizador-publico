# Instalación y uso — Cotizador Ojo Jarocho (panel interno)

Panel web en PHP para levantar visitas técnicas de CCTV/IP/redes, armar cotizaciones con
catálogo propio o SYSCOM (CSV y API), generar PDF, registrar clientes, recibos/banco y
facturación. Este documento es público: contiene **solo** instrucciones genéricas, sin
credenciales ni datos reales.

## Requisitos del hosting

- PHP 8.0+ con extensiones: `pdo_mysql`, `curl`, `gd`, `exif`, `fileinfo`, `openssl` (AES-256-GCM), `mbstring`, `json`.
- MySQL 5.7+ / MariaDB 10.3+.
- HTTPS obligatorio (el panel envía HSTS cuando detecta HTTPS).
- Carpeta pública tipo `/public_html/cotizador/` y acceso a una carpeta **fuera** del webroot
  (para la clave de cifrado de credenciales SYSCOM; el instalador la detecta automáticamente).
- Opcional: `dompdf/` (vendor) para PDF y `assets/vendor/fabric.min.js` (v5.3.1) para el editor de planos offline.

## Instalación paso a paso

1. Sube todo el contenido de este repositorio a `/public_html/cotizador/` **sin borrar nada** si ya existe una instalación.
2. Copia los archivos de ejemplo y completa tus credenciales **solo en el servidor**:
   - `db.ejemplo.php` → `db.php` (host, base de datos, usuario, contraseña de cPanel)
   - `private_config.ejemplo.php` → `private_config.php` (secreto aleatorio de enlaces de PDF: `bin2hex(random_bytes(32))`)
   - `facturacion/config.ejemplo.php` → `facturacion/config.php` (API key del proveedor de facturación)
   - `bancos/config.ejemplo.php` → `bancos/config.php` (secreto del webhook bancario + correo de avisos)
   - `app/db.ejemplo.php` → `app/db.php` y `app/config.ejemplo.php` → `app/config.php`
   - **Nunca subas estos archivos a ningún repositorio** (`.gitignore` ya los bloquea).
3. Crea la base de datos y el usuario en cPanel y ponlos en `db.php`.
4. Entra como admin a `visitas_instalar.php` (o simplemente abre `visitas.php`): las tablas
   (`oj_visitas`, `oj_visita_fotos` con `thumb/lat/lng`, `oj_planos`, esquemas de borradores/clientes)
   se crean/actualizan de forma idempotente. Los `MIGRACION_*.sql` son referencia manual.
5. Crea el primer usuario desde `usuarios.php` (solo admin). Cambia la contraseña inicial.
6. Captura los datos de la empresa y **datos bancarios** en `config_empresa.php`
   (nombre, contacto, banco, cuenta, CLABE; las columnas se agregan solas).
7. Permisos: `app/visitas_fotos/` y `app/storage/` escribibles por PHP (0700/0755 según hosting).
8. Copia `fabric.min.js` (v5.3.1, cdnjs) a `assets/vendor/`; si falta, el editor de planos usa el CDN.
9. Si `/cotizador/.htaccess` existe en el servidor, agrega al final:

```
<FilesMatch "\.(bin|sql|zip|tar|gz|log)$">
  Require all denied
</FilesMatch>
<FilesMatch "^(db|private_config|config)\.php$">
  Require all denied
</FilesMatch>
```

## Uso del panel

- **Login** (`login.php`): rate limit 10/h por IP y 5/h por usuario; recuperación de contraseña
  por correo con enlace de un solo uso (30 min). Sesión de 8 h, cookie `HttpOnly/SameSite/Secure`.
- **Dashboard** (`dashboard.php`): trabajo reciente, visitas recientes/listas/**programadas**,
  accesos a todos los módulos.
- **Visitas técnicas** (`visitas.php`): levantamiento con secciones (cliente, puntos de cámara
  `CAM-xx`, tramos de cableado `TR-xx`, infraestructura NVR/red/energía, ejecución, cierre con
  recomendación y pendientes). Cada guardado crea una **versión**. "Listo para cotizar" valida
  pendientes en servidor. **Fotos**: se suben en lotes de hasta 10 (`visita_fotos_subir.php`),
  el celular las comprime a 1600 px, el servidor genera miniatura 480 px y lee GPS EXIF;
  cada foto puede ligarse a un punto (`CAM-01`); se sirven solo vía `visita_foto.php` con sesión.
  **Planos** (`plano/index.php`): editor con paredes/cámaras/DVR; se guarda fuera del webroot
  (`app/storage/planos`) vía `plano/guardar.php` y se sirve con verificación de dueño (`plano/ver.php?id=N`).
  **Reportes**: `visita_reporte.php` (HTML/PDF interno con fotos agrupadas por punto; PDF para cliente
  sin fotos ni notas internas). Una visita lista se convierte en borrador de cotización con conceptos
  automáticos (cámaras, cable por metros con reserva, canalización, infraestructura...).
- **Cotizador** (`index.php`): datos del cliente/proyecto, conceptos con cantidad/precio/descuento,
  totales con IVA 16% y anticipo sugerido; **calculadora de precio de venta** (margen o recargo,
  IVA del costo, presets 30% videovigilancia / 20% redes); **calculadora de almacenamiento CCTV**
  (cámaras × MP × fps × días × modo → GB/día, TB y disco recomendado, agrega renglón);
  catálogo propio ("Mis conceptos") y **SYSCOM** (CSV importado en lotes o API en línea con OAuth,
  credenciales cifradas AES-256-GCM fuera del webroot, parámetros de tipo de cambio/margen guardados).
  Guardar borrador (versionado) o generar documento PDF (`generar-pdf.php`, `ver-cotizacion.php`).
- **Clientes** (`clientes.php`): directorio con búsqueda (`clientes_buscar.php`).
- **Bancos** (`bancos.php`, `banco_conexion.php`): avisos de Gmail vía Apps Script (`bancos/BanorteGmail.gs`)
  con firma HMAC; parseo de movimientos, recibos y vinculación.
- **Facturación** (`facturas.php`, `factura_conexion.php`, `app/facturacion.php`): entorno de pruebas
  con API key propia; CFDI con receptor fiscal.
- **Usuarios** (`usuarios.php`): admin crea/edita usuarios, roles, correo de recuperación;
  no se puede eliminar/desactivar el último admin ni a sí mismo.

## Temas y técnicas utilizadas

- **Seguridad**: PDO con prepared statements; CSRF en todos los POST; rate limiting por IP/usuario
  (`oj_acceso_intentos`); sesiones endurecidas (`use_strict_mode`, regeneración, fingerprint de hash,
  cookie HttpOnly/SameSite/Secure, timeout 8 h); headers globales (X-Frame-Options, nosniff,
  Referrer-Policy, CSP, HSTS); contraseñas con `password_hash`; credenciales SYSCOM cifradas
  AES-256-GCM con clave fuera del webroot; rutas de API en lista cerrada; validación server-side
  estricta de todo el input; subida de archivos con MIME real (`finfo`), tamaño y megapíxeles límite,
  nombres aleatorios y permisos 0600; `.htaccess` "deny" en carpetas de evidencias; acceso a
  fotos/planos solo vía scripts PHP con verificación de dueño.
- **Datos**: esquema versionado de visitas (`oj_visita_versiones`), borradores versionados con control
  optimista (409 si cambió en otra pestaña); columnas nuevas agregadas de forma idempotente.
- **Imágenes**: compresión client-side (canvas → 1600 px JPEG), miniaturas GD 480 px, EXIF GPS,
  PDF con dompdf (miniaturas en el PDF interno para que no pese ~50 MB).
- **Integraciones**: SYSCOM developers API (OAuth client_credentials, token con vigencia, reintentos
  con Retry-After, límite de respuesta 2 MB), Gmail vía Apps Script con HMAC-SHA256, WhatsApp vía wa.me.
- **Móvil**: CSS responsive (tarjetas editables para la tabla de conceptos), inputs 16 px, sticky savebar.

## Reglas de oro

- El repositorio público contiene **solo código sanitizado**; las credenciales viven únicamente en
  el servidor (archivos en `.gitignore`). Si una clave estuvo expuesta alguna vez: **róta antes de publicar**.
- Mantén el repo de trabajo **privado**; este público es solo para compartir el código limpio.
- Respaldos (zips, dumps SQL, PDFs de clientes) nunca dentro de `public_html`.
