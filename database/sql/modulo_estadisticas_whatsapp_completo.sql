-- =====================================================================
-- MÓDULO ESTADÍSTICAS DE MENSAJES WHATSAPP - SENA — SQL COMPLETO
-- Solo lectura sobre datos existentes de Seguimiento de Aspirantes.
-- No crea tabla de historial (reutiliza el snapshot por aspirante).
--
-- Solo SQL puro. No usa `php artisan migrate`.
--
-- DEPENDENCIA: requiere que `seguimientoAspirantes`, `permissions`,
-- `roles` y `role_has_permissions` ya existan.
--
-- Ejecutar completo, de una sola vez, con backup previo.
-- =====================================================================

START TRANSACTION;

-- 1) Columna aditiva: plantilla usada en el último envío por aspirante.
ALTER TABLE `seguimientoAspirantes`
  ADD COLUMN `ultimaPlantilla` VARCHAR(255) NULL DEFAULT NULL AFTER `waMessageId`;

-- 2) Permiso del módulo (guard_name = 'web', igual que el resto del proyecto).
-- SIN icon/path a propósito: el menú dinámico (permisos_jerarquia) convierte
-- cualquier permiso raíz CON path en un ítem de sidebar propio. Este permiso
-- solo gatea la pestaña "Estadísticas WhatsApp" dentro de Seguimiento de
-- Aspirantes, no debe aparecer como entrada de menú separada.
INSERT INTO `permissions` (`name`, `guard_name`, `description`, `icon`, `path`, `created_at`, `updated_at`)
SELECT 'GESTION_ESTADISTICAS_WHATSAPP_SENA', 'web', 'Estadísticas WhatsApp', NULL, NULL, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `permissions` WHERE `name` = 'GESTION_ESTADISTICAS_WHATSAPP_SENA' AND `guard_name` = 'web'
);

-- 3) Asignar el permiso a los mismos roles que ya tienen GESTION_SEGUIMIENTO_ASPIRANTES.
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id
FROM `permissions` p
JOIN `roles` r ON r.name IN ('ADMINISTRADOR VT', 'ADMIN REGIONAL', 'ADMIN CENTRO')
WHERE p.name = 'GESTION_ESTADISTICAS_WHATSAPP_SENA'
  AND NOT EXISTS (
    SELECT 1 FROM `role_has_permissions` rhp
    WHERE rhp.permission_id = p.id AND rhp.role_id = r.id
  );

COMMIT;

-- =====================================================================
-- OBLIGATORIO DESPUÉS DE CORRER ESTE SCRIPT EN PRODUCCIÓN:
--   php artisan permission:cache-reset
-- Y los usuarios con esos roles deben volver a iniciar sesión para que
-- su JWT incluya el permiso nuevo (se arma solo en login).
-- =====================================================================

-- Verificación:
-- SHOW COLUMNS FROM seguimientoAspirantes LIKE 'ultimaPlantilla';
-- SELECT * FROM permissions WHERE name = 'GESTION_ESTADISTICAS_WHATSAPP_SENA';
-- SELECT r.name FROM roles r
--   JOIN role_has_permissions rhp ON rhp.role_id = r.id
--   JOIN permissions p ON p.id = rhp.permission_id
--   WHERE p.name = 'GESTION_ESTADISTICAS_WHATSAPP_SENA';
