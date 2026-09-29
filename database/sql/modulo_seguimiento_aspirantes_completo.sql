-- =====================================================================
-- MÓDULO SEGUIMIENTO DE ASPIRANTES — SQL COMPLETO (todo el módulo)
-- Fase 1 (WhatsApp/Excel/TelecomConfig) + Fase 2 (Solicitudes de Inscripción)
--
-- Solo SQL puro. No usa `php artisan migrate`.
--
-- DEPENDENCIA EXTERNA: la tabla `formularios` (módulo de Formularios,
-- ya desplegado por separado) y `usuario` (tabla base de usuarios) deben
-- existir ANTES de correr este script — son referenciadas por FK.
--
-- Ejecutar completo, de una sola vez, con backup previo.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) seguimientoAspirantes — tabla principal del módulo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `seguimientoAspirantes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenPublico` CHAR(36) NULL DEFAULT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `apellido` VARCHAR(255) NOT NULL,
  `celular` VARCHAR(255) NOT NULL,
  `correo` VARCHAR(255) NULL DEFAULT NULL,
  `centro_formacion` VARCHAR(255) NOT NULL,
  `programa` VARCHAR(255) NOT NULL,
  `ficha` VARCHAR(255) NOT NULL,
  `fecha_registro_excel` DATE NULL DEFAULT NULL,
  `estado` VARCHAR(255) NOT NULL DEFAULT 'Pendiente',
  `estadoDocumental` VARCHAR(255) NULL DEFAULT NULL,
  `respuesta` VARCHAR(255) NULL DEFAULT NULL,
  `fechaRespuesta` DATETIME NULL DEFAULT NULL,
  `fechaFormularioEnviado` DATETIME NULL DEFAULT NULL,
  `waMessageId` VARCHAR(255) NULL DEFAULT NULL,
  `estadoEnvio` VARCHAR(255) NULL DEFAULT NULL,
  `errorEnvio` VARCHAR(255) NULL DEFAULT NULL,
  `ultimo_envio` DATETIME NULL DEFAULT NULL,
  `cantidad_envios` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seguimientoaspirantes_tokenpublico_unique` (`tokenPublico`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) telecomConfigs — configuración WhatsApp Cloud API (Meta), autónoma
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `telecomConfigs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(255) NOT NULL DEFAULT 'meta',
  `whatsappEnabled` TINYINT(1) NOT NULL DEFAULT 1,
  `nombre` VARCHAR(255) NULL DEFAULT NULL,
  `accessToken` TEXT NOT NULL,
  `phoneNumberId` VARCHAR(255) NOT NULL,
  `businessAccountId` VARCHAR(255) NULL DEFAULT NULL,
  `appId` VARCHAR(255) NULL DEFAULT NULL,
  `verifyToken` VARCHAR(255) NOT NULL,
  `appSecret` VARCHAR(255) NULL DEFAULT NULL,
  `webhookUrl` VARCHAR(255) NULL DEFAULT NULL,
  `idFormularioInscripcion` BIGINT UNSIGNED NULL DEFAULT NULL,
  `graphVersion` VARCHAR(255) NOT NULL DEFAULT 'v23.0',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `telecomconfigs_idformularioinscripcion_foreign` (`idFormularioInscripcion`),
  CONSTRAINT `telecomconfigs_idformularioinscripcion_foreign`
    FOREIGN KEY (`idFormularioInscripcion`) REFERENCES `formularios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) whatsappplantillas — plantillas de mensaje registradas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsappplantillas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(255) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_plantillas_nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantilla oficial que usa el backend (SeguimientoAspiranteController::PLANTILLA_OFICIAL).
-- Debe existir aprobada en Meta Business con este mismo nombre exacto.
INSERT IGNORE INTO `whatsappplantillas` (`nombre`, `mensaje`, `created_at`, `updated_at`) VALUES
  ('seguimiento_interes_programa_sena_v2',
   'Estimado aspirante, queremos conocer su interés en continuar con el programa de formación del SENA. Por favor responda Sí o No.',
   NOW(), NOW());

-- ---------------------------------------------------------------------
-- 4) seguimiento_revision_historial — historial append-only (Fase 2)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `seguimiento_revision_historial` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idAspirante` BIGINT UNSIGNED NOT NULL,
  `accion` VARCHAR(255) NOT NULL,
  `motivo` TEXT NULL,
  `idUsuarioRevisor` INT UNSIGNED NULL DEFAULT NULL,
  `fecha` DATETIME NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `seguimiento_revision_historial_idaspirante_foreign` (`idAspirante`),
  KEY `seguimiento_revision_historial_idusuariorevisor_foreign` (`idUsuarioRevisor`),
  CONSTRAINT `seguimiento_revision_historial_idaspirante_foreign`
    FOREIGN KEY (`idAspirante`) REFERENCES `seguimientoAspirantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seguimiento_revision_historial_idusuariorevisor_foreign`
    FOREIGN KEY (`idUsuarioRevisor`) REFERENCES `usuario` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) Permiso del módulo (Spatie Permission)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `permissions` (`name`, `guard_name`, `description`, `created_at`, `updated_at`) VALUES
  ('GESTION_SEGUIMIENTO_ASPIRANTES', 'api', 'Módulo de seguimiento de aspirantes', NOW(), NOW());

-- Asignar el permiso a un rol administrador. Ajusta el nombre del rol
-- según tu tabla `roles` real (ver ejemplo comentado abajo) o hazlo
-- desde el módulo de Gestión de Usuarios/Roles en el frontend.
--
-- INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`)
-- SELECT p.id, r.id
-- FROM `permissions` p, `roles` r
-- WHERE p.name = 'GESTION_SEGUIMIENTO_ASPIRANTES' AND r.name = 'ADMINISTRADOR VT';

COMMIT;

-- =====================================================================
-- PASO MANUAL OBLIGATORIO (no es SQL, es configuración de la app):
-- En el frontend, ir a Configuración WhatsApp y:
--   1. Cargar accessToken / phoneNumberId / verifyToken reales de Meta.
--   2. Seleccionar el "Formulario de inscripción de aspirantes"
--      (columna telecomConfigs.idFormularioInscripcion) — sin esto el
--      link que reciben los aspirantes por WhatsApp da 404.
-- Y asignar el permiso GESTION_SEGUIMIENTO_ASPIRANTES al rol correspondiente
-- si no se hizo por SQL arriba.
-- =====================================================================

-- =====================================================================
-- Verificación post-despliegue
-- =====================================================================
-- SHOW TABLES LIKE '%seguimiento%';
-- SHOW TABLES LIKE '%telecomConfigs%';
-- SHOW TABLES LIKE '%whatsappplantillas%';
-- SELECT * FROM permissions WHERE name = 'GESTION_SEGUIMIENTO_ASPIRANTES';
