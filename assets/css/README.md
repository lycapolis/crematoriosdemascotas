# Sistema de Botones

Patrón de nomenclatura compositivo para botones, basado en clases sueltas en español. Pensado para ser copiado a otros proyectos.

## Filosofía

Cada botón se construye sumando clases independientes:

```
.boton  +  [variante de color]  +  [modificador de tamaño/forma]
```

- **Clase base** (`.boton`) → define qué *es* (estructura, padding, tipografía, transición). No tiene color.
- **Variante de color** (`.uno`, `.dos`, `.tres`, `.cuatro`) → define qué *color* tiene. Numeradas, no semánticas, para mantener consistencia con la paleta del proyecto.
- **Modificador** (`.pequeno`, `.grande`, `.icono`) → define cómo *se ve*. Descriptivos en español.

> **Por qué numeradas y no semánticas (primary/secondary/danger):** la paleta del proyecto también es numerada (`--color-uno`, `--color-dos`...), así que los botones heredan la misma lógica. Cambiar la paleta no rompe la semántica del nombre.

## Clase base

```css
.boton {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--espacio-dos);
    padding: var(--espacio-tres) var(--espacio-cinco);
    margin: var(--espacio-dos) 0;
    font-size: var(--fs-dos);
    font-weight: var(--peso-negrita);
    text-decoration: none;
    border: none;
    border-radius: var(--radio-uno);
    cursor: pointer;
    transition: all var(--transicion);
}
```

Sin variante de color no se ve nada útil — siempre se combina con `.uno`/`.dos`/`.tres`/`.cuatro`.

## Variantes de color

| Clase           | Fondo                | Texto         | Hover                                  |
| --------------- | -------------------- | ------------- | -------------------------------------- |
| `.boton.uno`    | `--color-uno`        | `--color-ocho` (blanco) | `filter: brightness(0.9)` + lift `-2px` |
| `.boton.dos`    | `--color-cinco`      | `--color-uno` | invierte → fondo `--color-uno`, texto blanco |
| `.boton.tres`   | `--color-cinco`      | `--color-dos` | invierte → fondo `--color-dos`, texto blanco |
| `.boton.cuatro` | `--color-dos`        | `--color-ocho` (blanco) | pasa a fondo `--color-uno`, texto blanco |

Convención de uso típica:
- **`.uno`** → acción principal (CTA: enviar, registrar, buscar)
- **`.dos`** → acción secundaria suave (limpiar filtros sobre fondo claro)
- **`.tres`** → acción terciaria/neutral (paginación, cancelar, reset)
- **`.cuatro`** → acción alternativa de énfasis

## Modificadores de tamaño

| Clase             | Resultado                                         |
| ----------------- | ------------------------------------------------- |
| `.boton.pequeno`  | padding reducido, `font-size: var(--fs-uno)`     |
| *(sin clase)*     | tamaño por defecto, `font-size: var(--fs-dos)`   |
| `.boton.grande`   | padding generoso, `font-size: var(--fs-cuatro)`, `border-radius: var(--radio-dos)` |

## Modificador especial

| Clase           | Resultado                                                          |
| --------------- | ------------------------------------------------------------------ |
| `.boton.icono`  | padding cuadrado + `border-radius: var(--radio-full)` → botón circular solo con ícono. Requiere `aria-label`. |

```html
<button class="boton icono" aria-label="Cerrar">
    <span class="icono">×</span>
</button>
```

## Ejemplos de composición

```html
<!-- CTA principal grande -->
<button class="boton uno grande">Registrar mi negocio</button>

<!-- Botón normal -->
<button class="boton uno">Buscar</button>

<!-- Reset pequeño en formulario -->
<button class="boton tres pequeno">Limpiar</button>

<!-- Acción secundaria -->
<button class="boton dos">Limpiar filtros</button>

<!-- Botón ícono accesible -->
<button class="boton icono" aria-label="Menú">
    <span class="icono">☰</span>
</button>
```

## Variables que necesitás portar

Para que el sistema funcione en otro proyecto, definí estas variables CSS antes:

```css
/* Colores (paleta numerada) */
--color-uno     /* color principal de marca */
--color-dos     /* color secundario / acento */
--color-cuatro  /* fondo claro / superficie */
--color-cinco   /* fondo más claro / superficie alterna */
--color-ocho    /* blanco o casi-blanco para texto sobre fondos oscuros */

/* Espaciado */
--espacio-dos, --espacio-tres, --espacio-cuatro, --espacio-cinco, --espacio-seis

/* Tipografía */
--fs-uno, --fs-dos, --fs-cuatro
--peso-negrita

/* Bordes y transición */
--radio-uno, --radio-dos, --radio-full
--transicion
```

## Reglas de oro

1. **Nunca uses `.uno`/`.dos`/etc. solas** — siempre como modificador de `.boton`.
2. **No mezcles dos variantes de color** en el mismo botón (ej. `.boton.uno.dos` rompe el sistema).
3. **El tamaño y la forma son independientes del color** — cualquier color combina con cualquier tamaño.
4. **Botones ícono siempre con `aria-label`**.
5. **Si necesitás un nuevo estilo, sumá un modificador descriptivo nuevo** (ej. `.boton.fantasma`) en vez de pisar las variantes existentes.
