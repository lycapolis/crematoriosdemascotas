-- Cupo de entregas de datos de leads por plan (tarea 03b).
-- cupo_leads = nº de entregas de por vida por ficha (filas de lead_entregas). NULL = ilimitado.
-- El plan 00 (Gratis) arranca con 5; el resto queda ilimitado (reciben los datos completos en el email).
-- Se edita desde admin/editar-tier.php.
-- Ejecutar con utf8mb4:  mysql --default-character-set=utf8mb4 ...

ALTER TABLE tiers
    ADD COLUMN cupo_leads INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Entregas de datos de leads de por vida por ficha (lead_entregas). NULL = ilimitado'
    AFTER precio_mensual;

UPDATE tiers SET cupo_leads = 5 WHERE id = '00';
