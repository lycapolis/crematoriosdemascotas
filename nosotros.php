<?php
/**
 * ═══════════════════════════════════════════════════════════
 * SOBRE NOSOTROS - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 03
 * Fecha: Enero 2026
 * ═══════════════════════════════════════════════════════════
 */

$titulo_pagina = 'Sobre Nosotros - Crematorios de Mascotas';
$pagina_actual = 'nosotros';
include 'includes/header.php';
?>

    <!-- ═══════════════════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════════════════ -->
    <section class="hero hero-cuatro">
        <div class="contenedor">
            <p class="seccion__subtitulo">Nuestra Historia</p>
            <h1>Sobre Nosotros</h1>
            <h2 class="seccion__descripcion estilo-h5 seis">
                Creamos este directorio con un propósito claro: ayudar a las familias
                a encontrar el lugar perfecto para despedir a sus mascotas con dignidad,
                respeto y amor.
            </h2>
            
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         MISIÓN, VISIÓN, COMPROMISO
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">
            <div class="grid-tarjetas">
                <!-- Misión -->
                <article class="item-uno">
                    <div class="caracteristica__icono">
                        <i data-lucide="target" class="icono"></i>
                    </div>
                    <h3>Nuestra Misión</h3>
                    <p>
                        Conectar familias con crematorios de mascotas confiables y profesionales,
                        facilitando el proceso de despedida en momentos difíciles.
                    </p>
                </article>

                <!-- Visión -->
                <article class="item-uno">
                    <div class="caracteristica__icono">
                        <i data-lucide="eye" class="icono"></i>
                    </div>
                    <h3>Nuestra Visión</h3>
                    <p>
                        Ser el directorio de referencia en servicios de cremación para mascotas,
                        reconocido por la calidad y confiabilidad de la información que ofrecemos.
                    </p>
                </article>

                <!-- Compromiso -->
                <article class="item-uno">
                    <div class="caracteristica__icono">
                        <i data-lucide="heart" class="icono"></i>
                    </div>
                    <h3>Nuestro Compromiso</h3>
                    <p>
                        Tratamos cada caso con la sensibilidad que merece. Sabemos que perder
                        una mascota es perder un miembro de la familia.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         HISTORIA
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion uno">
        <div class="contenedor">
            <div class="layout-dos-columnas">
                <div>
                    <p class="seccion__subtitulo">¿Por qué existimos?</p>
                    <h2>Una necesidad real</h2>
                    <p>
                        Este proyecto nació de una experiencia personal. Cuando perdimos a nuestra
                        mascota, nos dimos cuenta de lo difícil que era encontrar información
                        confiable sobre servicios de cremación.
                    </p>
                    <p>
                        Buscamos en internet, preguntamos a conocidos, y el proceso fue abrumador
                        en un momento ya de por sí difícil. Fue entonces cuando decidimos crear
                        este directorio.
                    </p>
                    <p>
                        Nuestro objetivo es simple: que ninguna familia tenga que pasar por esa
                        incertidumbre. Queremos que encontrar un crematorio de confianza sea
                        fácil, rápido y transparente.
                    </p>
                </div>

                <div>
                    <img
                        src="https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&h=500&fit=crop"
                        alt="Mascota descansando"
                        loading="lazy"
                        style="width: 100%; border-radius: var(--radio-dos);"
                    >
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         VALORES
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion">
        <div class="contenedor">
            <div class="seccion__encabezado">
                <p class="seccion__subtitulo">Lo que nos guía</p>
                <h2 class="seccion__titulo">Nuestros Valores</h2>
            </div>

            <div style="max-width: var(--contenedor-dos); margin: 0 auto;">
                <div class="layout-dos-columnas" style="gap: var(--espacio-cinco);">
                    <!-- Valor 1 -->
                    <article class="item-dos" style="background: var(--color-cinco); border-radius: var(--radio-dos); padding: var(--espacio-cuatro);">
                        <span class="boton uno" style="width: var(--espacio-cinco); height: var(--espacio-cinco); border-radius: var(--radio-full); flex-shrink: 0;">1</span>
                        <div>
                            <h3>Empatía</h3>
                            <p>
                                Entendemos el dolor de perder a una mascota. Cada interacción
                                está guiada por la comprensión y el respeto.
                            </p>
                        </div>
                    </article>

                    <!-- Valor 2 -->
                    <article class="item-dos" style="background: var(--color-cinco); border-radius: var(--radio-dos); padding: var(--espacio-cuatro);">
                        <span class="boton uno" style="width: var(--espacio-cinco); height: var(--espacio-cinco); border-radius: var(--radio-full); flex-shrink: 0;">2</span>
                        <div>
                            <h3>Transparencia</h3>
                            <p>
                                Mostramos información clara y honesta sobre cada crematorio,
                                incluyendo precios, servicios y reseñas reales.
                            </p>
                        </div>
                    </article>

                    <!-- Valor 3 -->
                    <article class="item-dos" style="background: var(--color-cinco); border-radius: var(--radio-dos); padding: var(--espacio-cuatro);">
                        <span class="boton uno" style="width: var(--espacio-cinco); height: var(--espacio-cinco); border-radius: var(--radio-full); flex-shrink: 0;">3</span>
                        <div>
                            <h3>Calidad</h3>
                            <p>
                                Solo incluimos crematorios que cumplen con estándares de
                                profesionalismo y servicio de calidad.
                            </p>
                        </div>
                    </article>

                    <!-- Valor 4 -->
                    <article class="item-dos" style="background: var(--color-cinco); border-radius: var(--radio-dos); padding: var(--espacio-cuatro);">
                        <span class="boton uno" style="width: var(--espacio-cinco); height: var(--espacio-cinco); border-radius: var(--radio-full); flex-shrink: 0;">4</span>
                        <div>
                            <h3>Accesibilidad</h3>
                            <p>
                                Hacemos que la información sea fácil de encontrar y entender,
                                disponible cuando más se necesita.
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         CTA
         ═══════════════════════════════════════════════════════════ -->
    <section class="seccion dos">
        <div class="contenedor" style="text-align: center;">
            <h2>¿Eres dueño de un crematorio?</h2>
            <p class="seccion__descripcion" style="color: var(--color-ocho-claro); margin-bottom: var(--espacio-cinco);">
                Únete a nuestro directorio y ayuda a más familias a encontrar
                servicios de cremación de calidad para sus mascotas.
            </p>
            <a href="registrar-negocio.php" class="boton uno grande">
                Registrar mi Crematorio
            </a>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
