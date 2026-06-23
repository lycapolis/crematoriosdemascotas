-- ═══════════════════════════════════════════════════════════════════
-- REGENERAR SLUGS — 90 fichas — 2026-06-23
-- ═══════════════════════════════════════════════════════════════════
-- Regenera los slugs de las 90 fichas activas con la nueva lógica:
-- nombre_acortado + (keyword si nombre no tiene contexto) + ciudad
-- Para palabras ambiguas (funeraria/crematorio/tanatorio/etc.) se
-- agrega 'mascotas' para clarificar el nicho.
--
-- Generado a partir de plan_v2.tsv (procesado con Claude Haiku 4.5).
-- Ejecutar UNA SOLA VEZ. Idempotente (los UPDATEs son por id exacto).
-- ═══════════════════════════════════════════════════════════════════

UPDATE crematorios SET slug = 'cresma-valencia-crematorio-mascotas-alaquas', updated_at = NOW() WHERE id = 1;
UPDATE crematorios SET slug = 'funeraria-mascotas-san-antonio-abad-paracuellos-de-jarama', updated_at = NOW() WHERE id = 2;
UPDATE crematorios SET slug = 'su-amor-deja-huella-crematorio-mascotas-esparreguera', updated_at = NOW() WHERE id = 3;
UPDATE crematorios SET slug = 'cremascota-alcorcon', updated_at = NOW() WHERE id = 4;
UPDATE crematorios SET slug = 'mascarol-crematorio-mascotas-polinya', updated_at = NOW() WHERE id = 5;
UPDATE crematorios SET slug = 'cel-amic-crematorio-mascotas-sant-fruitos-de-bages', updated_at = NOW() WHERE id = 6;
UPDATE crematorios SET slug = 'cremasur-sevilla-salteras', updated_at = NOW() WHERE id = 7;
UPDATE crematorios SET slug = 'cecapa-crematorio-mascotas-maliano', updated_at = NOW() WHERE id = 8;
UPDATE crematorios SET slug = 'infinito-crematorio-mascotas-camas', updated_at = NOW() WHERE id = 9;
UPDATE crematorios SET slug = 'hadescan-crematorio-mascotas-sevilla-la-nueva', updated_at = NOW() WHERE id = 10;
UPDATE crematorios SET slug = 'tanatori-mascotes-barcelona', updated_at = NOW() WHERE id = 11;
UPDATE crematorios SET slug = 'everpet-crematorio-mascotas-collado-villalba', updated_at = NOW() WHERE id = 12;
UPDATE crematorios SET slug = 'recordarte-y-sonreir-crematorio-mascotas-getafe', updated_at = NOW() WHERE id = 13;
UPDATE crematorios SET slug = 'incivet-crematorio-mascotas-chapineria', updated_at = NOW() WHERE id = 14;
UPDATE crematorios SET slug = 'tanapets-mejorada-del-campo', updated_at = NOW() WHERE id = 15;
UPDATE crematorios SET slug = 'cremandogal-vigo', updated_at = NOW() WHERE id = 16;
UPDATE crematorios SET slug = 'sena-valencia-crematorio-mascotas-montserrat', updated_at = NOW() WHERE id = 17;
UPDATE crematorios SET slug = 'cremaguada-crematorio-mascotas-cabanillas-del-campo', updated_at = NOW() WHERE id = 18;
UPDATE crematorios SET slug = 'simas-crematorio-mascotas-cabanas-raras', updated_at = NOW() WHERE id = 19;
UPDATE crematorios SET slug = 'semper-fidelis-crematorio-mascotas-esquivias', updated_at = NOW() WHERE id = 20;
UPDATE crematorios SET slug = 'el-ultimo-paseo-crematorio-mascotas-ciempozuelos', updated_at = NOW() WHERE id = 21;
UPDATE crematorios SET slug = 'eternal-servicios-funerarios-yuncler', updated_at = NOW() WHERE id = 22;
UPDATE crematorios SET slug = 'berquir-canino-anorbe', updated_at = NOW() WHERE id = 23;
UPDATE crematorios SET slug = 'crematorio-mascotas-everest-antas', updated_at = NOW() WHERE id = 24;
UPDATE crematorios SET slug = 'la-morada-de-noe-crematorio-mascotas-san-mateo-de-gallego', updated_at = NOW() WHERE id = 25;
UPDATE crematorios SET slug = 'ibad-crematorio-mascotas-zangroiz', updated_at = NOW() WHERE id = 26;
UPDATE crematorios SET slug = 'elisia-cementerio-crematorio-mascotas-pozo-los-palos', updated_at = NOW() WHERE id = 27;
UPDATE crematorios SET slug = 'infinity-pet-leganes', updated_at = NOW() WHERE id = 28;
UPDATE crematorios SET slug = 'pets-eternity-san-fernando-de-henares', updated_at = NOW() WHERE id = 29;
UPDATE crematorios SET slug = 'inaco-crematorio-mascotas-lorqui', updated_at = NOW() WHERE id = 30;
UPDATE crematorios SET slug = 'humas-crematorio-mascotas-cuarte-de-huerva', updated_at = NOW() WHERE id = 31;
UPDATE crematorios SET slug = 'valcubia-crematorio-mascotas-grado', updated_at = NOW() WHERE id = 32;
UPDATE crematorios SET slug = 'tanatorio-mascotas-ineco-riudarenes', updated_at = NOW() WHERE id = 33;
UPDATE crematorios SET slug = 'caelum-pets-zaragoza', updated_at = NOW() WHERE id = 34;
UPDATE crematorios SET slug = 'tanatorio-mascotas-san-anton-cisterniga', updated_at = NOW() WHERE id = 35;
UPDATE crematorios SET slug = 'crematorio-mascotas-duin-esquiroz', updated_at = NOW() WHERE id = 36;
UPDATE crematorios SET slug = 'inade-crematorio-mascotas-orozco', updated_at = NOW() WHERE id = 37;
UPDATE crematorios SET slug = 'paseo-a-las-nubes-crematorio-mascotas-burgos', updated_at = NOW() WHERE id = 38;
UPDATE crematorios SET slug = 'cremamur-librilla', updated_at = NOW() WHERE id = 39;
UPDATE crematorios SET slug = 'funeraria-mascotas-huella-amiga-madrid', updated_at = NOW() WHERE id = 40;
UPDATE crematorios SET slug = 'caelum-pets-san-fernando-de-henares', updated_at = NOW() WHERE id = 41;
UPDATE crematorios SET slug = 'armony-pets-arroyomolinos', updated_at = NOW() WHERE id = 42;
UPDATE crematorios SET slug = 'mi-fiel-amigo-crematorio-mascotas-elx', updated_at = NOW() WHERE id = 43;
UPDATE crematorios SET slug = 'galimascota-bergondo', updated_at = NOW() WHERE id = 44;
UPDATE crematorios SET slug = 'almascotas-granollers', updated_at = NOW() WHERE id = 45;
UPDATE crematorios SET slug = 'funeraria-mascotas-san-antonio-abad-villanueva-del-pardillo', updated_at = NOW() WHERE id = 46;
UPDATE crematorios SET slug = 'campo-de-gibraltar-crematorio-mascotas-algeciras', updated_at = NOW() WHERE id = 47;
UPDATE crematorios SET slug = 'crematorio-mascotas-tanatur-noain', updated_at = NOW() WHERE id = 48;
UPDATE crematorios SET slug = 'tanatomascota-valladolid', updated_at = NOW() WHERE id = 49;
UPDATE crematorios SET slug = 'agur-vitoria-crematorio-mascotas-vitoria-gasteiz', updated_at = NOW() WHERE id = 50;
UPDATE crematorios SET slug = 'crematorio-mascotas-fuentebet-torrecaballeros', updated_at = NOW() WHERE id = 51;
UPDATE crematorios SET slug = 'recuerda-tu-mascota-albacete', updated_at = NOW() WHERE id = 52;
UPDATE crematorios SET slug = 'perpetual-crematorio-mascotas-logrono', updated_at = NOW() WHERE id = 53;
UPDATE crematorios SET slug = 'pip-galicia-crematorio-mascotas-o-porrino', updated_at = NOW() WHERE id = 54;
UPDATE crematorios SET slug = 'anubis-crematorio-mascotas-torralba-de-calatrava', updated_at = NOW() WHERE id = 55;
UPDATE crematorios SET slug = 'incipet-crematorio-mascotas-navalcarnero', updated_at = NOW() WHERE id = 56;
UPDATE crematorios SET slug = 'incineradora-mascotas-ruta-de-la-plata-guillena', updated_at = NOW() WHERE id = 57;
UPDATE crematorios SET slug = 'fune-mascotas-madrid', updated_at = NOW() WHERE id = 58;
UPDATE crematorios SET slug = 'eutanasia-domicilio-madrid', updated_at = NOW() WHERE id = 59;
UPDATE crematorios SET slug = 'serinmas-crematorio-mascotas-villanueva-del-carnero', updated_at = NOW() WHERE id = 60;
UPDATE crematorios SET slug = 'bosque-del-silencio-crematorio-mascotas-getafe', updated_at = NOW() WHERE id = 61;
UPDATE crematorios SET slug = 'anima-crematorio-mascotas-errenteria', updated_at = NOW() WHERE id = 62;
UPDATE crematorios SET slug = 'su-amor-deja-huella-crematorio-mascotas-barcelona', updated_at = NOW() WHERE id = 63;
UPDATE crematorios SET slug = 'ipa-crematorio-mascotas-dolores', updated_at = NOW() WHERE id = 64;
UPDATE crematorios SET slug = 'san-lesmes-crematorio-mascotas-burgos', updated_at = NOW() WHERE id = 65;
UPDATE crematorios SET slug = 'fune-mascotas-zaragoza', updated_at = NOW() WHERE id = 66;
UPDATE crematorios SET slug = 'reino-animal-lugo', updated_at = NOW() WHERE id = 67;
UPDATE crematorios SET slug = 'golden-flame-crematorio-mascotas-arroyomolinos', updated_at = NOW() WHERE id = 68;
UPDATE crematorios SET slug = 'estrellas-huellas-crematorio-mascotas-vilamarin', updated_at = NOW() WHERE id = 69;
UPDATE crematorios SET slug = 'el-parque-crematorio-mascotas-malaga', updated_at = NOW() WHERE id = 70;
UPDATE crematorios SET slug = 'fune-mascotas-murcia', updated_at = NOW() WHERE id = 71;
UPDATE crematorios SET slug = 'caelum-pets-santiga', updated_at = NOW() WHERE id = 72;
UPDATE crematorios SET slug = 'huella-amiga-crematorio-mascotas-toledo', updated_at = NOW() WHERE id = 73;
UPDATE crematorios SET slug = 'fune-mascotas-alicante', updated_at = NOW() WHERE id = 74;
UPDATE crematorios SET slug = 'soncan-incineradora-mascotas-ciudad-real', updated_at = NOW() WHERE id = 75;
UPDATE crematorios SET slug = 'luz-animal-granollers', updated_at = NOW() WHERE id = 76;
UPDATE crematorios SET slug = 'channelcan-crematorio-mascotas-antas', updated_at = NOW() WHERE id = 77;
UPDATE crematorios SET slug = 'pegadas-eternas-crematorio-mascotas-cambados', updated_at = NOW() WHERE id = 78;
UPDATE crematorios SET slug = 'fune-mascotas-fuenlabrada', updated_at = NOW() WHERE id = 79;
UPDATE crematorios SET slug = 'fune-mascotas-rivas-vaciamadrid', updated_at = NOW() WHERE id = 80;
UPDATE crematorios SET slug = 'fune-mascotas-benidorm', updated_at = NOW() WHERE id = 81;
UPDATE crematorios SET slug = 'fune-mascotas-leganes', updated_at = NOW() WHERE id = 82;
UPDATE crematorios SET slug = 'arco-iris-pets-madrid', updated_at = NOW() WHERE id = 83;
UPDATE crematorios SET slug = 'fune-mascotas-getafe', updated_at = NOW() WHERE id = 84;
UPDATE crematorios SET slug = 'tanatori-mascotes-reus', updated_at = NOW() WHERE id = 85;
UPDATE crematorios SET slug = 'fune-mascotas-majadahonda', updated_at = NOW() WHERE id = 86;
UPDATE crematorios SET slug = 'fune-mascotas-las-rozas-de-madrid', updated_at = NOW() WHERE id = 87;
UPDATE crematorios SET slug = 'fune-mascotas-torrejon-de-ardoz', updated_at = NOW() WHERE id = 88;
UPDATE crematorios SET slug = 'fune-mascotas-torre-pacheco', updated_at = NOW() WHERE id = 89;
UPDATE crematorios SET slug = 'fune-mascotas-villaviciosa-de-odon', updated_at = NOW() WHERE id = 90;

-- Verificación: que las 90 fichas tengan los slugs nuevos
SELECT COUNT(*) AS total, COUNT(DISTINCT slug) AS slugs_unicos FROM crematorios WHERE estado='activa';
