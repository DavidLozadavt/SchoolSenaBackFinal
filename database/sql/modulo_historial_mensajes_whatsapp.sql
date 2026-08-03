-- =====================================================================
-- REFACTOR ESTADÍSTICAS WHATSAPP — Historial real de mensajes
-- Solo SQL puro. No usa `php artisan migrate`.
--
-- Antes: las estadísticas leían el snapshot de `seguimientoAspirantes`
-- (solo el ÚLTIMO envío por aspirante). Ahora leen esta tabla nueva,
-- que guarda UN REGISTRO POR CADA ENVÍO (nunca se sobrescribe).
--
-- No modifica el flujo de envío ni el webhook existentes: `seguimientoAspirantes`
-- sigue actualizándose exactamente igual que antes. Esta tabla es aditiva,
-- solo para auditoría/estadísticas/reportes.
--
-- DEPENDENCIA: requiere que `seguimientoAspirantes` ya exista.
-- Ejecutar completo, de una sola vez, con backup previo.
-- =====================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `whatsapp_mensajes_historial` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seguimientoAspiranteId` BIGINT UNSIGNED NOT NULL,
  `company_id` VARCHAR(255) NULL DEFAULT NULL,
  `phone_number_id` VARCHAR(255) NULL DEFAULT NULL,
  `waMessageId` VARCHAR(255) NULL DEFAULT NULL,
  `conversationId` VARCHAR(255) NULL DEFAULT NULL,
  `template` VARCHAR(255) NULL DEFAULT NULL,
  `estado` VARCHAR(255) NOT NULL DEFAULT 'sent',
  `fecha_envio` DATETIME NOT NULL,
  `fecha_entregado` DATETIME NULL DEFAULT NULL,
  `fecha_leido` DATETIME NULL DEFAULT NULL,
  `fecha_error` DATETIME NULL DEFAULT NULL,
  `errorDetalle` TEXT NULL,
  `esMigrado` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_mensajes_historial_wamessageid_unique` (`waMessageId`),
  KEY `whatsapp_mensajes_historial_seguimientoaspiranteid_foreign` (`seguimientoAspiranteId`),
  KEY `whatsapp_mensajes_historial_fecha_envio_index` (`fecha_envio`),
  KEY `whatsapp_mensajes_historial_estado_index` (`estado`),
  KEY `whatsapp_mensajes_historial_template_index` (`template`),
  CONSTRAINT `whatsapp_mensajes_historial_seguimientoaspiranteid_foreign`
    FOREIGN KEY (`seguimientoAspiranteId`) REFERENCES `seguimientoAspirantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Migración de datos: copia el ÚLTIMO estado conocido de cada aspirante
-- (seguimientoAspirantes.estadoEnvio no nulo) hacia el historial, marcado
-- esMigrado=1 porque representa solo ese último estado, no el histórico
-- real de reintentos. Idempotente: el INSERT ... SELECT solo trae filas
-- de seguimientoAspirantes cuyo id NO tenga ya un registro esMigrado=1.
-- ---------------------------------------------------------------------
INSERT INTO `whatsapp_mensajes_historial`
  (`seguimientoAspiranteId`, `waMessageId`, `template`, `estado`,
   `fecha_envio`, `fecha_entregado`, `fecha_leido`, `fecha_error`,
   `errorDetalle`, `esMigrado`, `created_at`, `updated_at`)
SELECT
  sa.id,
  sa.waMessageId,
  sa.ultimaPlantilla,
  sa.estadoEnvio,
  COALESCE(sa.ultimo_envio, sa.created_at, NOW()) AS fecha_base,
  IF(sa.estadoEnvio IN ('delivered','read'), COALESCE(sa.ultimo_envio, sa.created_at, NOW()), NULL),
  IF(sa.estadoEnvio = 'read', COALESCE(sa.ultimo_envio, sa.created_at, NOW()), NULL),
  IF(sa.estadoEnvio = 'failed', COALESCE(sa.ultimo_envio, sa.created_at, NOW()), NULL),
  sa.errorEnvio,
  1,
  NOW(),
  NOW()
FROM `seguimientoAspirantes` sa
WHERE sa.estadoEnvio IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `whatsapp_mensajes_historial` h
    WHERE h.seguimientoAspiranteId = sa.id AND h.esMigrado = 1
  );

COMMIT;

-- =====================================================================
-- Nota: a partir de aquí, todo envío/estado NUEVO se inserta/actualiza
-- como evento real (esMigrado=0). Los registros esMigrado=1 son solo el
-- snapshot histórico previo al refactor, no un historial completo de
-- reintentos de esos aspirantes.
-- =====================================================================

-- Verificación:
-- SHOW CREATE TABLE whatsapp_mensajes_historial;
-- SELECT esMigrado, COUNT(*) FROM whatsapp_mensajes_historial GROUP BY esMigrado;
