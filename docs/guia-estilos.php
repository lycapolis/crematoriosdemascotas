<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía de Estilos - Crematorios de Mascotas</title>

    <!-- CSS del proyecto -->
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/componentes.css">

    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

    <!-- Estilos específicos de la guía -->
    <style>
        /* ═══════════════════════════════════════════════════════════
           ESTILOS DE LA GUÍA DE ESTILOS
           ═══════════════════════════════════════════════════════════ */

        body {
            font-family: var(--fuente-texto);
            background: var(--color-cuatro);
            margin: 0;
            padding: 0;
        }

        .guia-container {
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Navegación lateral */
        .guia-nav {
            display: flex;
            flex-direction: column;
            gap: var(--espacio-cuatro);
            width: 15%;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            
            
            background: var(--color-ocho);
            padding: var(--espacio-siete) var(--espacio-tres) var(--espacio-siete) var(--espacio-tres);
            border-right: 1px solid var(--color-cinco);
        }

        .guia-nav__titulo {
            font-size: var(--fs-seis);
            text-align: center;
            color: var(--color-dos);
            margin-bottom: var(--espacio-dos);
            font-family: var(--fuente-titulo);
        }

        .guia-nav__lista {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .guia-nav__item {
            margin-bottom: var(--espacio-dos);
        }

        .guia-nav__enlace {
            display: block;
            padding: var(--espacio-dos) var(--espacio-tres);
            color: var(--color-seis);
            text-decoration: none;
            border-radius: var(--radio-uno);
            transition: all var(--transicion);
            font-size: var(--fs-dos);
        }

        .guia-nav__enlace:hover {
            background: var(--color-cinco);
            color: var(--color-uno);
        }

        /* Contenido principal */
        .guia-main {
            width: 85%;
            padding: var(--espacio-cinco) var(--espacio-seis) var(--espacio-cinco) var(--espacio-seis);
            overflow: hidden;
        }

        /* Secciones */
        .guia-seccion {
            display: flex;
            flex-direction: column;
            gap: var(--espacio-cuatro);
            
            padding: var(--espacio-cinco) 0 var(--espacio-cinco) 0;
            border-bottom: 2px solid var(--color-cinco);
        }

        .guia-seccion__titulo {
            font-size: var(--fs-siete);
            color: var(--color-dos);
            margin-bottom: var(--espacio-cuatro);
            font-family: var(--fuente-titulo);
        }

        .guia-subseccion {
            display: flex;
            flex-direction: column;
            gap: var(--espacio-tres);
            /*margin-bottom: var(--espacio-seis);*/
        }

        .guia-subseccion__titulo {
            font-size: var(--fs-cinco);
            color: var(--color-dos);
            margin-bottom: var(--espacio-tres);
            font-weight: var(--peso-negrita);
        }

        /* Grid de ejemplos */
        .guia-grid {
            display: grid;
            gap: var(--espacio-cuatro);
        }

        .guia-grid--dos {
            grid-template-columns: 1fr;
        }

        .guia-grid--tres {
            grid-template-columns: 1fr;
        }

        .guia-grid--cuatro {
            grid-template-columns: repeat(4, 1fr);
        }

        /* Ejemplo individual */
        .guia-ejemplo {
            display: flex;
            flex-direction: column;
            gap: var(--espacio-cuatro);
            background: var(--color-ocho);
            border: 1px solid var(--color-cinco);
            border-radius: var(--radio-dos);
            padding: var(--espacio-cuatro);
            overflow: hidden;
        }

        .guia-ejemplo__visual {
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            /*margin-bottom: var(--espacio-tres);*/
            padding: var(--espacio-tres);
            background: var(--color-cuatro);
            border-radius: var(--radio-uno);
            overflow-x: auto;
        }

        .guia-ejemplo__info {
            display: flex;
            flex-direction: column;
            gap: var(--espacio-uno);
            /*margin-bottom: var(--espacio-tres);*/
            padding: var(--espacio-tres);
            background: var(--color-cinco);
            border-radius: var(--radio-uno);
            flex: 1;
        }

        /* Tarjetas de colores - padding reducido */
        .guia-grid--cuatro .guia-ejemplo {
            padding: var(--espacio-tres);
            gap: var(--espacio-tres);
        }

        .guia-ejemplo__info strong {
            color: var(--color-dos);
            font-family: monospace;
            font-size: var(--fs-dos);
        }

        .guia-ejemplo__info span {
            color: var(--color-seis);
            font-size: var(--fs-dos);
        }

        .guia-ejemplo__codigo {
            background: var(--color-seis);
            color: var(--color-cuatro);
            padding: var(--espacio-tres);
            border-radius: var(--radio-uno);
            font-size: var(--fs-uno);
            overflow-x: auto;
            font-family: monospace;
            line-height: var(--lh-cuatro);
        }

        /* Swatch de color */
        .color-swatch {
            width: 100%;
            height: 80px;
            border-radius: var(--radio-dos);
            
            border: 1px solid var(--color-cinco);
        }

        /* Tabla de tipografía */
        .tipo-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: var(--espacio-cinco);
            display: block;
            overflow-x: auto;
        }

        .tipo-tabla th,
        .tipo-tabla td {
            padding: var(--espacio-tres);
            text-align: left;
            border-bottom: 1px solid var(--color-cinco);
        }

        .tipo-tabla th {
            background: var(--color-cinco);
            font-weight: var(--peso-negrita);
            color: var(--color-dos);
        }

        /* Responsive - Mobile First */
        .guia-container {
            flex-direction: column;
        }

        .guia-nav {
            position: static;
            width: 100%;
            height: auto;
            border-right: none;
            border-bottom: 1px solid var(--color-cinco);
        }

        .guia-main {
            width: 100%;
        }

        .guia-grid--dos,
        .guia-grid--tres,
        .guia-grid--cuatro {
            grid-template-columns: 1fr;
        }

        /* Fix: Botones oscuros mantienen texto blanco en hover */
        .boton.uno:hover,
        .boton.cuatro:hover {
            color: var(--color-ocho);
        }

        @media (min-width: 768px) {
            .guia-grid--dos {
                grid-template-columns: repeat(2, 1fr);
            }

            .guia-grid--tres {
                grid-template-columns: repeat(3, 1fr);
            }

            .guia-grid--cuatro {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1200px) {
            .guia-grid--cuatro {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .guia-container {
                flex-direction: row;
            }

            .guia-nav {
                position: sticky;
                width: 15%;
                height: 100vh;
                border-right: 1px solid var(--color-cinco);
                border-bottom: none;
            }

            .guia-main {
                width: 85%;
            }
        }

        /* Estilos específicos para demostración */
        .demo-hero {
            background: var(--color-cuatro);
            padding: var(--espacio-seis) var(--espacio-cinco);
            border-radius: var(--radio-dos);
        }

        .demo-hero__contenedor {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .demo-hero__subtitulo {
            color: var(--color-uno);
            font-size: var(--fs-tres);
            font-style: italic;
            margin-bottom: var(--espacio-tres);
        }

        .demo-hero__titulo {
            font-size: var(--fs-ocho);
            line-height: var(--lh-uno);
            letter-spacing: var(--ls-uno);
            margin-bottom: var(--espacio-cuatro);
            color: var(--color-dos);
            font-family: var(--fuente-titulo);
        }

        .demo-hero__descripcion {
            font-size: var(--fs-cuatro);
            color: var(--color-seis);
            margin-bottom: var(--espacio-cinco);
            line-height: var(--lh-tres);
        }
    </style>
</head>
<body>
    <div class="guia-container">
        <!-- ═══════════════════════════════════════════════════════════
             NAVEGACIÓN LATERAL
             ═══════════════════════════════════════════════════════════ -->
        <nav class="guia-nav">
            <h1 class="guia-nav__titulo">Guía de Estilos</h1>
            <ul class="guia-nav__lista">
                <li class="guia-nav__item"><a href="#tipografia" class="guia-nav__enlace">1. Tipografía</a></li>
                <li class="guia-nav__item"><a href="#colores" class="guia-nav__enlace">2. Paleta de Colores</a></li>
                <li class="guia-nav__item"><a href="#botones" class="guia-nav__enlace">3. Botones</a></li>
                <li class="guia-nav__item"><a href="#headers" class="guia-nav__enlace">4. Headers</a></li>
                <li class="guia-nav__item"><a href="#heroes" class="guia-nav__enlace">5. Hero Sections</a></li>
                <li class="guia-nav__item"><a href="#tarjetas" class="guia-nav__enlace">6. Tarjetas</a></li>
                <li class="guia-nav__item"><a href="#formularios" class="guia-nav__enlace">7. Formularios</a></li>
                <li class="guia-nav__item"><a href="#iconos" class="guia-nav__enlace">8. Iconos</a></li>
                <li class="guia-nav__item"><a href="#secciones" class="guia-nav__enlace">9. Secciones Especiales</a></li>
                <li class="guia-nav__item"><a href="#footers" class="guia-nav__enlace">10. Footers</a></li>
                <li class="guia-nav__item"><a href="#layouts" class="guia-nav__enlace">11. Layouts y Grillas</a></li>
            </ul>
        </nav>

        <!-- ═══════════════════════════════════════════════════════════
             CONTENIDO PRINCIPAL
             ═══════════════════════════════════════════════════════════ -->
        <main class="guia-main">

            <!-- ═══════════════════════════════════════════════════════════
                 1. TIPOGRAFÍA
                 ═══════════════════════════════════════════════════════════ -->
            <section id="tipografia" class="guia-seccion">
                <h2 class="guia-seccion__titulo">1. Tipografía</h2>

                <!-- Escala de tamaños -->
                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Escala de Tamaños (8 niveles)</h3>

                    <table class="tipo-tabla">
                        <thead>
                            <tr>
                                <th>Variable</th>
                                <th>Tamaño</th>
                                <th>Uso Recomendado</th>
                                <th>Vista Previa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>--fs-uno</code></td>
                                <td>0.75rem (12px)</td>
                                <td>Textos muy pequeños, legales, footnotes</td>
                                <td><span style="font-size: var(--fs-uno);">Texto de ejemplo</span></td>
                            </tr>
                            <tr>
                                <td><code>--fs-dos</code></td>
                                <td>0.875rem (14px)</td>
                                <td><strong>Texto base, body</strong>, metadatos, labels, breadcrumbs</td>
                                <td><span style="font-size: var(--fs-dos);">Texto de ejemplo</span></td>
                            </tr>
                            <tr>
                                <td><code>--fs-tres</code></td>
                                <td>1rem (16px)</td>
                                <td>Texto destacado, párrafos largos</td>
                                <td><span style="font-size: var(--fs-tres);">Texto de ejemplo</span></td>
                            </tr>
                            <tr>
                                <td><code>--fs-cuatro</code></td>
                                <td>1.125rem (18px)</td>
                                <td>Texto destacado, lead paragraphs</td>
                                <td><span style="font-size: var(--fs-cuatro);">Texto de ejemplo</span></td>
                            </tr>
                            <tr>
                                <td><code>--fs-cinco</code></td>
                                <td>1.25rem (20px)</td>
                                <td>Subtítulos pequeños, H4</td>
                                <td><span style="font-size: var(--fs-cinco);">Texto de ejemplo</span></td>
                            </tr>
                            <tr>
                                <td><code>--fs-seis</code></td>
                                <td>1.75rem (28px)</td>
                                <td>Títulos de tarjetas, H3, H2</td>
                                <td><span style="font-size: var(--fs-seis); font-family: var(--fuente-titulo);">Texto de ejemplo</span></td>
                            </tr>
                            <tr>
                                <td><code>--fs-siete</code></td>
                                <td>2.5rem (40px)</td>
                                <td>Títulos de sección</td>
                                <td><span style="font-size: var(--fs-siete); font-family: var(--fuente-titulo);">Texto de ejemplo</span></td>
                            </tr>
                            <tr>
                                <td><code>--fs-ocho</code></td>
                                <td>3rem (48px)</td>
                                <td>Títulos hero, H1</td>
                                <td><span style="font-size: var(--fs-ocho); font-family: var(--fuente-titulo);">Texto de ejemplo</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Jerarquía de títulos -->
                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Jerarquía de Títulos (H1-H6)</h3>

                    <div style="display: flex; flex-direction: column; gap: var(--espacio-tres); padding: var(--espacio-cinco); background: var(--color-ocho); border-radius: var(--radio-dos); width: 70%; margin: 0 auto;">
                        <h1 style="font-size: var(--fs-ocho); font-family: var(--fuente-titulo); margin: 0; color: var(--color-dos);">H1 - Título Principal Hero</h1>
                        <h2 style="font-size: var(--fs-seis); font-family: var(--fuente-titulo); margin: 0; color: var(--color-dos);">H2 - Títulos de Sección</h2>
                        <h3 style="font-size: var(--fs-cinco); font-weight: var(--peso-negrita); margin: 0; color: var(--color-dos);">H3 - Subtítulos</h3>
                        <h4 style="font-size: var(--fs-cuatro); font-weight: var(--peso-negrita); margin: 0; color: var(--color-dos);">H4 - Títulos Menores</h4>
                        <h5 style="font-size: var(--fs-tres); font-weight: var(--peso-negrita); margin: 0; color: var(--color-dos);">H5 - Título Pequeño</h5>
                        <h6 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); margin: 0; color: var(--color-dos);">H6 - Título Mínimo</h6>
                    </div>
                </div>

                <!-- Pesos de fuente -->
                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Pesos de Fuente</h3>

                    <div class="guia-grid guia-grid--tres">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--peso-normal</strong>
                                <span>400 - Texto regular</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="font-weight: var(--peso-normal); margin: 0;">Texto con peso normal</p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--peso-medio</strong>
                                <span>500 - Texto semi-bold</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="font-weight: var(--peso-medio); margin: 0;">Texto con peso medio</p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--peso-negrita</strong>
                                <span>700 - Texto negrita</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="font-weight: var(--peso-negrita); margin: 0;">Texto con peso bold</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Heights -->
                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Altura de Línea (Line Height)</h3>

                    <div class="guia-grid guia-grid--dos">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--lh-uno</strong>
                                <span>1.1 - Títulos muy grandes (H1 hero)</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="line-height: var(--lh-uno); font-size: var(--fs-cinco); margin: 0;">
                                    Línea uno de texto<br>
                                    Línea dos de texto
                                </p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--lh-dos</strong>
                                <span>1.3 - Títulos medianos (H2, H3)</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="line-height: var(--lh-dos); font-size: var(--fs-cinco); margin: 0;">
                                    Línea uno de texto<br>
                                    Línea dos de texto
                                </p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--lh-tres</strong>
                                <span>1.6 - Texto estándar, párrafos</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="line-height: var(--lh-tres); margin: 0;">
                                    Línea uno de texto<br>
                                    Línea dos de texto
                                </p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--lh-cuatro</strong>
                                <span>1.8 - Textos largos, artículos</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="line-height: var(--lh-cuatro); margin: 0;">
                                    Línea uno de texto<br>
                                    Línea dos de texto
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Letter Spacing -->
                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Espaciado de Letras (Letter Spacing)</h3>

                    <div class="guia-grid guia-grid--tres">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--ls-uno</strong>
                                <span>-0.02em - Títulos grandes</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="letter-spacing: var(--ls-uno); font-size: var(--fs-cinco); margin: 0;">Texto Comprimido</p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--ls-dos</strong>
                                <span>0 - Texto base</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="letter-spacing: var(--ls-dos); font-size: var(--fs-cinco); margin: 0;">Texto Normal</p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>--ls-tres</strong>
                                <span>0.05em - Labels uppercase</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="letter-spacing: var(--ls-tres); font-size: var(--fs-dos); text-transform: uppercase; margin: 0;">Texto Expandido</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 2. PALETA DE COLORES
                 ═══════════════════════════════════════════════════════════ -->
            <section id="colores" class="guia-seccion">
                <h2 class="guia-seccion__titulo">2. Paleta de Colores</h2>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">10 Colores Base</h3>

                    <div class="guia-grid guia-grid--cuatro">
                        <!-- Color Uno -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-uno);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-uno</strong>
                                <span>#B8704F</span>
                                <span>Primario - Terracota</span>
                                <span>CTAs, enlaces, destacados</span>
                            </div>
                        </div>

                        <!-- Color Dos -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-dos);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-dos</strong>
                                <span>#5A3E2F</span>
                                <span>Secundario - Marrón</span>
                                <span>Títulos, elementos importantes</span>
                            </div>
                        </div>

                        <!-- Color Tres -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-tres);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-tres</strong>
                                <span>#8FA876</span>
                                <span>Acento - Salvia</span>
                                <span>Estados de éxito, elementos suaves</span>
                            </div>
                        </div>

                        <!-- Color Cuatro -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-cuatro);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-cuatro</strong>
                                <span>#FFFBF7</span>
                                <span>Fondo - Crema</span>
                                <span>Fondo principal del sitio</span>
                            </div>
                        </div>

                        <!-- Color Cinco -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-cinco);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-cinco</strong>
                                <span>#F5EDE5</span>
                                <span>Fondo Alt - Arena</span>
                                <span>Fondos alternativos, separadores</span>
                            </div>
                        </div>

                        <!-- Color Seis -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-seis);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-seis</strong>
                                <span>#2C2C2C</span>
                                <span>Texto - Gris oscuro</span>
                                <span>Todo el texto del sitio</span>
                            </div>
                        </div>

                        <!-- Color Siete -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-siete);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-siete</strong>
                                <span>#C4695B</span>
                                <span>Error/Advertencia - Rojo terracota</span>
                                <span>Estados de error, alertas</span>
                            </div>
                        </div>

                        <!-- Color Ocho -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-ocho); border: 2px solid var(--color-cinco);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-ocho</strong>
                                <span>#FFFFFF</span>
                                <span>Blanco</span>
                                <span>Fondos de tarjetas, modales</span>
                            </div>
                        </div>

                        <!-- Color Nueve -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-nueve);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-nueve</strong>
                                <span>#25D366</span>
                                <span>Verde WhatsApp (FIJO)</span>
                                <span>Botón de contacto, CTAs de mensajes</span>
                            </div>
                        </div>

                        <!-- Color Diez -->
                        <div class="guia-ejemplo">
                            <div class="color-swatch" style="background: var(--color-diez);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-diez</strong>
                                <span>#f59e0b</span>
                                <span>Amarillo (FIJO)</span>
                                <span>Calificaciones con estrellas, alertas</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Variantes con Opacidad</h3>
                    <p class="guia-descripcion">Sistema de niveles: base (sin sufijo = nivel 01 implícito) + -02, -03 para niveles adicionales. Solo usar "claro" y "oscuro".</p>

                    <div class="guia-grid guia-grid--tres">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <p style="color: var(--color-seis-claro); margin: 0;">Texto con color-seis-claro</p>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-seis-claro</strong>
                                <span>rgba(44, 44, 44, 0.65)</span>
                                <span>Nivel base (01 implícito)</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <p style="color: var(--color-seis-claro-02); margin: 0;">Texto con color-seis-claro-02</p>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-seis-claro-02</strong>
                                <span>rgba(44, 44, 44, 0.45)</span>
                                <span>Segundo nivel (más claro)</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual" style="background: var(--color-uno-claro);"></div>
                            <div class="guia-ejemplo__info">
                                <strong>--color-uno-claro</strong>
                                <span>rgba(184, 112, 79, 0.12)</span>
                                <span>Fondos suaves de iconos</span>
                            </div>
                        </div>
                    </div>

                </div>

            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 3. BOTONES
                 ═══════════════════════════════════════════════════════════ -->
            <section id="botones" class="guia-seccion">
                <h2 class="guia-seccion__titulo">3. Botones</h2>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Tipos de Botones</h3>

                    <div class="guia-grid guia-grid--dos">
                        <!-- Botón Uno -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <button class="boton uno">Botón Uno</button>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.boton.uno</strong>
                                <span>CTAs principales</span>
                            </div>
                            <pre class="guia-ejemplo__codigo">&lt;button class="boton uno"&gt;Botón Uno&lt;/button&gt;</pre>
                        </div>

                        <!-- Botón Dos -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <button class="boton dos">Botón Dos</button>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.boton.dos</strong>
                                <span>Acciones secundarias (outline)</span>
                            </div>
                            <pre class="guia-ejemplo__codigo">&lt;button class="boton dos"&gt;Botón Dos&lt;/button&gt;</pre>
                        </div>

                        <!-- Botón Tres -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <button class="boton tres">Botón Tres</button>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.boton.tres</strong>
                                <span>Elementos de navegación, tags</span>
                            </div>
                            <pre class="guia-ejemplo__codigo">&lt;button class="boton tres"&gt;Botón Tres&lt;/button&gt;</pre>
                        </div>

                        <!-- Botón Cuatro -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <button class="boton cuatro">Botón Cuatro</button>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.boton.cuatro</strong>
                                <span>Acciones secundarias destacadas</span>
                            </div>
                            <pre class="guia-ejemplo__codigo">&lt;button class="boton cuatro"&gt;Botón Cuatro&lt;/button&gt;</pre>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Tamaños de Botones</h3>
                    <p class="guia-descripcion">
                        <strong>Border-radius:</strong> Botón normal y pequeño usan --radio-uno (6px) para mantener forma rectangular con esquinas suavemente redondeadas.
                        Botón grande usa --radio-dos (10px). Botón icono usa --radio-full (circular).
                    </p>

                    <div style="max-width: 90%; margin: 0 auto;">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual" style="flex-direction: column; gap: var(--espacio-tres);">
                                <button class="boton uno grande">Botón Grande</button>
                                <button class="boton uno">Botón Normal</button>
                                <button class="boton uno pequeno">Botón Pequeño</button>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>Tamaños:</strong>
                                <span>.grande: padding 24px/64px, font-size 18px</span>
                                <span>Normal: padding 16px/40px, font-size 14px</span>
                                <span>.pequeno: padding 8px/24px, font-size 12px</span>
                            </div>
                            <pre class="guia-ejemplo__codigo">&lt;button class="boton uno grande"&gt;Grande&lt;/button&gt;
&lt;button class="boton uno"&gt;Normal&lt;/button&gt;
&lt;button class="boton uno pequeno"&gt;Pequeño&lt;/button&gt;</pre>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Botones con Iconos</h3>

                    <div class="guia-grid guia-grid--dos">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <button class="boton uno">
                                    <i data-lucide="search" class="icono"></i>
                                    Buscar
                                </button>
                            </div>
                            <div class="guia-ejemplo__info">
                                <span>Botón con icono izquierdo</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <button class="boton dos">
                                    Ver más
                                    <i data-lucide="arrow-right" class="icono"></i>
                                </button>
                            </div>
                            <div class="guia-ejemplo__info">
                                <span>Botón con icono derecho</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual" style="display: flex; gap: var(--espacio-tres);">
                                <button class="boton icono" aria-label="Menú">
                                    <i data-lucide="menu" class="icono"></i>
                                </button>
                                <button class="boton icono" aria-label="Cerrar">
                                    <i data-lucide="x" class="icono"></i>
                                </button>
                                <button class="boton icono" aria-label="Buscar">
                                    <i data-lucide="search" class="icono"></i>
                                </button>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.boton.icono</strong>
                                <span>Botón solo con icono, sin texto</span>
                                <span><strong>Uso:</strong> Menú hamburguesa, cerrar modales, acciones rápidas</span>
                                <span><strong>Estilo:</strong> Circular (--radio-full), padding uniforme</span>
                                <span><strong>Accesibilidad:</strong> Siempre incluir aria-label</span>
                            </div>
                            <pre class="guia-ejemplo__codigo">&lt;button class="boton icono" aria-label="Menú"&gt;
  &lt;i data-lucide="menu" class="icono"&gt;&lt;/i&gt;
&lt;/button&gt;</pre>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 4. HEADERS
                 ═══════════════════════════════════════════════════════════ -->
            <section id="headers" class="guia-seccion">
                <h2 class="guia-seccion__titulo">4. Headers</h2>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Header Principal</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__visual" style="padding: 0; background: var(--color-ocho);">
                            <header class="header" style="width: 100%;">
                                <div class="header__contenedor">
                                    <a href="/" class="header__logo">
                                        <i data-lucide="paw-print" class="icono"></i>
                                        <span>Crematorios de Mascotas</span>
                                    </a>

                                    <nav class="header__nav">
                                        <ul class="menu">
                                            <li><a href="/directorio.html" class="menu__enlace">Directorio</a></li>
                                            <li><a href="/nosotros.html" class="menu__enlace">Nosotros</a></li>
                                            <li><a href="/contacto.html" class="menu__enlace">Contacto</a></li>
                                        </ul>

                                        <a href="/registrar-negocio.html" class="boton uno">Registrar Negocio</a>
                                    </nav>
                                </div>
                            </header>
                        </div>
                        <div class="guia-ejemplo__info">
                            <strong>.header</strong>
                            <span><strong>Logo:</strong> Icono paw-print + texto, enlazado a "/" (función "Inicio")</span>
                            <span><strong>Menú:</strong> Solo 3 items (Directorio, Nosotros, Contacto)</span>
                            <span><strong>CTA:</strong> "Registrar Negocio" siempre visible</span>
                            <span><strong>Móvil:</strong> Menú hamburguesa con los 3 items + botón CTA</span>
                        </div>
                        <pre class="guia-ejemplo__codigo">&lt;header class="header"&gt;
  &lt;a href="/" class="header__logo"&gt;
    &lt;i data-lucide="paw-print"&gt;&lt;/i&gt;
    &lt;span&gt;Crematorios de Mascotas&lt;/span&gt;
  &lt;/a&gt;
  &lt;nav class="header__nav"&gt;
    &lt;ul class="menu"&gt;
      &lt;li&gt;&lt;a href="/directorio.html"&gt;Directorio&lt;/a&gt;&lt;/li&gt;
      &lt;li&gt;&lt;a href="/nosotros.html"&gt;Nosotros&lt;/a&gt;&lt;/li&gt;
      &lt;li&gt;&lt;a href="/contacto.html"&gt;Contacto&lt;/a&gt;&lt;/li&gt;
    &lt;/ul&gt;
    &lt;a href="/registrar-negocio.html" class="boton uno"&gt;Registrar Negocio&lt;/a&gt;
  &lt;/nav&gt;
&lt;/header&gt;</pre>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 5. HERO SECTIONS
                 ═══════════════════════════════════════════════════════════ -->
            <section id="heroes" class="guia-seccion">
                <h2 class="guia-seccion__titulo">5. Hero Sections / Portadas</h2>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Hero Principal con Buscador (index.html)</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__visual" style="padding: 0;">
                            <div class="demo-hero">
                                <div class="demo-hero__contenedor">
                                    <p class="demo-hero__subtitulo">Un adiós con amor y dignidad</p>
                                    <h1 class="demo-hero__titulo">Encuentra el lugar perfecto para despedir a tu mascota</h1>
                                    <p class="demo-hero__descripcion">
                                        Conectamos familias con crematorios de mascotas de confianza.
                                        Servicios profesionales, respetuosos y llenos de compasión.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="guia-ejemplo__info">
                            <strong>.hero</strong>
                            <span>Subtítulo itálico + Título H1 + Descripción + Buscador</span>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Otras Variantes de Hero</h3>
                    <p class="guia-descripcion">Sistema numérico: .hero (base) + .hero-uno, .hero-dos, .hero-tres, .hero-cuatro</p>

                    <div class="guia-grid guia-grid--dos">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>.hero-uno</strong>
                                <span>nosotros.html (antes: .nosotros-hero)</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="margin: 0; font-size: var(--fs-dos); color: var(--color-seis-claro);">
                                    Hero sin buscador, estructura simple con subtítulo + título + descripción
                                </p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>.hero-dos</strong>
                                <span>contacto.html (antes: .contacto-hero)</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="margin: 0; font-size: var(--fs-dos); color: var(--color-seis-claro);">
                                    Hero compacto: solo título + descripción
                                </p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>.hero-tres</strong>
                                <span>registrar-negocio.html (antes: .registro-hero)</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="margin: 0; font-size: var(--fs-dos); color: var(--color-seis-claro);">
                                    Idéntico a .hero-dos: título + descripción
                                </p>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>.hero-cuatro</strong>
                                <span>directorio.html (antes: .directorio-header)</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <p style="margin: 0; font-size: var(--fs-dos); color: var(--color-seis-claro);">
                                    Header simple sin imagen de fondo: título + subtítulo
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 6. TARJETAS
                 ═══════════════════════════════════════════════════════════ -->
            <section id="tarjetas" class="guia-seccion">
                <h2 class="guia-seccion__titulo">6. Tarjetas</h2>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Tarjeta Estándar (.tarjeta)</h3>

                    <div class="guia-ejemplo">
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--espacio-cuatro); width: 100%;">
                                <!-- Tarjeta 1 -->
                                <article class="tarjeta">
                                    <div class="tarjeta__imagen">
                                        <img src="https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600&h=400&fit=crop" alt="Demo" loading="lazy">
                                        <span class="tarjeta__destacado">Destacado</span>
                                    </div>
                                    <div class="tarjeta__contenido">
                                        <h3 class="tarjeta__titulo"><a href="#">Crematorio Madrid</a></h3>
                                        <p class="tarjeta__ubicacion"><i data-lucide="map-pin" class="icono"></i> Madrid, Madrid</p>
                                        <p class="tarjeta__descripcion">Servicios de cremación profesional y respetuoso.</p>
                                        <div class="tarjeta__footer">
                                            <div class="tarjeta__valoracion">
                                                <i data-lucide="star" class="icono icono--llena"></i>
                                                <span>5.0 (12)</span>
                                            </div>
                                        </div>
                                    </div>
                                </article>

                                <!-- Tarjeta 2 -->
                                <article class="tarjeta">
                                    <div class="tarjeta__imagen">
                                        <img src="https://images.unsplash.com/photo-1450778869180-41d0601e046e?w=600&h=400&fit=crop" alt="Demo" loading="lazy">
                                    </div>
                                    <div class="tarjeta__contenido">
                                        <h3 class="tarjeta__titulo"><a href="#">Crematorio Barcelona</a></h3>
                                        <p class="tarjeta__ubicacion"><i data-lucide="map-pin" class="icono"></i> Barcelona, Barcelona</p>
                                        <p class="tarjeta__descripcion">Acompañamiento en momentos difíciles con dignidad.</p>
                                        <div class="tarjeta__footer">
                                            <div class="tarjeta__valoracion">
                                                <i data-lucide="star" class="icono icono--llena"></i>
                                                <span>4.8 (8)</span>
                                            </div>
                                        </div>
                                    </div>
                                </article>

                                <!-- Tarjeta 3 -->
                                <article class="tarjeta">
                                    <div class="tarjeta__imagen">
                                        <img src="https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=600&h=400&fit=crop" alt="Demo" loading="lazy">
                                    </div>
                                    <div class="tarjeta__contenido">
                                        <h3 class="tarjeta__titulo"><a href="#">Crematorio Valencia</a></h3>
                                        <p class="tarjeta__ubicacion"><i data-lucide="map-pin" class="icono"></i> Valencia, Valencia</p>
                                        <p class="tarjeta__descripcion">Servicio completo de despedida para tu mascota.</p>
                                        <div class="tarjeta__footer">
                                            <div class="tarjeta__valoracion">
                                                <i data-lucide="star" class="icono icono--llena"></i>
                                                <span>5.0 (15)</span>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <div class="guia-ejemplo__info">
                            <strong>.tarjeta</strong>
                            <span>Grid 3 columnas: repeat(auto-fit, minmax(300px, 1fr))</span>
                            <span>Imagen + badge destacado (opcional) + título + ubicación + descripción + valoración</span>
                            <span><strong>Padding vertical:</strong> Contenedor con espacio arriba/abajo para centrado</span>
                        </div>
                    </div>
                </div>   

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Otros Tipos: Sistema .item-*</h3>
                    <p class="guia-descripcion">Componentes tipo card que no son tarjetas principales. Nomenclatura numérica: .item-uno, .item-dos, etc.</p>

                    <div class="guia-grid guia-grid--tres">
                        <!-- Item Uno -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>.item-uno</strong>
                                <span>nosotros.html (antes: .mision-card)</span>
                            </div>
                            <div class="guia-ejemplo__visual" style="flex-direction: column;">
                                <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--color-uno-claro); display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="heart" class="icono" style="color: var(--color-uno); width: 28px; height: 28px;"></i>
                                </div>
                                <h3 style="font-size: var(--fs-cuatro); margin: var(--espacio-tres) 0;">Título</h3>
                                <p style="font-size: var(--fs-tres); color: var(--color-seis-claro); margin: 0;">Texto descriptivo</p>
                            </div>
                        </div>

                        <!-- Item Dos -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>.item-dos</strong>
                                <span>nosotros.html (antes: .valor-item)</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <div style="display: flex; gap: var(--espacio-tres); align-items: flex-start;">
                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--color-uno); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">1</div>
                                    <div>
                                        <h3 style="font-size: var(--fs-cuatro); margin: 0 0 var(--espacio-dos);">Valor</h3>
                                        <p style="font-size: var(--fs-tres); margin: 0;">Descripción</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Item Tres -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>.item-tres</strong>
                                <span>contacto.html (antes: .contacto-item)</span>
                            </div>
                            <div class="guia-ejemplo__visual">
                                <div style="display: flex; gap: var(--espacio-tres); align-items: flex-start;">
                                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--color-uno-claro); display: flex; align-items: center; justify-content: center;">
                                        <i data-lucide="mail" class="icono" style="color: var(--color-uno);"></i>
                                    </div>
                                    <div>
                                        <h3 style="font-size: var(--fs-cuatro); margin: 0 0 var(--espacio-dos);">Email</h3>
                                        <p style="font-size: var(--fs-tres); margin: 0;">info@ejemplo.com</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Item Cuatro -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>.item-cuatro</strong>
                                <span>registrar-negocio.html (antes: .beneficio-card)</span>
                            </div>
                            <div class="guia-ejemplo__visual" style="flex-direction: column;">
                                <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--color-tres); color: white; display: flex; align-items: center; justify-content: center;">
                                    <i data-lucide="users" class="icono"></i>
                                </div>
                                <h3 style="font-size: var(--fs-cuatro); margin: var(--espacio-tres) 0;">Beneficio</h3>
                                <p style="font-size: var(--fs-tres); margin: 0;">Descripción del beneficio</p>
                            </div>
                        </div>

                        <!-- Item Cinco -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__info">
                                <strong>.item-cinco</strong>
                                <span>registrar-negocio.html (antes: .registro-exito)</span>
                            </div>
                            <div class="guia-ejemplo__visual" style="flex-direction: column;">
                                <i data-lucide="check-circle" class="icono" style="width: 72px; height: 72px; color: var(--color-tres);"></i>
                                <h2 style="font-size: var(--fs-seis); margin: var(--espacio-cuatro) 0 var(--espacio-tres);">¡Registro Exitoso!</h2>
                                <p style="margin: 0;">Mensaje de confirmación</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 7. FORMULARIOS
                 ═══════════════════════════════════════════════════════════ -->
            <section id="formularios" class="guia-seccion">
                <h2 class="guia-seccion__titulo">7. Formularios</h2>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Elementos de Formulario</h3>

                    <div class="guia-grid guia-grid--dos">
                        <!-- Input -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual" style="flex-direction: column; align-items: stretch;">
                                <label class="formulario-etiqueta">Nombre</label>
                                <input type="text" class="campo" placeholder="Tu nombre">
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.campo</strong>
                                <span>Input de texto (antes: .entrada)</span>
                            </div>
                            <pre class="guia-ejemplo__codigo">&lt;label class="formulario-etiqueta"&gt;Nombre&lt;/label&gt;
&lt;input type="text" class="campo" placeholder="..."&gt;</pre>
                        </div>

                        <!-- Select -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual" style="flex-direction: column; align-items: stretch;">
                                <label class="formulario-etiqueta">Provincia</label>
                                <select class="seleccion">
                                    <option>Selecciona una opción</option>
                                    <option>Madrid</option>
                                    <option>Barcelona</option>
                                </select>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.seleccion</strong>
                                <span>Select dropdown</span>
                            </div>
                        </div>

                        <!-- Textarea -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual" style="flex-direction: column; align-items: stretch;">
                                <label class="formulario-etiqueta">Mensaje</label>
                                <textarea class="area-texto" rows="3" placeholder="Escribe tu mensaje"></textarea>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.area-texto</strong>
                                <span>Textarea</span>
                            </div>
                        </div>

                        <!-- Checkbox -->
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <label class="casilla-verificacion">
                                    <input type="checkbox">
                                    <span class="casilla-verificacion__texto">Acepto términos</span>
                                </label>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.casilla-verificacion</strong>
                                <span>Checkbox custom</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Alertas</h3>

                    <div class="guia-grid guia-grid--dos">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <div class="alerta exito">
                                    <i data-lucide="check-circle" class="icono"></i>
                                    <span>¡Mensaje enviado con éxito!</span>
                                </div>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.alerta.exito</strong>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <div class="alerta error">
                                    <i data-lucide="alert-circle" class="icono"></i>
                                    <span>Por favor completa todos los campos</span>
                                </div>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>.alerta.error</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Formularios Completos</h3>

                    <!-- Buscador Hero -->
                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__info">
                            <strong>Buscador Hero</strong>
                            <span>index.html - Input + botón horizontal integrado en hero</span>
                        </div>
                        <div class="guia-ejemplo__visual">
                            <form class="buscador" style="max-width: 700px; width: 100%; display: flex; align-items: center; gap: var(--espacio-dos);">
                                <input type="text" class="campo" placeholder="Buscar por nombre o ciudad..." style="border-radius: var(--radio-full); flex: 1; height: 48px;">
                                <button class="boton uno" type="submit" style="border-radius: var(--radio-full); height: 48px;">
                                    <i data-lucide="search" class="icono"></i>
                                    Buscar
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Filtros Avanzados -->
                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__info">
                            <strong>Filtros Avanzados</strong>
                            <span>index.html - Grid 4 columnas con selects y checkboxes</span>
                        </div>
                        <div class="guia-ejemplo__visual" style="padding: var(--espacio-cuatro); background: var(--color-ocho); border-radius: var(--radio-dos);">
                            <form style="width: 100%;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--espacio-tres); margin-bottom: var(--espacio-cuatro);">
                                    <div>
                                        <label class="formulario-etiqueta">Comunidad Autónoma</label>
                                        <div class="seleccion-contenedor">
                                            <select class="seleccion">
                                                <option>Todas</option>
                                                <option>Madrid</option>
                                                <option>Cataluña</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="formulario-etiqueta">Provincia</label>
                                        <div class="seleccion-contenedor">
                                            <select class="seleccion">
                                                <option>Todas</option>
                                                <option>Madrid</option>
                                                <option>Barcelona</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="formulario-etiqueta">Ciudad</label>
                                        <div class="seleccion-contenedor">
                                            <select class="seleccion">
                                                <option>Todas</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="formulario-etiqueta">Ordenar por</label>
                                        <div class="seleccion-contenedor">
                                            <select class="seleccion">
                                                <option>Más relevantes</option>
                                                <option>Mejor valorados</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-bottom: var(--espacio-cuatro);">
                                    <label class="formulario-etiqueta">Servicios</label>
                                    <div style="display: flex; flex-wrap: wrap; gap: var(--espacio-tres);">
                                        <label class="casilla-verificacion">
                                            <input type="checkbox">
                                            <span class="casilla-verificacion__texto">Cremación individual</span>
                                        </label>
                                        <label class="casilla-verificacion">
                                            <input type="checkbox">
                                            <span class="casilla-verificacion__texto">Recogida a domicilio</span>
                                        </label>
                                        <label class="casilla-verificacion">
                                            <input type="checkbox">
                                            <span class="casilla-verificacion__texto">Urnas personalizadas</span>
                                        </label>
                                    </div>
                                </div>
                                <div style="display: flex; gap: var(--espacio-tres);">
                                    <button type="submit" class="boton uno">Buscar</button>
                                    <button type="reset" class="boton tres">Limpiar filtros</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Formulario Contacto -->
                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__info">
                            <strong>Formulario Contacto</strong>
                            <span>contacto.html - Grid 2 columnas con validación</span>
                        </div>
                        <div class="guia-ejemplo__visual" style="padding: var(--espacio-cuatro); background: var(--color-ocho); border-radius: var(--radio-dos);">
                            <form style="width: 100%;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--espacio-cuatro); margin-bottom: var(--espacio-cuatro);">
                                    <div>
                                        <label class="formulario-etiqueta">Nombre completo *</label>
                                        <input type="text" class="campo" placeholder="Juan Pérez" required>
                                    </div>
                                    <div>
                                        <label class="formulario-etiqueta">Email *</label>
                                        <input type="email" class="campo" placeholder="tu@email.com" required>
                                    </div>
                                    <div>
                                        <label class="formulario-etiqueta">Teléfono</label>
                                        <input type="tel" class="campo" placeholder="+34 600 000 000">
                                    </div>
                                    <div>
                                        <label class="formulario-etiqueta">Asunto *</label>
                                        <select class="seleccion" required>
                                            <option value="">Selecciona un asunto</option>
                                            <option>Consulta general</option>
                                            <option>Información de servicios</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-bottom: var(--espacio-cuatro);">
                                    <label class="formulario-etiqueta">Mensaje *</label>
                                    <textarea class="area-texto" rows="4" placeholder="Escribe tu mensaje aquí..." required></textarea>
                                </div>
                                <button type="submit" class="boton uno">Enviar mensaje</button>
                            </form>
                        </div>
                    </div>

                    <!-- Formulario Registro Negocio -->
                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__info">
                            <strong>Formulario Registro Negocio</strong>
                            <span>registrar-negocio.html - 3 secciones con iconos</span>
                        </div>
                        <div class="guia-ejemplo__visual" style="padding: var(--espacio-cuatro); background: var(--color-ocho); border-radius: var(--radio-dos);">
                            <form style="width: 100%;">
                                <!-- Sección 1: Datos de contacto -->
                                <div style="margin-bottom: var(--espacio-cinco);">
                                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); margin-bottom: var(--espacio-tres);">
                                        <i data-lucide="user" class="icono" style="color: var(--color-uno);"></i>
                                        <h3 style="margin: 0; font-size: var(--fs-cinco);">Datos de contacto</h3>
                                    </div>
                                    <div style="display: grid; gap: var(--espacio-tres);">
                                        <input type="text" class="campo" placeholder="Nombre completo" required>
                                        <input type="email" class="campo" placeholder="Email" required>
                                        <input type="tel" class="campo" placeholder="Teléfono" required>
                                    </div>
                                </div>

                                <!-- Sección 2: Datos del crematorio -->
                                <div style="margin-bottom: var(--espacio-cinco);">
                                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); margin-bottom: var(--espacio-tres);">
                                        <i data-lucide="home" class="icono" style="color: var(--color-uno);"></i>
                                        <h3 style="margin: 0; font-size: var(--fs-cinco);">Datos del crematorio</h3>
                                    </div>
                                    <div style="display: grid; gap: var(--espacio-tres);">
                                        <input type="text" class="campo" placeholder="Nombre del negocio" required>
                                        <input type="text" class="campo" placeholder="Dirección completa" required>
                                        <select class="seleccion" required>
                                            <option value="">Provincia</option>
                                            <option>Madrid</option>
                                            <option>Barcelona</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Sección 3: Presencia online -->
                                <div style="margin-bottom: var(--espacio-cuatro);">
                                    <div style="display: flex; align-items: center; gap: var(--espacio-tres); margin-bottom: var(--espacio-tres);">
                                        <i data-lucide="globe" class="icono" style="color: var(--color-uno);"></i>
                                        <h3 style="margin: 0; font-size: var(--fs-cinco);">Presencia online</h3>
                                    </div>
                                    <div style="display: grid; gap: var(--espacio-tres);">
                                        <input type="url" class="campo" placeholder="Sitio web (opcional)">
                                        <input type="url" class="campo" placeholder="Facebook (opcional)">
                                        <input type="url" class="campo" placeholder="Instagram (opcional)">
                                    </div>
                                </div>

                                <button type="submit" class="boton uno grande">Registrar mi negocio</button>
                            </form>
                        </div>
                    </div>

                    <!-- Filtros Sidebar -->
                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__info">
                            <strong>Filtros Sidebar</strong>
                            <span>directorio.html - Sidebar sticky con filtros</span>
                        </div>
                        <div class="guia-ejemplo__visual" style="padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <aside style="max-width: 300px; background: var(--color-ocho); padding: var(--espacio-cuatro); border-radius: var(--radio-dos);">
                                <h3 style="margin: 0 0 var(--espacio-cuatro); font-size: var(--fs-cinco);">Filtros</h3>
                                <form>
                                    <div style="margin-bottom: var(--espacio-cuatro);">
                                        <label class="formulario-etiqueta">Buscar</label>
                                        <input type="search" class="campo" placeholder="Nombre del crematorio...">
                                    </div>
                                    <div style="margin-bottom: var(--espacio-cuatro);">
                                        <label class="formulario-etiqueta">Comunidad Autónoma</label>
                                        <select class="seleccion">
                                            <option>Todas</option>
                                            <option>Madrid</option>
                                            <option>Cataluña</option>
                                        </select>
                                    </div>
                                    <div style="margin-bottom: var(--espacio-cuatro);">
                                        <label class="formulario-etiqueta">Provincia</label>
                                        <select class="seleccion">
                                            <option>Todas</option>
                                        </select>
                                    </div>
                                    <div style="margin-bottom: var(--espacio-cuatro);">
                                        <label class="formulario-etiqueta">Servicios</label>
                                        <label class="casilla-verificacion" style="margin-bottom: var(--espacio-dos); display: flex;">
                                            <input type="checkbox">
                                            <span class="casilla-verificacion__texto">Cremación individual</span>
                                        </label>
                                        <label class="casilla-verificacion" style="margin-bottom: var(--espacio-dos); display: flex;">
                                            <input type="checkbox">
                                            <span class="casilla-verificacion__texto">Recogida a domicilio</span>
                                        </label>
                                        <label class="casilla-verificacion" style="display: flex;">
                                            <input type="checkbox">
                                            <span class="casilla-verificacion__texto">Urnas personalizadas</span>
                                        </label>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: var(--espacio-dos);">
                                        <button type="submit" class="boton uno">Aplicar filtros</button>
                                        <button type="reset" class="boton tres pequeno">Limpiar</button>
                                    </div>
                                </form>
                            </aside>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 8. ICONOS
                 ═══════════════════════════════════════════════════════════ -->
            <section id="iconos" class="guia-seccion">
                <h2 class="guia-seccion__titulo">8. Iconos</h2>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Biblioteca Lucide Icons</h3>

                    <p style="margin-bottom: var(--espacio-cuatro);">
                        Todos los iconos utilizan la biblioteca <strong>Lucide</strong>.
                        Se cargan mediante el script al final del HTML y se inicializan con <code>lucide.createIcons()</code>.
                    </p>

                    <div class="guia-grid guia-grid--tres">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <i data-lucide="paw-print" class="icono" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>paw-print</strong>
                                <span>Logo principal (huella de mascota)</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <i data-lucide="search" class="icono" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>search</strong>
                                <span>Búsqueda</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <i data-lucide="map-pin" class="icono" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>map-pin</strong>
                                <span>Ubicación</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <i data-lucide="star" class="icono" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>star</strong>
                                <span>Valoraciones</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <i data-lucide="phone" class="icono" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>phone</strong>
                                <span>Teléfono</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <i data-lucide="mail" class="icono" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>mail</strong>
                                <span>Email</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <i data-lucide="menu" class="icono" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>menu</strong>
                                <span>Menú hamburguesa</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <i data-lucide="arrow-right" class="icono" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>arrow-right</strong>
                                <span>Navegación</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <i data-lucide="check-circle" class="icono" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>check-circle</strong>
                                <span>Éxito</span>
                            </div>
                        </div>
                    </div>

                    <pre class="guia-ejemplo__codigo" style="margin-top: var(--espacio-cuatro);">&lt;i data-lucide="heart" class="icono"&gt;&lt;/i&gt;

&lt;!-- Cargar al final del HTML --&gt;
&lt;script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"&gt;&lt;/script&gt;
&lt;script&gt;lucide.createIcons();&lt;/script&gt;</pre>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 9. SECCIONES ESPECIALES
                 ═══════════════════════════════════════════════════════════ -->
            <section id="secciones" class="guia-seccion">
                <h2 class="guia-seccion__titulo">9. Secciones Especiales</h2>
                <p class="guia-descripcion">
                    <strong>Sistema de nomenclatura:</strong> Secciones que NO son héroes/portadas usan .seccion (base) + .seccion.uno, .seccion.dos, etc.
                    <br><strong>Excepciones:</strong> .paginacion mantiene nombre específico.
                    <br><strong>Variantes:</strong> .seccion (fondo claro), .seccion.uno (fondo color-cinco), .seccion.dos (fondo color-dos oscuro con texto blanco)
                </p>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Encabezado de Sección</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__visual" style="flex-direction: column;">
                            <p style="color: var(--color-uno); font-size: var(--fs-dos); font-style: italic; margin-bottom: var(--espacio-dos); text-transform: uppercase; letter-spacing: var(--ls-tres);">
                                Subtítulo/Etiqueta
                            </p>
                            <h2 style="font-size: var(--fs-seis); color: var(--color-dos); margin-bottom: var(--espacio-tres); font-family: var(--fuente-titulo);">
                                Título de la Sección
                            </h2>
                            <p style="color: var(--color-seis-claro); margin: 0;">
                                Descripción opcional de la sección que contextualiza el contenido.
                            </p>
                        </div>
                        <div class="guia-ejemplo__info">
                            <strong>.seccion__encabezado</strong>
                            <span>Usado en todas las secciones principales</span>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Nube de Ciudades</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__visual">
                            <div style="display: flex; flex-wrap: wrap; gap: var(--espacio-tres); justify-content: center;">
                                <a href="#" class="boton tres">Madrid</a>
                                <a href="#" class="boton tres">Barcelona</a>
                                <a href="#" class="boton tres">Valencia</a>
                                <a href="#" class="boton tres">Sevilla</a>
                                <a href="#" class="boton tres">Alicante</a>
                                <a href="#" class="boton tres">Málaga</a>
                            </div>
                        </div>
                        <div class="guia-ejemplo__info">
                            <strong>.ciudades-grid</strong>
                            <span>index.html - 23 botones terciarios con flex wrap</span>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">CTA Pre-Footer</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__visual" style="padding: var(--espacio-seis); background: var(--color-dos); color: white; border-radius: var(--radio-dos); flex-direction: column;">
                            <h2 style="font-size: var(--fs-seis); margin-bottom: var(--espacio-cuatro); color: white; font-family: var(--fuente-titulo);">
                                ¿Tienes un crematorio de mascotas?
                            </h2>
                            <p style="color: rgba(255, 255, 255, 0.8); max-width: 600px; margin: 0 auto var(--espacio-cinco);">
                                Únete a nuestro directorio y conecta con familias que buscan servicios de cremación.
                            </p>
                            <button class="boton uno grande">
                                Registrar mi Crematorio
                                <i data-lucide="arrow-right" class="icono"></i>
                            </button>
                        </div>
                        <div class="guia-ejemplo__info">
                            <strong>.seccion.dos</strong>
                            <span>Fondo oscuro + texto centrado + CTA grande</span>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Grid de Características</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__visual">
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--espacio-cuatro);">
                                <div style="text-align: center;">
                                    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--color-uno-claro); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--espacio-cuatro);">
                                        <i data-lucide="shield-check" class="icono" style="color: var(--color-dos); width: 36px; height: 36px;"></i>
                                    </div>
                                    <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); margin-bottom: var(--espacio-dos);">Verificados</h3>
                                    <p style="font-size: var(--fs-tres); color: var(--color-seis-claro); margin: 0;">Descripción</p>
                                </div>
                                <div style="text-align: center;">
                                    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--color-uno-claro); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--espacio-cuatro);">
                                        <i data-lucide="star" class="icono" style="color: var(--color-dos); width: 36px; height: 36px;"></i>
                                    </div>
                                    <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); margin-bottom: var(--espacio-dos);">Reseñas Reales</h3>
                                    <p style="font-size: var(--fs-tres); color: var(--color-seis-claro); margin: 0;">Descripción</p>
                                </div>
                                <div style="text-align: center;">
                                    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--color-cuatro); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--espacio-cuatro);">
                                        <i data-lucide="clock" class="icono" style="color: var(--color-dos); width: 36px; height: 36px;"></i>
                                    </div>
                                    <h3 style="font-size: var(--fs-dos); font-weight: var(--peso-negrita); margin-bottom: var(--espacio-dos);">24/7</h3>
                                    <p style="font-size: var(--fs-tres); color: var(--color-seis-claro); margin: 0;">Descripción</p>
                                </div>
                            </div>
                        </div>
                        <div class="guia-ejemplo__info">
                            <strong>.caracteristica</strong>
                            <span>3 columnas con iconos circulares 80px + título + descripción</span>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Paginación</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__visual">
                            <div style="display: flex; gap: var(--espacio-dos); align-items: center;">
                                <button class="boton tres" disabled>&larr; Anterior</button>
                                <button class="boton uno">1</button>
                                <button class="boton tres">2</button>
                                <button class="boton tres">3</button>
                                <span>...</span>
                                <button class="boton tres">10</button>
                                <button class="boton tres">Siguiente &rarr;</button>
                            </div>
                        </div>
                        <div class="guia-ejemplo__info">
                            <strong>.paginacion</strong>
                            <span>directorio.html - Botones números + flechas + elipsis</span>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Sin Resultados</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__visual" style="flex-direction: column; padding: var(--espacio-seis);">
                            <i data-lucide="search" class="icono" style="width: 80px; height: 80px; color: var(--color-seis-claro); margin-bottom: var(--espacio-cuatro);"></i>
                            <h3 style="font-size: var(--fs-cinco); margin-bottom: var(--espacio-tres); color: var(--color-dos);">No se encontraron resultados</h3>
                            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cuatro);">
                                Intenta ajustar los filtros o realizar una nueva búsqueda
                            </p>
                            <button class="boton dos">Limpiar filtros</button>
                        </div>
                        <div class="guia-ejemplo__info">
                            <strong>Estado sin resultados</strong>
                            <span>directorio.html - Icono 80px + título + texto + botón</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 10. FOOTERS
                 ═══════════════════════════════════════════════════════════ -->
            <section id="footers" class="guia-seccion">
                <h2 class="guia-seccion__titulo">10. Footers</h2>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Footer Principal</h3>
                    <p class="guia-descripcion">
                        <strong>Sistema de variantes:</strong> .footer (base), .footer.dos, .footer.tres para variantes futuras.
                        Grid 4 columnas en desktop, columna única en móvil.
                    </p>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__visual" style="padding: 0;">
                            <footer class="footer">
                                <div class="footer__contenedor">
                                    <div class="footer__grid">
                                        <!-- Columna 1: Sobre nosotros + Redes -->
                                        <div class="footer__seccion">
                                            <h3 class="footer__titulo">Crematorios de Mascotas</h3>
                                            <p class="footer__texto">
                                                Directorio de crematorios profesionales para despedir a tu mascota con amor y dignidad.
                                            </p>
                                            <div class="footer__redes">
                                                <a href="#" class="footer__red" aria-label="Facebook">
                                                    <i data-lucide="facebook"></i>
                                                </a>
                                                <a href="#" class="footer__red" aria-label="Instagram">
                                                    <i data-lucide="instagram"></i>
                                                </a>
                                                <a href="#" class="footer__red" aria-label="Twitter">
                                                    <i data-lucide="twitter"></i>
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Columna 2: Navegación -->
                                        <div class="footer__seccion">
                                            <h3 class="footer__titulo">Navegación</h3>
                                            <ul class="footer__lista">
                                                <li><a href="/directorio.html" class="footer__enlace">
                                                    <i data-lucide="map" class="icono"></i> Directorio
                                                </a></li>
                                                <li><a href="/nosotros.html" class="footer__enlace">
                                                    <i data-lucide="users" class="icono"></i> Nosotros
                                                </a></li>
                                                <li><a href="/contacto.html" class="footer__enlace">
                                                    <i data-lucide="mail" class="icono"></i> Contacto
                                                </a></li>
                                            </ul>
                                        </div>

                                        <!-- Columna 3: Para Negocios -->
                                        <div class="footer__seccion">
                                            <h3 class="footer__titulo">Para Negocios</h3>
                                            <ul class="footer__lista">
                                                <li><a href="/registrar-negocio.html" class="footer__enlace">
                                                    <i data-lucide="briefcase" class="icono"></i> Registrar Negocio
                                                </a></li>
                                                <li><a href="#" class="footer__enlace">
                                                    <i data-lucide="star" class="icono"></i> Beneficios
                                                </a></li>
                                                <li><a href="#" class="footer__enlace">
                                                    <i data-lucide="help-circle" class="icono"></i> Preguntas Frecuentes
                                                </a></li>
                                            </ul>
                                        </div>

                                        <!-- Columna 4: Contacto -->
                                        <div class="footer__seccion">
                                            <h3 class="footer__titulo">Contacto</h3>
                                            <ul class="footer__lista">
                                                <li><a href="mailto:info@crematoriosdemascotas.com" class="footer__enlace">
                                                    <i data-lucide="mail" class="icono"></i> info@crematoriosdemascotas.com
                                                </a></li>
                                                <li><a href="tel:+34600000000" class="footer__enlace">
                                                    <i data-lucide="phone" class="icono"></i> +34 600 000 000
                                                </a></li>
                                                <li>
                                                    <span class="footer__enlace">
                                                        <i data-lucide="clock" class="icono"></i> Lun-Dom 24/7
                                                    </span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Copyright -->
                                    <div class="footer__copyright">
                                        <p>&copy; 2026 Crematorios de Mascotas. Todos los derechos reservados.</p>
                                    </div>
                                </div>
                            </footer>
                        </div>
                        <div class="guia-ejemplo__info">
                            <strong>.footer</strong>
                            <span>Grid 4 columnas con iconos Lucide en enlaces</span>
                            <span>Redes sociales en columna 1, copyright centrado al final</span>
                            <span>Responsive: columna única en móvil</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════
                 11. LAYOUTS Y GRILLAS
                 ═══════════════════════════════════════════════════════════ -->
            <section id="layouts" class="guia-seccion">
                <h2 class="guia-seccion__titulo">11. Layouts y Grillas</h2>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Grid de Tarjetas</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__info">
                            <strong>.grid-tarjetas</strong>
                            <span>repeat(auto-fit, minmax(300px, 1fr))</span>
                            <span>Gap: var(--espacio-cuatro)</span>
                        </div>
                        <pre class="guia-ejemplo__codigo">.grid-tarjetas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--espacio-cuatro);
}</pre>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Layout Sidebar + Main</h3>

                    <div class="guia-ejemplo">
                        <div class="guia-ejemplo__info">
                            <strong>Usado en:</strong>
                            <span>directorio.html - Filtros sidebar + listado</span>
                            <span>registrar-negocio.html - Beneficios sidebar + formulario</span>
                        </div>
                        <pre class="guia-ejemplo__codigo">.layout-dos-columnas {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--espacio-cinco);
}

@media (min-width: 768px) {
    .layout-dos-columnas {
        grid-template-columns: 1fr 1fr;
    }
}</pre>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Contenedores Máximos</h3>

                    <table class="tipo-tabla">
                        <thead>
                            <tr>
                                <th>Variable</th>
                                <th>Tamaño</th>
                                <th>Uso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>--contenedor-uno</code></td>
                                <td>640px</td>
                                <td>Textos estrechos, formularios simples</td>
                            </tr>
                            <tr>
                                <td><code>--contenedor-dos</code></td>
                                <td>768px</td>
                                <td>Contenido medio, heros</td>
                            </tr>
                            <tr>
                                <td><code>--contenedor-tres</code></td>
                                <td>1024px</td>
                                <td>Secciones principales</td>
                            </tr>
                            <tr>
                                <td><code>--contenedor-cuatro</code></td>
                                <td>1200px</td>
                                <td>Grids de tarjetas, contenido amplio</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Sistema de Espaciado</h3>

                    <table class="tipo-tabla">
                        <thead>
                            <tr>
                                <th>Variable</th>
                                <th>Tamaño</th>
                                <th>Uso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>--espacio-uno</code></td>
                                <td>0.25rem (4px)</td>
                                <td>Espaciado mínimo</td>
                            </tr>
                            <tr>
                                <td><code>--espacio-dos</code></td>
                                <td>0.5rem (8px)</td>
                                <td>Espaciado pequeño</td>
                            </tr>
                            <tr>
                                <td><code>--espacio-tres</code></td>
                                <td>1rem (16px)</td>
                                <td>Espaciado estándar</td>
                            </tr>
                            <tr>
                                <td><code>--espacio-cuatro</code></td>
                                <td>1.5rem (24px)</td>
                                <td>Espaciado medio</td>
                            </tr>
                            <tr>
                                <td><code>--espacio-cinco</code></td>
                                <td>2.5rem (40px)</td>
                                <td>Espaciado grande</td>
                            </tr>
                            <tr>
                                <td><code>--espacio-seis</code></td>
                                <td>4rem (64px)</td>
                                <td>Padding de secciones</td>
                            </tr>
                            <tr>
                                <td><code>--espacio-siete</code></td>
                                <td>6rem (96px)</td>
                                <td>Separación entre secciones</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Border Radius</h3>

                    <div class="guia-grid guia-grid--tres">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <div style="width: 100px; height: 60px; background: var(--color-uno); border-radius: var(--radio-uno);"></div>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>--radio-uno</strong>
                                <span>6px - Botones, inputs</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <div style="width: 100px; height: 60px; background: var(--color-uno); border-radius: var(--radio-dos);"></div>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>--radio-dos</strong>
                                <span>10px - Tarjetas</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <div style="width: 100px; height: 60px; background: var(--color-uno); border-radius: var(--radio-tres);"></div>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>--radio-tres</strong>
                                <span>16px - Elementos grandes</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <div style="width: 100px; height: 60px; background: var(--color-uno); border-radius: var(--radio-full);"></div>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>--radio-full</strong>
                                <span>9999px - Pills, circulares</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="guia-subseccion">
                    <h3 class="guia-subseccion__titulo">Sombras</h3>

                    <div class="guia-grid guia-grid--dos">
                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <div style="width: 200px; height: 100px; background: white; box-shadow: var(--sombra-uno);"></div>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>--sombra-uno</strong>
                                <span>0 2px 12px rgba(90, 62, 47, 0.08)</span>
                                <span>Sombra sutil para tarjetas</span>
                            </div>
                        </div>

                        <div class="guia-ejemplo">
                            <div class="guia-ejemplo__visual">
                                <div style="width: 200px; height: 100px; background: white; box-shadow: var(--sombra-dos);"></div>
                            </div>
                            <div class="guia-ejemplo__info">
                                <strong>--sombra-dos</strong>
                                <span>0 8px 32px rgba(90, 62, 47, 0.12)</span>
                                <span>Sombra elevada para modales</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         SCRIPTS
         ═══════════════════════════════════════════════════════════ -->
    <!-- TODO: Cambiar a assets/js/lucide.min.js cuando el proyecto esté en producción -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        // Inicializar Lucide Icons
        lucide.createIcons();

        // Smooth scroll para navegación
        document.querySelectorAll('.guia-nav__enlace').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>

</body>
</html>
