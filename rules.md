# REGLAS DE DISEÑO Y CÓDIGO PARA LA CREACIÓN DE SITIOS WEB DE DIRECTORIOS

```
═══════════════════════════════════════════════════════════
PLANTILLA DE CREACIÓN DE DIRECTORIOS
═══════════════════════════════════════════════════════════

Autor: Facundo M. Campos
Empresa: Lycapolis LLC
Web: https://lycapolis.com
Proyecto Específico: CREMATORIOS DE MASCOTAS
Fecha inicio: Enero 2026

Sistema: Minimalismo extremo, nomenclatura numérica
═══════════════════════════════════════════════════════════
```

## ⚠️ ESTAS REGLAS SON ABSOLUTAS Y NO SE PUEDEN VIOLAR ⚠️

---

## 🎯 FILOSOFÍA DEL PROYECTO

Este proyecto sigue una filosofía de **MINIMALISMO EXTREMO**:
- Solo lo esencial
- Organización clara e intuitiva
- Código limpio y mantenible
- Amateur-friendly (cualquiera debe entender la estructura)

---

## 📁 ARCHIVOS SAGRADOS

Estos archivos son la **BASE del proyecto**. Son plantillas reutilizables para proyectos futuros.

### ⚠️ PROTOCOLO DE DOBLE CONFIRMACIÓN

**Modificar estos archivos requiere:**
1. **Primera confirmación:** "¿Este cambio es realmente necesario?"
2. **Segunda confirmación:** "¿Este cambio beneficia a TODOS los proyectos futuros?"

### Lista de Archivos Sagrados

| Archivo | Ubicación | Propósito |
|---------|-----------|-----------|
| `variables.css` | `/assets/css/` | Variables CSS globales (colores, tipografía, espaciado) |
| `componentes.css` | `/assets/css/` | Estilos de todos los componentes |
| `header.php` | `/includes/` | Cabecera reutilizable |
| `footer.php` | `/includes/` | Pie de página reutilizable |
| `rules.md` | `/` | Esta constitución del proyecto |

### ❌ NUNCA en archivos sagrados:
- Crear clases basándose en código de documentación
- Agregar estilos específicos de una sola página
- Modificar sin las dos confirmaciones

---

## 📚 ARCHIVOS DE DOCUMENTACIÓN

### Ubicación
Todos los archivos de documentación/guía van en `/docs/`:
- `guia-estilos.php` - Guía visual interactiva de componentes
- `ejemplo-completo.php` - Ejemplo de página integrada
- `ejemplo-comunidad.php` - Ejemplo de comunidad autónoma

### Excepciones Permitidas
Los archivos de documentación **PUEDEN** tener:
- ✅ Estilos inline (para demostrar ejemplos visuales)
- ✅ Clases de demostración no definidas en componentes.css
- ✅ HTML que exceda 3 niveles (para mostrar ejemplos)
- ✅ Más de 2 clases por elemento (para demostrar variantes)

### ⚠️ REGLA CRÍTICA
**NUNCA** crear clases o variables nuevas en archivos sagrados basándose en código de documentación. La documentación es referencia visual, NO código de producción.

---

## 📋 PLANTILLAS REUTILIZABLES

### Ubicación
Todas las plantillas geográficas van en `/plantillas/`:
- `pais.php` - Plantilla para cualquier país
- `provincia.php` - Plantilla para cualquier provincia/estado
- `ciudad.php` - Plantilla para cualquier ciudad
- `ficha.php` - Plantilla de ficha de negocio con todos los detalles

### Propósito
Estas plantillas contienen **estructura y funcionalidad completa** pero con datos de ejemplo (dummy text). Se duplican y adaptan al agregar nuevas categorías.

### Flujo de Uso
1. Copiar plantilla correspondiente
2. Renombrar según el nuevo contenido (ej: `argentina.php`)
3. Reemplazar datos dummy con datos reales
4. Mover a ubicación final (raíz u otra carpeta según URL deseada)

### Ejemplo
```
Agregar México al directorio:
1. Copiar /plantillas/pais.php → /mexico.php
2. Editar variables: $pais_nombre = 'México', etc.
3. Completar datos de provincias/estados
```

---

## ♻️ REGLA DE REUTILIZACIÓN DE CLASES

### PRINCIPIO FUNDAMENTAL
**Siempre reutilizar clases existentes antes de crear nuevas.**

### Proceso Obligatorio (antes de crear cualquier clase)
1. ✅ Revisar `componentes.css` buscando clases similares
2. ✅ Consultar `/docs/guia-estilos.php` para ver ejemplos de uso
3. ✅ Intentar lograr el efecto con clases existentes + variantes
4. ❌ Solo crear clase nueva si NO existe alternativa
5. ❌ Solo crear clase nueva si se usa en más de una página como mínimo.

### Si debes crear una clase nueva:
1. Verificar que no existe algo similar (buscar en componentes.css)
2. Solicitar doble confirmación (es archivo sagrado)
3. Agregarla a `componentes.css` siguiendo nomenclatura existente
4. Documentarla en `/docs/guia-estilos.php`

---

## 🔗 INCLUDES Y COMPONENTES

### Método de Inclusión
Los componentes se incluyen vía **PHP include**:

```php
<?php
// Al inicio de cada página PHP
$titulo_pagina = 'Título de la Página';
$pagina_actual = 'inicio'; // para marcar nav activo
include 'includes/header.php';
?>

<!-- ... contenido de la página ... -->

<?php include 'includes/footer.php'; ?>
```

### Variables Disponibles en Header
- `$titulo_pagina` - Título del tab del navegador
- `$pagina_actual` - Página activa ('inicio', 'directorio', 'contacto', etc.)
- `$base_url` - Base URL del proyecto (default: '/crematoriosdemascotas')

### ¿Por qué PHP include y no JavaScript?
- ✅ SEO óptimo (Google ve HTML completo)
- ✅ Carga rápida (una sola petición HTTP)
- ✅ Funciona sin JavaScript habilitado
- ❌ JavaScript fetch: SEO pobre, dos peticiones, dependencia de JS

---

## 🎨 CSS - REGLAS ABSOLUTAS

### COLORES
- ✅ **MÁXIMO 12 COLORES** en todo el proyecto (8 universales + 4 específicos)
- ✅ Los colores específicos se definen en `variables.css` para cada proyecto
- ✅ Usar nomenclatura numérica: `--color-uno`, `--color-dos`, `--color-tres`, etc.
- ✅ **Variantes permitidas:** `-claro` y `-oscuro` (ej: `--color-uno-claro`)
- ❌ **NUNCA crear variantes `-hover`** (hover es un estado, no una variante de color)
- ✅ Usar `opacity` o `rgba()` para variaciones

**COLORES UNIVERSALES (8) - Siempre presentes en cualquier proyecto:**
| Variable | Función | Valor fijo |
|----------|---------|------------|
| `--color-tres` | Éxito/Confirmación (verde) | Elegir según paleta |
| `--color-cuatro` | Fondo principal (claro) | Elegir según paleta |
| `--color-cinco` | Fondo alternativo (medio) | Elegir según paleta |
| `--color-seis` | Texto (negro/casi negro) | #2C2C2C o #242424 |
| `--color-siete` | Error/Advertencia (rojo) | Elegir según paleta |
| `--color-ocho` | Blanco puro | #FFFFFF |
| `--color-nueve` | Verde WhatsApp (CTAs mensajes) | #25D366 |
| `--color-diez` | Amarillo (estrellas, alertas) | #f59e0b |

**COLORES ESPECÍFICOS DEL PROYECTO (hasta 4):**
| Variable | Función |
|----------|---------|
| `--color-uno` | Primario - CTAs, enlaces, destacados |
| `--color-dos` | Secundario - Títulos, elementos importantes |
| `--color-once` | (Opcional) Acento adicional |
| `--color-doce` | (Opcional) Acento adicional |

**ESTRUCTURA (copiar esta plantilla en variables.css):**
```css
--color-uno: #XXXXXX;    /* Primario - [Descripción] - CTAs, enlaces, destacados */
--color-dos: #XXXXXX;    /* Secundario - [Descripción] - Títulos, elementos importantes */
--color-tres: #XXXXXX;   /* Acento - [Descripción] - Estados de éxito, elementos suaves */
--color-cuatro: #XXXXXX; /* Fondo - [Descripción] - Fondo principal del sitio */
--color-cinco: #XXXXXX;  /* Fondo Alt - [Descripción] - Fondos alternativos, separadores */
--color-seis: #XXXXXX;   /* Texto - [Descripción] - Todo el texto del sitio */
--color-siete: #XXXXXX;  /* Error/Advertencia - [Descripción] - Mensajes de error */
--color-ocho: #XXXXXX;   /* Blanco - [Descripción] - Fondos de tarjetas, modales */
--color-nueve: #25D366;  /* Verde WhatsApp - Botón de contacto (FIJO) */
--color-diez: #f59e0b;   /* Amarillo - Calificaciones con estrellas (FIJO) */
--color-once: #XXXXXX;   /* (Opcional) Acento adicional del proyecto */
--color-doce: #XXXXXX;   /* (Opcional) Acento adicional del proyecto */
```

**EJEMPLO PRÁCTICO (Paleta de Colores Tierra):**
```css
--color-uno: #C4866B;    /* Primario - Terracota - CTAs, enlaces, destacados */
--color-dos: #6B4E3D;    /* Secundario - Marrón - Títulos, elementos importantes */
--color-tres: #9CAF88;   /* Acento - Salvia - Estados de éxito, elementos suaves */
--color-cuatro: #FDF8F3; /* Fondo - Crema - Fondo principal del sitio */
--color-cinco: #E8DDD4;  /* Fondo Alt - Arena - Fondos alternativos, separadores */
--color-seis: #3D3D3D;   /* Texto - Gris oscuro - Todo el texto del sitio */
--color-siete: #C4695B;  /* Error - Rojo terracota - Mensajes de error/advertencia */
--color-ocho: #FFFFFF;   /* Blanco - Fondos de tarjetas, modales */
--color-nueve: #25D366;  /* Verde WhatsApp - Botón de contacto (FIJO) */
--color-diez: #f59e0b;   /* Amarillo - Calificaciones con estrellas (FIJO) */
/* --color-once: #XXXXXX; (Opcional - no usado en este proyecto) */
/* --color-doce: #XXXXXX; (Opcional - no usado en este proyecto) */
```
*Este ejemplo usa tonos tierra cálidos y acogedores, ideal para servicios de crematorios de mascotas.*

### TIPOGRAFÍA
- ✅ **8 TAMAÑOS DE FUENTE** (sistema completo de escala tipográfica)
- ✅ Los tamaños específicos se definen en `variables.css` para cada proyecto
- ✅ Usar nomenclatura numérica: `--fs-uno` a `--fs-ocho`
- ✅ Definir también las fuentes a usar (títulos y texto)
- ✅ **--fs-dos (14px) es el texto base del body**
- ❌ **NUNCA crear** tamaños como `xs`, `sm`, `md`, `lg`, `xl`, `2xl`, `3xl`
- ❌ **NUNCA usar** múltiples tamaños para el mismo tipo de elemento
- ✅ Si un texto necesita destacar, usar **peso de fuente** o **color**, NO crear tamaños nuevos

**ESTRUCTURA (copiar esta plantilla en variables.css):**
```css
/* Fuentes */
--fuente-titulo: '[Fuente de títulos]', Georgia, serif;
--fuente-texto: '[Fuente de texto]', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

/* 8 tamaños de fuente */
--fs-uno: 0.75rem;     /* 12px - Textos muy pequeños, legales, footnotes */
--fs-dos: 0.875rem;    /* 14px - TEXTO BASE, body, metadatos, labels */
--fs-tres: 1rem;       /* 16px - Texto destacado, párrafos largos */
--fs-cuatro: 1.125rem; /* 18px - Lead paragraphs */
--fs-cinco: 1.25rem;   /* 20px - Subtítulos pequeños, H4 */
--fs-seis: 1.75rem;    /* 28px - Títulos de tarjetas, H3 */
--fs-siete: 2.5rem;    /* 40px - Títulos de sección, H2 */
--fs-ocho: 3rem;       /* 48px - Títulos hero, H1 */
```

**EJEMPLO PRÁCTICO (Fuentes Elegantes):**
```css
/* Fuentes */
--fuente-titulo: 'Playfair Display', Georgia, serif;
--fuente-texto: 'Lato', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

/* 8 tamaños de fuente */
--fs-uno: 0.75rem;     /* 12px - Textos muy pequeños, legales, footnotes */
--fs-dos: 0.875rem;    /* 14px - TEXTO BASE, body, metadatos, labels */
--fs-tres: 1rem;       /* 16px - Texto destacado, párrafos largos */
--fs-cuatro: 1.125rem; /* 18px - Lead paragraphs */
--fs-cinco: 1.25rem;   /* 20px - Subtítulos pequeños, H4 */
--fs-seis: 1.75rem;    /* 28px - Títulos de tarjetas, H3 */
--fs-siete: 2.5rem;    /* 40px - Títulos de sección, H2 */
--fs-ocho: 3rem;       /* 48px - Títulos hero, H1 */
```
*Este ejemplo usa Playfair Display para títulos (elegante, serif) y Lato para texto (legible, sans-serif).*

### ESPACIADO
- ✅ Sistema basado en múltiplos de **4px** o **8px**
- ✅ Usar nomenclatura numérica: `--espacio-uno`, `--espacio-dos`, etc.
- ✅ Definir entre 5-7 tamaños (suficiente para todo el proyecto)

**ESTRUCTURA (copiar esta plantilla en variables.css):**
```css
--espacio-uno: 0.25rem;   /* 4px */
--espacio-dos: 0.5rem;    /* 8px */
--espacio-tres: 1rem;     /* 16px */
--espacio-cuatro: 1.5rem; /* 24px */
--espacio-cinco: 2.5rem;  /* 40px */
--espacio-seis: 4rem;     /* 64px */
--espacio-siete: 6rem;    /* 96px */
```

### BORDES Y SOMBRAS
- ✅ Máximo **3 radios de borde** + uno para círculos completos
- ✅ Máximo **2 niveles de sombra** (sutil y prominente)
- ✅ Usar nomenclatura numérica

**ESTRUCTURA (copiar esta plantilla en variables.css):**
```css
/* Radios de borde */
--radio-uno: 6px;
--radio-dos: 10px;
--radio-tres: 16px;
--radio-full: 9999px;  /* Para elementos circulares */

/* Sombras (con color marrón para armonía con paleta tierra) */
--sombra-uno: 0 2px 12px rgba(90, 62, 47, 0.08);   /* Sombra sutil */
--sombra-dos: 0 8px 32px rgba(90, 62, 47, 0.12);   /* Sombra prominente */
```

### CLASES CSS
- ✅ Clases deben ser **SEMÁNTICAS**: `.tarjeta`, `.boton`, `.entrada`
- ❌ **NUNCA usar clases UTILITARIAS**: `.flex`, `.flex-center`, `.mt-4`, `.gap-6`, `.text-center`
- ✅ Usar **sistema híbrido** para variantes: numeradas (colores) o descriptivas (otros)
- ✅ Usar **guión bajo `__`** para elementos hijos: `.tarjeta__titulo`
- ✅ Nombres **descriptivos pero CORTOS**
- ✅ **Máximo 1-2 clases por elemento HTML**

**SISTEMA HÍBRIDO DE NOMENCLATURA:**

```html
<!-- COMPONENTE BASE -->
<article class="tarjeta">

<!-- VARIANTE DE COLOR (numerada) -->
<button class="boton uno">      <!-- Usa var(--color-uno) -->
<button class="boton dos">      <!-- Usa var(--color-dos) -->

<!-- VARIANTE ESTRUCTURAL (descriptiva) -->
<article class="tarjeta simple">      <!-- Sin imagen -->
<article class="tarjeta destacada">   <!-- Estructura diferente -->

<!-- MODIFICADOR DE TAMAÑO/ESTADO (descriptivo) -->
<button class="boton uno grande">     <!-- Color + tamaño -->
<input class="entrada error">         <!-- Estado de error -->

<!-- ELEMENTO HIJO (guión bajo) -->
<h3 class="tarjeta__titulo">          <!-- Hijo de tarjeta -->

<!-- HIJO CON MODIFICADOR -->
<h3 class="tarjeta__titulo destacada"> <!-- Hijo + modificador descriptivo -->
<h3 class="tarjeta__titulo uno">       <!-- Hijo + modificador de color -->
```

**REGLA ORO:**
- Modificador de **COLOR** → Numerado (`uno`, `dos`, `tres`)
- Modificador de **OTRA COSA** → Descriptivo (`grande`, `pequeno`, `destacada`, `error`)
- **Elementos hijos** → Guión bajo `__`

**❌ PROHIBIDO:**
```html
<div class="flex flex-col items-center justify-between gap-4 p-8 rounded-lg">
<button class="btn btn--primario btn--large">
<div class="text-center mb-4 mt-8 font-bold text-2xl">
```

**✅ CORRECTO:**
```html
<article class="tarjeta">
<button class="boton uno grande">
<h3 class="tarjeta__titulo destacada">
```

### ESTRUCTURA CSS
- ✅ TODO debe estar definido en `variables.css` (`:root`)
- ✅ Solo 2 archivos CSS: `/assets/css/variables.css`, `/assets/css/componentes.css`
- ❌ **NUNCA crear** archivos como `buttons.css`, `cards.css`, `forms.css`, `responsive.css`
- ❌ **NUNCA usar estilos inline** en HTML (salvo casos MUY excepcionales)
- ✅ **Excepción:** Estilos inline permitidos en archivos de `/docs/` (documentación)

---

## 📄 HTML - REGLAS ABSOLUTAS

### ESTRUCTURA
- ✅ **MÁXIMO 3 NIVELES** de anidación de divs
- ✅ Usar HTML5 semántico: `<section>`, `<article>`, `<nav>`, `<header>`, `<footer>`
- ❌ **EVITAR divs innecesarios** - usar elementos semánticos primero
- ✅ Cada elemento debe tener **MÁXIMO 1-2 clases**

**❌ PROHIBIDO (demasiados niveles):**
```html
<div class="wrapper">
  <div class="container">
    <div class="inner">
      <div class="content">
        <div class="item">
          <p>Texto</p>
        </div>
      </div>
    </div>
  </div>
</div>
```

**✅ CORRECTO (máximo 3 niveles):**
```html
<section class="seccion">
  <div class="contenedor">
    <article class="tarjeta">
      <h2>Título</h2>
      <p>Texto</p>
    </article>
  </div>
</section>
```

### ICONOS - LUCIDE (YA INSTALADO)
- ✅ Usar **LUCIDE ICONS** (librería moderna de SVG)
- ✅ **YA INSTALADO** en: `/assets/js/lucide.min.js`
- ✅ **Inicialización YA INCLUIDA** en `footer.php`
- ✅ HTML limpio: `<i data-lucide="search"></i>`
- ✅ Clase común: `.icono` para estilos compartidos
- ❌ **NUNCA usar CDN** - usar archivo local
- ❌ **NUNCA usar** fuentes de íconos (Font Awesome, Material Icons)
- ❌ **NUNCA usar** SVG inline (solo si Lucide no tiene el ícono necesario)

**Uso:**
```html
<!-- Ícono simple -->
<i data-lucide="search"></i>

<!-- Ícono con clase para estilos -->
<i data-lucide="map-pin" class="icono"></i>

<!-- Dentro de botones -->
<button class="boton uno">
    <i data-lucide="phone" class="icono"></i>
    Llamar
</button>
```

**Recursos:**
- Explorar íconos: https://lucide.dev/icons

### FORMULARIOS
- ✅ Usar estructura semántica: `<form>`, `<label>`, `<input>`, `<select>`, `<textarea>`
- ✅ Clases descriptivas: `.formulario-grupo`, `.formulario-etiqueta`, `.campo`
- ❌ **NUNCA** envolver inputs en divs innecesarios

---

## 🎨 LUCIDE ICONS - REFERENCIA RÁPIDA

### ICONOS MÁS USADOS
```html
<!-- Navegación -->
<i data-lucide="search"></i>      <!-- Búsqueda -->
<i data-lucide="menu"></i>        <!-- Menú móvil -->
<i data-lucide="x"></i>           <!-- Cerrar -->
<i data-lucide="arrow-right"></i> <!-- Flecha -->

<!-- Contacto -->
<i data-lucide="map-pin"></i>     <!-- Ubicación -->
<i data-lucide="phone"></i>       <!-- Teléfono -->
<i data-lucide="mail"></i>        <!-- Email -->

<!-- Estados -->
<i data-lucide="star"></i>        <!-- Calificación -->
<i data-lucide="check"></i>       <!-- Verificado -->
<i data-lucide="clock"></i>       <!-- Horario -->
<i data-lucide="calendar"></i>    <!-- Fecha -->
```

### ICONOS ESPECÍFICOS PARA CREMATORIOS
```html
<i data-lucide="heart"></i>       <!-- Cremación -->
<i data-lucide="home"></i>        <!-- Recogida domicilio -->
<i data-lucide="clock"></i>       <!-- 24 horas -->
<i data-lucide="church"></i>      <!-- Velatorio -->
<i data-lucide="trees"></i>       <!-- Cementerio -->
<i data-lucide="paw-print"></i>   <!-- Mascotas (logo) -->
```

### REGLAS DE USO
- ✅ Siempre usar clase `.icono` para estilos consistentes
- ✅ Los íconos heredan el color del elemento padre (`currentColor`)
- ❌ **NUNCA** poner estilos inline en el `<i>`
- ❌ **NUNCA** mezclar Lucide con otras librerías de íconos

---

## 🔤 NOMENCLATURA - REGLAS ABSOLUTAS

### ARCHIVOS PHP
- ✅ **snake_case**: `crematorio_detalle.php`, `formulario_contacto.php`
- ✅ Nombres **descriptivos y cortos**
- ✅ Un archivo = una responsabilidad

### CLASES PHP
- ✅ **PascalCase**: `CrematorioController`, `FormularioContacto`
- ✅ Nombres claros de lo que hace la clase

### CLASES CSS - SISTEMA HÍBRIDO
- ✅ **TODO en español**: `boton` (no `btn`), `tarjeta` (no `card`), `entrada` (no `input`)
- ✅ **Componente base sin guiones**: `.boton`, `.tarjeta`, `.entrada`
- ✅ **Variantes de color numeradas**: `.boton.uno`, `.entrada.uno`
- ✅ **Variantes descriptivas**: `.boton.grande`, `.tarjeta.destacada`, `.entrada.error`
- ✅ **Elementos hijos con `__`**: `.tarjeta__titulo`, `.tarjeta__contenido`
- ✅ **Hijos con modificadores**: `.tarjeta__titulo.destacada`, `.tarjeta__imagen.grande`

**EJEMPLOS:**
```css
/* Componente base */
.boton { }
.tarjeta { }

/* Variante de color (numerada) */
.boton.uno { background: var(--color-uno); }
.boton.dos { border: 2px solid var(--color-uno); }

/* Variante descriptiva */
.boton.grande { padding: var(--espacio-cuatro); }
.tarjeta.destacada { border: 2px solid var(--color-uno); }

/* Elemento hijo */
.tarjeta__titulo { }
.tarjeta__contenido { }

/* Hijo con modificador */
.tarjeta__titulo.destacada { font-size: var(--fs-tres); }
```

### VARIABLES CSS
- ✅ **Nomenclatura numérica con prefijo**: `--color-uno`, `--fs-dos`, `--espacio-tres`
- ✅ Prefijos claros: `color-`, `fs-`, `espacio-`, `radio-`, `sombra-`
- ✅ Números en español: `uno`, `dos`, `tres`, `cuatro`, `cinco`, `seis`, `siete`

### FUNCIONES PHP
- ✅ **camelCase**: `obtenerCrematorios()`, `generarEstrellas()`, `limpiar()`
- ✅ Verbos al inicio: `obtener`, `crear`, `actualizar`, `eliminar`, `generar`

---

## 🏗️ ESTRUCTURA DE PROYECTO

```
crematorios-mascotas/
│
├── index.php, directorio.php, etc.  # Páginas de producción
├── rules.md                         # ⚠️ SAGRADO - Esta constitución
│
├── /docs/                           # Documentación y guías
│   ├── guia-estilos.php            # Guía visual de componentes
│   ├── ejemplo-completo.php        # Ejemplo de página integrada
│   └── ejemplo-comunidad.php       # Ejemplo de comunidad autónoma
│
├── /plantillas/                     # Plantillas base reutilizables
│   ├── pais.php                    # Plantilla para países
│   ├── provincia.php               # Plantilla para provincias
│   ├── ciudad.php                  # Plantilla para ciudades
│   └── ficha.php                   # Plantilla para crematorios
│
├── /includes/                       # ⚠️ SAGRADO - Componentes PHP
│   ├── header.php                  # Cabecera (incluye CSS, nav)
│   └── footer.php                  # Pie (incluye scripts, Lucide init)
│
├── /assets/
│   ├── /css/                       # ⚠️ SAGRADO
│   │   ├── variables.css           # Variables globales
│   │   └── componentes.css         # Estilos de componentes
│   ├── /js/
│   │   ├── lucide.min.js           # Biblioteca de iconos (LOCAL)
│   │   └── main.js                 # Scripts personalizados
│   └── /img/                       # Imágenes estáticas
│
├── /uploads/                        # Contenido subido por usuarios
│   ├── /galeria/
│   ├── /logos/
│   └── /portadas/
│
├── /admin/                          # Panel de administración
└── /sql/                            # Scripts de base de datos
```

---

## 📱 RESPONSIVE

### BREAKPOINTS (solo estos 3)
```css
/* Móvil: Base (0-767px) */
/* Tablet: 768px+ */
@media (min-width: 768px) { }
/* Desktop: 1024px+ */
@media (min-width: 1024px) { }
```

### ENFOQUE
- ✅ **Mobile First** - base es móvil, luego crece
- ✅ Usar `min-width` (no `max-width`)
- ❌ **NUNCA crear** breakpoints intermedios innecesarios

---

## 🚫 PROHIBICIONES ABSOLUTAS

### NUNCA HACER:
1. ❌ Crear más de 12 colores
2. ❌ Crear más de 8 tamaños de fuente
3. ❌ Usar clases utilitarias (`.flex`, `.mt-4`, etc)
4. ❌ Anidar más de 3 niveles en HTML
5. ❌ Usar estilos inline en HTML (excepto `/docs/`)
6. ❌ Hardcodear valores (siempre usar variables)
7. ❌ Crear archivos CSS fragmentados
8. ❌ Usar fuentes de íconos (Font Awesome, Material Icons) - usar Lucide
9. ❌ Usar SVG inline - usar Lucide (excepción: íconos custom no disponibles)
10. ❌ Múltiples clases por elemento (máximo 2)
11. ❌ Nombres genéricos (`.container1`, `.box`, `.wrapper`)
12. ❌ Modificar archivos sagrados sin doble confirmación
13. ❌ Crear clases basándose en código de documentación

---

## ✅ SIEMPRE HACER:

1. ✅ **Buscar clases existentes antes de crear nuevas**
2. ✅ Usar variables de `:root` para TODO
3. ✅ Clases semánticas descriptivas
4. ✅ HTML semántico (section, article, nav)
5. ✅ Nomenclatura consistente
6. ✅ Código limpio y organizado
7. ✅ Comentarios descriptivos con separadores visuales
8. ✅ Máximo 3 niveles de anidación
9. ✅ Mobile First
10. ✅ Consultar `/docs/guia-estilos.php` antes de implementar
11. ✅ Doble confirmación para archivos sagrados

---

## 🎯 PRINCIPIO GENERAL

**Si no estás 100% seguro de que algo cumple con estas reglas, NO LO HAGAS.**

Mejor preguntar primero que crear código que después hay que refactorizar.

---

## 💡 CUANDO TENGAS DUDAS

1. ¿Puedo usar esta clase utilitaria? → **NO**
2. ¿Puedo crear este color nuevo? → **NO**
3. ¿Puedo crear este tamaño de fuente? → **NO**
4. ¿Puedo anidar un div más? → **¿Ya tengo 3 niveles? Entonces NO**
5. ¿Puedo usar estilo inline? → **NO** (salvo en `/docs/`)
6. ¿Puedo crear esta clase nueva? → **¿Ya existe algo similar? Entonces NO**
7. ¿Puedo modificar variables.css o componentes.css? → **Doble confirmación primero**

**REGLA DE ORO: Cuando dudes, simplifica.**

---

## 🔍 SEO TÉCNICO

### ARCHIVOS DE SEO EN RAÍZ

| Archivo | Propósito |
|---------|-----------|
| `robots.txt` | Directivas para crawlers (qué rastrear/bloquear) |
| `sitemap.php` | Genera `sitemap.xml` dinámicamente desde la BD |

**Nota:** `sitemap.xml` no existe como archivo - el `.htaccess` redirige a `sitemap.php`.

### CONSTANTES SEO (`includes/config.php`)

```php
define('SEO_DEFAULT_IMAGE', BASE_URL . '/assets/img/og-default.jpg');
define('SEO_TWITTER_HANDLE', '@crematoriosmascotas');
define('SEO_LOCALE', 'es_ES');
define('SEO_SITE_TYPE', 'website');
```

### VARIABLES SEO POR PÁGINA

Antes de incluir `header.php`, cada página puede definir:

```php
$meta_descripcion = "Descripción única de la página";
$meta_canonical = "https://crematoriosdemascotas.es/url-de-la-pagina";
$meta_robots = "index, follow";  // o "noindex, nofollow"
$og_image = "https://crematoriosdemascotas.es/img/imagen-especifica.jpg";
$og_type = "website";  // o "article", "local.business"
$schema_data = [...];  // Array para JSON-LD personalizado
```

Si no se definen, `header.php` usa valores por defecto.

### META TAGS EN `header.php`

El header incluye automáticamente:
- **SEO básico:** description, robots, canonical
- **Open Graph:** og:title, og:description, og:image, og:url, og:type, og:locale, og:site_name
- **Twitter Cards:** twitter:card, twitter:title, twitter:description, twitter:image, twitter:site
- **Schema.org JSON-LD:** WebSite por defecto, o personalizado si se pasa `$schema_data`

### SCHEMA MARKUP

**Por defecto** (todas las páginas):
```json
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "Crematorios de Mascotas",
  "url": "https://crematoriosdemascotas.es",
  "potentialAction": { "@type": "SearchAction", ... }
}
```

**Personalizado** (ej: ficha de crematorio):
```php
$schema_data = [
    "@context" => "https://schema.org",
    "@type" => "LocalBusiness",
    "name" => $crematorio['nombre'],
    "address" => [...],
    "telephone" => $crematorio['telefono'],
    // etc.
];
```

### VERIFICACIÓN

- robots.txt: `https://crematoriosdemascotas.com/robots.txt`
- sitemap.xml: `https://crematoriosdemascotas.com/sitemap.xml`
- Sitemap Validator: https://www.xml-sitemaps.com/validate-xml-sitemap.html
- Open Graph: https://developers.facebook.com/tools/debug/
- Twitter Cards: https://cards-dev.twitter.com/validator
- Schema: https://validator.schema.org/

### IMAGEN OG POR DEFECTO

Crear imagen en `/assets/img/og-default.jpg`:
- Tamaño recomendado: **1200x630px**
- Formato: JPG o PNG
- Contenido: Logo + nombre del sitio
