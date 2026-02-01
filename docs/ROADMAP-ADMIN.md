# Roadmap: Sistema de Administración

Este documento describe las etapas pendientes para completar el sistema de administración del sitio.

---

## Estado Actual (Etapa 1 - Completada)

### Sistema de Reseñas
- [x] Tabla `resenas` en BD con estados (pendiente/aprobada/rechazada)
- [x] Tabla `admins` para usuarios del panel
- [x] Modificación de `procesar-formulario.php` para guardar reseñas
- [x] Panel admin funcional (`/admin/`)
  - Login con autenticación real
  - Listado de reseñas con filtros
  - Aprobar/rechazar reseñas
- [x] Visualización de reseñas aprobadas en `ficha.php`

### Credenciales Admin
- Email: `admin@crematoriosdemascotas.com`
- Password: `admin123` (cambiar en producción)

---

## Etapa 2: Aprobación de Negocios (registrar-negocio.php)

### Objetivo
Permitir que nuevos crematorios se registren por su cuenta, con un proceso de aprobación antes de aparecer en el directorio.

### Tareas

#### 1. Base de Datos
Crear tabla `solicitudes_negocio`:
```sql
CREATE TABLE solicitudes_negocio (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Datos de contacto
    contacto_nombre VARCHAR(100) NOT NULL,
    contacto_email VARCHAR(255) NOT NULL,
    contacto_telefono VARCHAR(50),

    -- Datos del crematorio
    nombre_negocio VARCHAR(255) NOT NULL,
    direccion VARCHAR(500) NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    provincia VARCHAR(100) NOT NULL,
    codigo_postal VARCHAR(10),
    descripcion TEXT,
    servicios TEXT,
    horarios TEXT,

    -- Online
    sitio_web VARCHAR(500),
    whatsapp VARCHAR(50),
    facebook VARCHAR(255),
    instagram VARCHAR(255),

    -- Estado
    estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
    moderado_por INT UNSIGNED DEFAULT NULL,
    moderado_en TIMESTAMP NULL,
    motivo_rechazo TEXT,
    crematorio_id INT UNSIGNED DEFAULT NULL, -- FK al crematorio creado si se aprueba

    -- Metadatos
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_estado (estado),
    INDEX idx_created (created_at)
);
```

#### 2. Modificar procesar-formulario.php
- En función `procesarRegistro()`:
  - Guardar en tabla `solicitudes_negocio` con estado 'pendiente'
  - Mantener envío de email/webhook existente

#### 3. Panel Admin - Nueva sección
Crear `admin/negocios.php`:
- Listado de solicitudes pendientes/aprobadas/rechazadas
- Ver detalle de cada solicitud
- Botón "Aprobar" que:
  - Crea el crematorio en tabla `crematorios`
  - Actualiza `solicitudes_negocio.estado = 'aprobada'`
  - Guarda `crematorio_id` de referencia
  - (Opcional) Envía email de confirmación al negocio
- Botón "Rechazar" con campo para motivo

#### 4. Archivos a crear
```
admin/
├── negocios.php          # Listado de solicitudes
├── negocio-detalle.php   # Ver detalle de una solicitud
└── negocio-accion.php    # Procesar aprobar/rechazar (AJAX)
```

#### 5. Funciones helper (funciones.php)
```php
function obtenerSolicitudesNegocio($estado, $pagina, $porPagina)
function obtenerSolicitudNegocio($id)
function aprobarSolicitudNegocio($id, $adminId) // Crea crematorio
function rechazarSolicitudNegocio($id, $adminId, $motivo)
```

---

## Etapa 3: Gestión de Consultas (contacto.php)

### Objetivo
Centralizar las consultas de contacto en el panel admin para mejor seguimiento.

### Tareas

#### 1. Base de Datos
Crear tabla `consultas`:
```sql
CREATE TABLE consultas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Datos del remitente
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefono VARCHAR(50),

    -- Contenido
    asunto VARCHAR(255),
    mensaje TEXT NOT NULL,

    -- Estado
    estado ENUM('nueva', 'leida', 'respondida', 'archivada') DEFAULT 'nueva',
    leida_por INT UNSIGNED DEFAULT NULL,
    leida_en TIMESTAMP NULL,
    notas_internas TEXT, -- Notas del admin

    -- Metadatos
    ip_address VARCHAR(45),
    page_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_estado (estado),
    INDEX idx_created (created_at)
);
```

#### 2. Modificar procesar-formulario.php
- En función `procesarContacto()`:
  - Guardar en tabla `consultas` con estado 'nueva'
  - Mantener envío de email/webhook existente

#### 3. Panel Admin - Nueva sección
Crear `admin/consultas.php`:
- Listado de consultas con filtros por estado
- Badge con contador de consultas nuevas en el header admin
- Ver detalle de cada consulta
- Marcar como leída/respondida/archivada
- Campo para notas internas

#### 4. Archivos a crear
```
admin/
├── consultas.php         # Listado de consultas
├── consulta-detalle.php  # Ver detalle (opcional, puede ser modal)
└── consulta-accion.php   # Cambiar estado (AJAX)
```

#### 5. Funciones helper (funciones.php)
```php
function obtenerConsultas($estado, $pagina, $porPagina)
function obtenerConsulta($id)
function contarConsultasNuevas() // Para badge en header
function actualizarEstadoConsulta($id, $estado, $adminId)
```

---

## Mejoras Futuras (Opcional)

### Seguridad
- [ ] Tokens CSRF en formularios
- [ ] Rate limiting por IP
- [ ] `session_regenerate_id()` después del login
- [ ] Logs de acciones de admin

### UX Admin
- [ ] Dashboard con estadísticas (reseñas, negocios, consultas)
- [ ] Búsqueda/filtros avanzados
- [ ] Exportar datos a CSV
- [ ] Notificaciones por email cuando hay items pendientes

### Negocios
- [ ] Email automático de bienvenida al aprobar negocio
- [ ] Email de rechazo con motivo
- [ ] Panel para que negocios editen su ficha (área privada)

---

## Notas Técnicas

### Patrones a mantener
- PDO con prepared statements (Singleton en `conexion_db.php`)
- Sanitización con `limpiar()` / `htmlspecialchars()`
- Estilos CSS usando `variables.css` y `componentes.css`
- Iconos con Lucide
- AJAX con fetch API

### Estructura de archivos admin
```
admin/
├── auth.php              # Funciones de autenticación
├── header.php            # Header común
├── footer.php            # Footer común
├── index.php             # Redirect
├── login.php             # Login
├── logout.php            # Logout
├── resenas.php           # [Etapa 1] Moderación de reseñas
├── resena-accion.php     # [Etapa 1] AJAX aprobar/rechazar
├── negocios.php          # [Etapa 2] Solicitudes de negocio
├── negocio-detalle.php   # [Etapa 2] Detalle solicitud
├── negocio-accion.php    # [Etapa 2] AJAX aprobar/rechazar
├── consultas.php         # [Etapa 3] Bandeja de consultas
├── consulta-detalle.php  # [Etapa 3] Detalle consulta
└── consulta-accion.php   # [Etapa 3] AJAX cambiar estado
```

---

*Documento generado: Enero 2026*
*Proyecto: Crematorios de Mascotas*
