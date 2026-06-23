# DEPLOY — Crematorios de Mascotas

Plan de despliegue a Hostinger en **3 sesiones**. Estrategia: **reemplazo total**
de la V1 existente (no actualización incremental). Las URLs/paths se mantienen,
así que Google no pierde la indexación.

> **Consentimiento de cookies (CMP):** se implementa por separado vía GTM
> server-side tracking, en paralelo al deploy del directorio. No es bloqueante
> de ninguna sesión de este plan.

---

## Conceptos previos

**`.gitignore` ≠ deploy.** Son dos cosas distintas:

- **Repo git** → solo el código de la plataforma. Excluye `scripts/`, `sql/`,
  `docs/`, `plantillas/`, `uploads/`, secretos.
- **Servidor Hostinger** → el código del repo **+** archivos que NO están en
  git pero el sitio necesita: `config.php`, `.env`, `.htaccess`, `uploads/`.

| Elemento | ¿En git? | ¿En el servidor? | Cómo llega al servidor |
|----------|----------|------------------|------------------------|
| Código (`.php`, `assets/`, `admin/`, `includes/`, `cron/`) | ✅ | ✅ | git / FTP |
| `admin/migrations/*.sql` | ✅ | opcional | git (referencia) |
| `includes/config.php` | ❌ | ✅ | FTP manual |
| `.env` | ❌ | ✅ | FTP manual |
| `.htaccess` | ❌ | ✅ | FTP manual |
| `uploads/` (imágenes) | ❌ | ✅ | FTP (zip) una vez |
| `scripts/`, `sql/`, `docs/`, `plantillas/` | ❌ | ❌ | no se suben |

**Tiempo total estimado:** ~3.5 h de trabajo activo repartidas en 3 sesiones,
en un calendario de ~3 días (porque entre Sesión 1 y Sesión 2 hay 1-2 días
de espera por la propagación DNS del email). La mayor parte es "esperar que
suban archivos", no trabajo activo.

---

# 🗓 SESIÓN 0 — Servicios externos (opcional)

> **Objetivo:** infraestructura de terceros que NO bloquea el deploy.
> El CMP se maneja por GTM server-side aparte. Cloudflare es opcional.

## 0.1 Cloudflare (OPCIONAL — puede hacerse meses después)
NO es necesario para lanzar. El sitio funciona perfecto sin Cloudflare
(Hostinger ya da SSL/HTTPS gratis). Se suma cuando convenga — por velocidad
o protección al crecer el tráfico. Agregarlo después NO es riesgoso:
Cloudflare importa los registros DNS existentes automáticamente.

- Si se suma **a futuro**: al migrar, verificar que importó bien los
  registros de email (MX/SPF/DKIM/DMARC) y dejarlos en "DNS only" (no proxy).
- ⚠️ Pendiente de código **solo el día que se active Cloudflare**: la IP
  real del visitante pasa a venir en el header `CF-Connecting-IP` (no
  `REMOTE_ADDR`). Ajustar donde se registra IP: leads_b2c, outbound_clicks,
  rate-limit de reseñas. Fix chico. Sin Cloudflare, `REMOTE_ADDR` funciona
  perfecto — no tocar nada hasta entonces.

**✅ Para el lanzamiento NO hace falta Sesión 0.** El CMP va por GTM server-side
en paralelo, Cloudflare es tarea futura.

> **Nota sobre el DNS de email:** sin Cloudflare, el SPF/DKIM/DMARC se
> configura en **hPanel** (ver Sesión 1.1). Si en el futuro se suma
> Cloudflare, el DNS se migra allá en ese momento.

---

# 🗓 SESIÓN 1 — Preparación (~1 h)

> **Objetivo:** dejar todo el material listo y arrancar la propagación DNS.
> **No se toca Hostinger todavía. Riesgo: cero.**

## 1.1 Configurar DNS de email — HACER PRIMERO
Esto se hace primero porque la propagación tarda horas (hasta 24-48 h) y debe
estar lista para la Sesión 3.
- [ ] En **hPanel → Emails** → configurar **SPF**, **DKIM** y **DMARC** del
      dominio (si en el futuro se suma Cloudflare, el DNS se migra allá)
- [ ] Anotar las credenciales SMTP de Titan (host, puerto, usuario, contraseña)

## 1.2 Limpiar datos de prueba (en LOCAL)
- [ ] Borrar fichas de prueba desde `admin/fichas-negocios.php` (botón 🗑)
- [ ] Vaciar tablas transaccionales de testing en phpMyAdmin:
  ```sql
  TRUNCATE leads_b2c;
  TRUNCATE outbound_clicks;
  TRUNCATE leads_comerciales;
  ```
  (Reseñas/solicitudes de prueba: borrar selectivamente, NO truncar si hay
  reales que conservar.)

## 1.3 Verificar slugs (CRÍTICO para SEO)
- [ ] Comparar los `slug` de las fichas locales contra las URLs que Google ya
      indexó de la V1. Si difieren → esas URLs darán 404 tras el cutover.

## 1.4 Exportar la base de datos limpia
- [ ] `mysqldump -u root crematorios_mascotas > deploy_db.sql`
- [ ] Verificar que el dump trae la estructura nueva (`estado`, `precios_json`,
      `leads_b2c`, `outbound_clicks`, etc.)

## 1.5 Preparar la configuración de producción
- [ ] Copia de `includes/config.php`:
  - `ENTORNO` → `'produccion'`
  - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` → datos reales de hPanel
  - `BASE_URL` → `https://crematoriosdemascotas.com`
  - `EMAIL_CONTACTO` → email de contacto definitivo
  - `WHATSAPP_SOPORTE` → WhatsApp/teléfono real (hoy es placeholder)
  - Confirmar `GTM-TDLMC4BH` = container real de producción
- [ ] Preparar el `.env` de producción:
  ```
  CLAUDE_API_KEY=sk-ant-...
  SMTP_HOST=smtp.titan.email
  SMTP_PORT=587
  SMTP_USER=notificaciones@crematoriosdemascotas.com
  SMTP_PASS=...
  SMTP_FROM_EMAIL=notificaciones@crematoriosdemascotas.com
  SMTP_FROM_NAME="Crematorios de Mascotas"
  SMTP_ENCRYPTION=tls
  ```
- [ ] Revisar `.htaccess`: en producción `RewriteBase` debe ser `/`
      (en local es `/crematoriosdemascotas/`)

## 1.6 Versionar la V2
- [ ] `git add .` (respeta `.gitignore` — agrega solo código)
- [ ] `git commit -m "V2 — versión completa pre-deploy"`
- [ ] `git tag v2.0-deploy`

**✅ Al terminar la Sesión 1 tenés:** DNS propagando, `deploy_db.sql` listo,
`config.php`/`.env`/`.htaccess` de producción preparados, V2 commiteada.

---

# 🗓 SESIÓN 2 — Cutover (~1 h 30)

> **Objetivo:** reemplazar la V1 por la V2.
> **El sitio queda caído ~1-2 h durante esta sesión.** Hacelo en horario tranquilo.

## 2.1 Backup de la V1 (seguro — por las dudas)
- [ ] Exportar la BD actual de Hostinger (phpMyAdmin → Exportar)
- [ ] Descargar `public_html/` actual por FTP a una carpeta local

## 2.2 Borrar la V1
- [ ] Vaciar `public_html/` en Hostinger
- [ ] Eliminar/vaciar la BD vieja

## 2.3 Subir la V2
- [ ] Subir el código (git clone/pull en el servidor, o FTP de todo el repo)
- [ ] Subir manualmente (NO están en git): `config.php`, `.env`, `.htaccess`
- [ ] Subir `uploads/`: comprimir local en `.zip`, subir un archivo al File
      Manager de hPanel, descomprimir allá (~26 MB, lo más lento)

## 2.4 Importar la base de datos
- [ ] Crear la BD nueva en hPanel
- [ ] Importar `deploy_db.sql` vía phpMyAdmin
- [ ] Verificar: `SELECT COUNT(*) FROM crematorios;` y que existan las columnas
      `estado` y `precios_json`

## 2.5 Permisos
- [ ] `uploads/` con permisos de escritura (755 / 775 según Hostinger)

## 2.6 Smoke test rápido
- [ ] Abrir la home — que cargue sin errores
- [ ] Abrir una ficha cualquiera — que cargue
- [ ] Entrar al panel admin — que el login funcione

**✅ Al terminar la Sesión 2 tenés:** la V2 en vivo en el dominio real.

---

# 🗓 SESIÓN 3 — QA y activación final (~1 h)

> **Objetivo:** verificar que todo funciona de punta a punta.
> **El DNS ya propagó (configurado en Sesión 1).**

## 3.1 QA de páginas públicas
- [ ] Home, directorio, ficha individual
- [ ] Páginas geo: España, comunidad, provincia, ciudad
- [ ] Mapas Leaflet cargan con pines + popups
- [ ] `sitemap.xml` y `robots.txt` responden OK
- [ ] Páginas legales (privacidad, cookies, términos), contacto, nosotros, 404

## 3.2 QA de formularios (end-to-end real)
- [ ] Contacto — enviar uno → verificar email + webhook
- [ ] Registro de negocio — verificar persistencia + email
- [ ] Reseña — verificar que entra a moderación
- [ ] Promocionar (footer) — verificar lead en admin
- [ ] Lead capture (clic en tel/wa/web de una ficha) — verificar `leads_b2c`

## 3.3 Email / entregabilidad
- [ ] Probar envío desde `admin/notif-leads-test.php`
- [ ] Confirmar que llega y NO cae en spam (el DNS ya propagó)

## 3.4 Cron del teaser
- [ ] hPanel → Cron Jobs:
  ```
  Comando: /usr/bin/php /home/USUARIO/domains/crematoriosdemascotas.com/public_html/cron/enviar-teasers-leads.php
  Horario: 0 9 * * *   (diario 09:00)
  ```
- [ ] Probar antes con `--dry-run` por SSH

## 3.5 SEO / indexación
- [ ] Search Console: verificar que las URLs indexadas siguen dando 200
- [ ] Enviar el `sitemap.xml` actualizado en Search Console

**✅ Al terminar la Sesión 3:** sitio definitivo verificado y 100% operativo.

---

# Después del deploy — workflow día a día

1. Cambios en local → `git commit` + `git push`
2. `git pull` en el servidor (o FTP de los archivos cambiados)
3. Si el cambio tocó la BD → correr esa migración `.sql` en producción
   (NUNCA más vaciar/re-sembrar — producción ya tiene datos reales)

`uploads/` ya NO se toca por FTP — el servidor lo llena solo cuando los
admins suben imágenes desde el panel.

**El cutover de las 3 sesiones es la ÚLTIMA vez** que se hace "borrar todo y
cargar de cero". De ahí en adelante, todo es incremental.
