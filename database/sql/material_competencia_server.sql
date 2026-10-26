-- =====================================================================
-- MATERIAL POR COMPETENCIA — SQL DE SERVIDOR
-- Equivalente a:
--   database/migrations/2026_10_22_120000_create_material_competencia_table.php
--
-- Escenario Git (origin/CarlosPinzaSchool):
--   materialRap NUNCA se publicó. El servidor no debe crearla.
--   Este script crea directamente materialCompetencia.
--
-- NO ejecutar sobre una BD que ya tenga materialCompetencia con datos
-- (entorno local ya transformado).
--
-- Dependencias: materia, persona, actividades, materialApoyoActividad.
-- Ejecutar con backup previo.
-- =====================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `materialCompetencia` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idCompetencia` bigint unsigned NOT NULL,
  `idPersona` int unsigned DEFAULT NULL,
  `idActividad` bigint unsigned DEFAULT NULL,
  `idMaterialApoyoActividad` bigint unsigned DEFAULT NULL,
  `origen` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `claveOrigen` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `tipoMaterial` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urlDocumento` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urlAdicional` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `materialcompetencia_comp_clave_unique` (`idCompetencia`,`claveOrigen`),
  KEY `materialcompetencia_comp_activo_idx` (`idCompetencia`,`activo`),
  KEY `materialcompetencia_origen_idx` (`origen`),
  KEY `materialcompetencia_idpersona_foreign` (`idPersona`),
  KEY `materialcompetencia_idactividad_foreign` (`idActividad`),
  KEY `materialcompetencia_idmaterialapoyoactividad_foreign` (`idMaterialApoyoActividad`),
  CONSTRAINT `materialcompetencia_idcompetencia_foreign`
    FOREIGN KEY (`idCompetencia`) REFERENCES `materia` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `materialcompetencia_idpersona_foreign`
    FOREIGN KEY (`idPersona`) REFERENCES `persona` (`id`) ON DELETE SET NULL,
  CONSTRAINT `materialcompetencia_idactividad_foreign`
    FOREIGN KEY (`idActividad`) REFERENCES `actividades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `materialcompetencia_idmaterialapoyoactividad_foreign`
    FOREIGN KEY (`idMaterialApoyoActividad`) REFERENCES `materialApoyoActividad` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrar la migración Laravel equivalente para que `php artisan migrate`
-- no intente crearla de nuevo si también se corre migrate en el mismo entorno.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_22_120000_create_material_competencia_table',
       (SELECT next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM `migrations`) t)
WHERE NOT EXISTS (
  SELECT 1
  FROM `migrations`
  WHERE `migration` = '2026_10_22_120000_create_material_competencia_table'
);

COMMIT;

-- Verificación post-despliegue:
-- SHOW CREATE TABLE materialCompetencia;
-- SHOW TABLES LIKE 'materialRap';           -- debe quedar vacío
-- SELECT * FROM migrations WHERE migration LIKE '%material_competencia%';
