-- =====================================================================
-- Módulo Seguimiento de Aspirantes — Fase 2 (Solicitudes de Inscripción)
-- SQL de producción, equivalente a las migraciones:
--   2026_07_21_210000_add_inscripcion_columns_to_seguimiento_aspirantes_table
--   2026_07_21_210100_create_seguimiento_revision_historial_table
--   2026_07_21_210200_add_id_formulario_inscripcion_to_telecom_configs_table
--
-- Requisitos previos en producción: las tablas `seguimientoAspirantes`,
-- `telecomConfigs`, `formularios`, `usuario` ya deben existir (módulo base
-- de Seguimiento de Aspirantes + Formularios ya en producción).
--
-- Ejecutar dentro de una transacción/backup previo. Todas las columnas son
-- nullable: no afecta filas existentes.
-- =====================================================================

START TRANSACTION;

-- 1) Columnas nuevas en seguimientoAspirantes ------------------------
ALTER TABLE `seguimientoAspirantes`
  ADD COLUMN `tokenPublico` CHAR(36) NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `estadoDocumental` VARCHAR(255) NULL DEFAULT NULL AFTER `estado`,
  ADD COLUMN `fechaFormularioEnviado` DATETIME NULL DEFAULT NULL AFTER `fechaRespuesta`;

ALTER TABLE `seguimientoAspirantes`
  ADD UNIQUE KEY `seguimientoaspirantes_tokenpublico_unique` (`tokenPublico`);

-- 2) Tabla de historial (append-only) ---------------------------------
CREATE TABLE `seguimiento_revision_historial` (
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

-- 3) Formulario de inscripción asignable desde TelecomConfig ---------
ALTER TABLE `telecomConfigs`
  ADD COLUMN `idFormularioInscripcion` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `webhookUrl`;

ALTER TABLE `telecomConfigs`
  ADD CONSTRAINT `telecomconfigs_idformularioinscripcion_foreign`
    FOREIGN KEY (`idFormularioInscripcion`) REFERENCES `formularios` (`id`) ON DELETE SET NULL;

-- 4) Registrar las migraciones en la tabla `migrations` ----------------
-- Evita que `php artisan migrate` intente volver a crearlas si el
-- proyecto Laravel también corre migrate en el mismo entorno.
-- Ajusta el valor de `batch` al siguiente número disponible en tu entorno:
--   SELECT MAX(batch) FROM migrations;
INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_07_21_210000_add_inscripcion_columns_to_seguimiento_aspirantes_table', (SELECT next_batch FROM (SELECT COALESCE(MAX(batch),0)+1 AS next_batch FROM migrations) t)),
  ('2026_07_21_210100_create_seguimiento_revision_historial_table', (SELECT next_batch FROM (SELECT COALESCE(MAX(batch),0)+1 AS next_batch FROM migrations) t)),
  ('2026_07_21_210200_add_id_formulario_inscripcion_to_telecom_configs_table', (SELECT next_batch FROM (SELECT COALESCE(MAX(batch),0)+1 AS next_batch FROM migrations) t));

COMMIT;

-- =====================================================================
-- Verificación post-despliegue
-- =====================================================================
-- SHOW COLUMNS FROM seguimientoAspirantes;
-- SHOW COLUMNS FROM telecomConfigs;
-- DESCRIBE seguimiento_revision_historial;
-- SELECT * FROM migrations WHERE migration LIKE '2026_07_21%';
