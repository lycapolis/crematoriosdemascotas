/**
 * ═══════════════════════════════════════════════════════════
 * MAPA LEAFLET — pines + popups compartidos
 * ═══════════════════════════════════════════════════════════
 *
 * Source of truth para los pines y popups de crematorios.
 * Usado por cerca.php, cerca-mapa.php, comunidad.php, provincia.php.
 *
 * Expone (en window.MapaCrematorios):
 *   crearIconoNormal()
 *   crearIconoDestacado(p)
 *   popupHtml(p)
 *   crearClusterConPuntos(map, puntos, opts)
 *
 * Estilos relacionados (componentes.css):
 *   .map-pin / .map-pin__dot
 *   .map-pin-destacado / .map-pin-destacado__inner
 *   .map-popup / .map-popup__foto / .map-popup__cuerpo / .map-popup__nombre /
 *   .map-popup__ubic / .map-popup__meta / .map-popup__km / .map-popup__badges /
 *   .map-popup__badge--destacado/--verificado/--registrado
 *
 * Estructura esperada del punto (todos los campos opcionales salvo lat/lng/nombre/url):
 *   {
 *     lat, lng,                  // requeridos
 *     nombre, url,               // requeridos
 *     foto,                      // URL absoluta de la foto principal (opcional)
 *     ubicacion,                 // "Ciudad, Provincia" (opcional)
 *     rating,                    // "4.7" string formateado (opcional)
 *     reviews,                   // entero (opcional)
 *     km,                        // distancia al usuario, solo en cerca (opcional)
 *     verificado, destacado, registrado  // booleanos (opcionales)
 *   }
 * ═══════════════════════════════════════════════════════════
 */
(function (global) {
    'use strict';

    function crearIconoNormal() {
        return L.divIcon({
            className: 'map-pin',
            html: '<div class="map-pin__dot"></div>',
            iconSize: [22, 22],
            iconAnchor: [11, 22]
        });
    }

    function crearIconoDestacado(p) {
        var nombre = p.nombre || '';
        var label = (p.rating ? '★ ' + p.rating + ' · ' : '') +
                    (nombre.length > 18 ? nombre.substring(0, 16) + '…' : nombre);
        return L.divIcon({
            className: 'map-pin-destacado',
            html: '<div class="map-pin-destacado__inner">' + label + '</div>',
            iconSize: null,
            iconAnchor: [10, 14]
        });
    }

    function popupHtml(p) {
        var html = '<a href="' + p.url + '" class="map-popup">';
        if (p.foto) {
            html += '<div class="map-popup__foto" style="background-image:url(\'' + p.foto + '\');"></div>';
        }
        html += '<div class="map-popup__cuerpo">';
        html += '<div class="map-popup__nombre">' + p.nombre + '</div>';
        if (p.ubicacion) {
            html += '<div class="map-popup__ubic">' + p.ubicacion + '</div>';
        }
        var meta = [];
        if (p.rating)   meta.push('<strong>★ ' + p.rating + '</strong>');
        if (p.reviews)  meta.push(p.reviews + ' reseñas');
        if (p.km != null) meta.push('<span class="map-popup__km">' + p.km + ' km</span>');
        if (meta.length) {
            html += '<div class="map-popup__meta">' + meta.join(' · ') + '</div>';
        }
        if (p.verificado || p.registrado || p.destacado) {
            html += '<div class="map-popup__badges">';
            if (p.destacado)  html += '<span class="map-popup__badge map-popup__badge--destacado">Destacado</span>';
            if (p.verificado) html += '<span class="map-popup__badge map-popup__badge--verificado">Verificado</span>';
            if (p.registrado) html += '<span class="map-popup__badge map-popup__badge--registrado">Registrado</span>';
            html += '</div>';
        }
        html += '</div></a>';
        return html;
    }

    /**
     * Agrega markers + popups (con hover-open + cluster) a un mapa ya inicializado.
     *
     * @param {L.Map}   map
     * @param {Array}   puntos     Cada uno con `id` (entero) si querés usar marcadoresPorId
     * @param {Object}  opts
     *   - maxClusterRadius (default 50)
     *   - popupMaxWidth (default 320)
     *   - popupMinWidth (default 280)
     *
     * @returns {Object} { cluster, marcadoresPorId }
     *   - cluster: el L.markerClusterGroup (para fitBounds u otras operaciones)
     *   - marcadoresPorId: dict { id → L.marker } para hacer sync de hover con
     *     las cards del listado (ver region-mapa.php / cerca-mapa.php).
     *
     * Compat: el retorno anterior era el cluster directo. Si alguien hace
     *   var cluster = crearClusterConPuntos(...)
     * ahora obtiene `{cluster, marcadoresPorId}`. Para mantener compat, exponemos
     * el cluster también en .cluster del retorno.
     */
    function crearClusterConPuntos(map, puntos, opts) {
        opts = opts || {};
        var timerCierre = null;
        var marcadoresPorId = {};
        var cluster = L.markerClusterGroup({
            maxClusterRadius: opts.maxClusterRadius || 50
        });

        puntos.forEach(function (p) {
            var icono = p.destacado ? crearIconoDestacado(p) : crearIconoNormal();
            var marker = L.marker([p.lat, p.lng], {
                icon: icono,
                zIndexOffset: p.destacado ? 1000 : 0,
                riseOnHover: true
            });
            marker.bindPopup(popupHtml(p), {
                closeButton: false,
                autoClose: true,
                closeOnEscapeKey: true,
                maxWidth: opts.popupMaxWidth || 320,
                minWidth: opts.popupMinWidth || 280,
                offset: [0, -4],
                className: 'map-popup-wrap'
            });
            marker.on('mouseover', function () {
                clearTimeout(timerCierre);
                this.openPopup();
            });
            marker.on('mouseout', function () {
                var that = this;
                timerCierre = setTimeout(function () { that.closePopup(); }, 200);
            });
            cluster.addLayer(marker);
            // Index por id (solo si el punto trae id; algunos partials no lo pasan).
            if (p.id != null) {
                marcadoresPorId[p.id] = marker;
            }
        });

        map.addLayer(cluster);

        // Mantener popup si el mouse pasa al popup
        map.on('popupopen', function (e) {
            var el = e.popup.getElement();
            if (!el) return;
            el.addEventListener('mouseenter', function () { clearTimeout(timerCierre); });
            el.addEventListener('mouseleave', function () {
                timerCierre = setTimeout(function () { e.popup._source.closePopup(); }, 200);
            });
        });

        return { cluster: cluster, marcadoresPorId: marcadoresPorId };
    }

    /**
     * Dibuja un efecto "spotlight" sobre un mapa: cubre el mundo con un overlay
     * oscuro semi-transparente y deja un agujero circular en el centro de la región.
     * El resultado es que los crematorios de la región quedan "iluminados" y el resto
     * del mundo se ve atenuado. Da foco visual claro.
     *
     * @param {L.Map}   map
     * @param {Object}  opts
     *   - lat, lng (requerido): centro del foco
     *   - puntos (opcional): array de {lat,lng}. Si se pasa, el radio se calcula
     *     como la distancia del centro al punto más lejano + margen.
     *   - radioMetros (opcional): radio fijo en metros. Si está, prevalece sobre puntos.
     *   - margenPct (default 25): % de margen extra alrededor del bbox
     *   - radioMinimoMetros (default 3000): radio mínimo si solo hay 1 punto / dispersión nula
     *   - color (default rgba(20,14,8,0.4)): color del overlay oscuro
     *   - sides (default 96): segmentos del círculo (más = más liso)
     *
     * @returns {L.Polygon} el polígono creado (por si el llamador quiere removerlo después)
     */
    function dibujarSpotlight(map, opts) {
        opts = opts || {};
        var lat   = opts.lat;
        var lng   = opts.lng;
        if (typeof lat !== 'number' || typeof lng !== 'number') return null;

        var center = L.latLng(lat, lng);
        var radioMetros = opts.radioMetros;

        if (typeof radioMetros !== 'number' && Array.isArray(opts.puntos) && opts.puntos.length) {
            // Calculamos la distancia máxima del centro a cualquier punto
            var maxDist = 0;
            opts.puntos.forEach(function (p) {
                var d = center.distanceTo([p.lat, p.lng]);
                if (d > maxDist) maxDist = d;
            });
            var margen = (typeof opts.margenPct === 'number' ? opts.margenPct : 25) / 100;
            radioMetros = maxDist * (1 + margen);
        }
        if (typeof radioMetros !== 'number' || radioMetros <= 0) {
            radioMetros = opts.radioMinimoMetros || 3000;
        }
        radioMetros = Math.max(radioMetros, opts.radioMinimoMetros || 3000);

        // Polígono "mundo" (sentido horario)
        var mundo = [[90, -180], [90, 180], [-90, 180], [-90, -180]];

        // Círculo aproximado con N segmentos (sentido antihorario → agujero)
        var sides    = opts.sides || 96;
        var R_TIERRA = 6378137;  // radio terrestre medio en metros
        var dLatBase = (radioMetros / R_TIERRA) * (180 / Math.PI);
        var cosLat   = Math.cos(lat * Math.PI / 180);
        var dLngBase = dLatBase / (cosLat || 1);
        var circulo = [];
        for (var i = sides - 1; i >= 0; i--) {  // i decreciente → antihorario
            var ang = (i / sides) * 2 * Math.PI;
            circulo.push([
                lat + dLatBase * Math.cos(ang),
                lng + dLngBase * Math.sin(ang)
            ]);
        }

        var poligono = L.polygon([mundo, circulo], {
            color:       'transparent',
            stroke:      false,
            fillColor:   opts.color || 'rgba(20, 14, 8, 0.4)',
            fillOpacity: 1,
            interactive: false,
            pane:        'overlayPane'
        });
        poligono.addTo(map);
        return poligono;
    }

    /**
     * Variante de spotlight que usa una geometría real (frontera administrativa)
     * en lugar de un círculo. Útil para iluminar el contorno exacto de un país,
     * comunidad o provincia.
     *
     * Internamente dibuja un polígono con el mundo como anillo exterior y todos
     * los anillos del polígono/multipolygon como anillos interiores. Leaflet
     * (SVG, fill-rule: evenodd) trata los anillos interiores como agujeros, de
     * modo que la región queda iluminada y el resto del mundo, atenuado.
     *
     * @param {L.Map}   map
     * @param {Object}  geometry  GeoJSON.geometry: Polygon o MultiPolygon
     * @param {Object}  opts
     *   - color (default rgba(20,14,8,0.4))
     *
     * @returns {L.Polygon} polígono creado (o null si geometría inválida)
     */
    function dibujarSpotlightPoligono(map, geometry, opts) {
        opts = opts || {};
        if (!geometry || !geometry.type) return null;

        // Polígono exterior: el mundo entero (sentido horario)
        var rings = [[[90, -180], [90, 180], [-90, 180], [-90, -180]]];

        function agregarPolygon(coords) {
            // coords = array de rings; el primero es el outer, el resto huecos del
            // polígono original (que nos da igual — usamos solo el outer).
            if (!coords.length) return;
            var outer = coords[0];
            if (!outer.length) return;
            // GeoJSON viene en [lng, lat]; Leaflet quiere [lat, lng].
            var convertido = new Array(outer.length);
            for (var i = 0; i < outer.length; i++) {
                convertido[i] = [outer[i][1], outer[i][0]];
            }
            rings.push(convertido);
        }

        if (geometry.type === 'Polygon') {
            agregarPolygon(geometry.coordinates);
        } else if (geometry.type === 'MultiPolygon') {
            geometry.coordinates.forEach(agregarPolygon);
        } else {
            return null;
        }

        var poligono = L.polygon(rings, {
            color:       'transparent',
            stroke:      false,
            fillColor:   opts.color || 'rgba(20, 14, 8, 0.4)',
            fillOpacity: 1,
            fillRule:    'evenodd',
            interactive: false,
            pane:        'overlayPane'
        });
        poligono.addTo(map);
        return poligono;
    }

    global.MapaCrematorios = {
        crearIconoNormal: crearIconoNormal,
        crearIconoDestacado: crearIconoDestacado,
        popupHtml: popupHtml,
        crearClusterConPuntos: crearClusterConPuntos,
        dibujarSpotlight: dibujarSpotlight,
        dibujarSpotlightPoligono: dibujarSpotlightPoligono
    };
})(window);
