CREATE DEFINER=`root`@`localhost` PROCEDURE `vt_school`.`cargarTrabajadores`(
    IN companyId INT,
    IN val_centroId INT,
    IN val_rol VARCHAR(100),
    IN encryptedPassword VARCHAR(255)
)
BEGIN
    DECLARE done INT DEFAULT 0;

    -- Variables texto (forzadas a utf8mb4 para evitar mezclas)
    DECLARE nom1 VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE nom2 VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE ap1  VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE ap2  VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    DECLARE iden VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE crro VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE cel  VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    DECLARE tContra VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE val VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- <-- se declara para el FETCH
    DECLARE tiIden  VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE nomRol  VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE areaCon VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE nivelEdu VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    -- Fechas
    DECLARE feNac DATE;
    DECLARE feIni DATE;
    DECLARE feFin DATE;

    -- Auxiliares
    DECLARE RESULT INT DEFAULT 0;
    DECLARE idTipoIden INT(10) UNSIGNED;
    DECLARE id_personagenerado BIGINT(20) UNSIGNED;
    DECLARE id_contratogenerado BIGINT(20) UNSIGNED;

    DECLARE userId INT(10) UNSIGNED;
    DECLARE roleId INT;
    DECLARE activationId INT;

    DECLARE tipoConId BIGINT(20) UNSIGNED;
    DECLARE areaConoId BIGINT(20) UNSIGNED;

    DECLARE idNivelEdu INT(10) UNSIGNED;

    -- Cursor
    DECLARE c_trabajadores CURSOR FOR
      SELECT nombre1, nombre2, apellido1, apellido2,
             tipo_identificacion, identificacion, correo, celular, fecha_nacimiento,
             tipo_contratacion, valor, fecha_inicial, fecha_final, rol,
             area_conocimientos, nivel_educativo
      FROM trabajadores;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN c_trabajadores;

    read_loop: LOOP
        FETCH c_trabajadores INTO
            nom1, nom2, ap1, ap2,
            tiIden, iden, crro, cel,
            feNac, tContra, val, feIni, feFin,
            nomRol, areaCon, nivelEdu;

        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        -- 1) Verificar si ya existe la persona (comparación segura)
        SELECT EXISTS(
            SELECT 1
            FROM persona
            WHERE CONVERT(identificacion USING utf8mb4) COLLATE utf8mb4_unicode_ci
                = CONVERT(iden USING utf8mb4) COLLATE utf8mb4_unicode_ci
        ) INTO RESULT;

        IF RESULT = 0 THEN

            -- 2) Tipo Identificación
            SET idTipoIden = (
                SELECT id
                FROM tipoIdentificacion
                WHERE CONVERT(codigo USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(tiIden USING utf8mb4) COLLATE utf8mb4_unicode_ci
                LIMIT 1
            );

            -- 3) Tipo Contrato (crear si no existe)
            IF NOT EXISTS (
                SELECT 1
                FROM tipoContrato
                WHERE CONVERT(nombreTipoContrato USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(tContra USING utf8mb4) COLLATE utf8mb4_unicode_ci
            ) THEN
                INSERT INTO tipoContrato (nombreTipoContrato, descripcion)
                VALUES (tContra, 'Nuevo tipo de contrato');

                SET tipoConId = LAST_INSERT_ID();
            ELSE
                SET tipoConId = (
                    SELECT id
                    FROM tipoContrato
                    WHERE CONVERT(nombreTipoContrato USING utf8mb4) COLLATE utf8mb4_unicode_ci
                        = CONVERT(tContra USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    LIMIT 1
                );
            END IF;

            -- 4) Roles (crear si no existe)
            IF NOT EXISTS (
                SELECT 1
                FROM roles
                WHERE CONVERT(name USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(val_rol USING utf8mb4) COLLATE utf8mb4_unicode_ci
            ) THEN
                INSERT INTO roles (name, guard_name)
                VALUES (val_rol, 'web');
            END IF;

            -- 5) Nivel Educativo (crear si no existe)
            IF NOT EXISTS (
                SELECT 1
                FROM nivelEducativo
                WHERE CONVERT(nombreNivel USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(nivelEdu USING utf8mb4) COLLATE utf8mb4_unicode_ci
            ) THEN
                INSERT INTO nivelEducativo (nombreNivel, activo, created_at, updated_at)
                VALUES (nivelEdu, 1, NOW(), NOW());

                SET idNivelEdu = LAST_INSERT_ID();
            ELSE
                SET idNivelEdu = (
                    SELECT id
                    FROM nivelEducativo
                    WHERE CONVERT(nombreNivel USING utf8mb4) COLLATE utf8mb4_unicode_ci
                        = CONVERT(nivelEdu USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    LIMIT 1
                );
            END IF;
/*
            -- 6) Área Conocimiento (crear si no existe)
            IF NOT EXISTS (
                SELECT 1
                FROM area_conocimiento
                WHERE CONVERT(nombreAreaConocimiento USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(areaCon USING utf8mb4) COLLATE utf8mb4_unicode_ci
            ) THEN
                INSERT INTO area_conocimiento (nombreAreaConocimiento, idNivelEducativo, created_at, updated_at)
                VALUES (areaCon, idNivelEdu, NOW(), NOW());

                SET areaConoId = LAST_INSERT_ID();
            ELSE
                SET areaConoId = (
                    SELECT id
                    FROM area_conocimiento
                    WHERE CONVERT(nombreAreaConocimiento USING utf8mb4) COLLATE utf8mb4_unicode_ci
                        = CONVERT(areaCon USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    LIMIT 1
                );
            END IF;
*/
            -- 7) Insertar persona
            INSERT INTO persona (
                identificacion, nombre1, nombre2, apellido1, apellido2,
                fechaNac, direccion, email, telefonoFijo, celular,
                perfil, sexo, rh, rutaFoto,
                idTipoIdentificacion, idCiudad, idCiudadNac, idCiudadUbicacion
            ) VALUES (
                iden, nom1, nom2, ap1, ap2,
                feNac, '', crro, '', cel,
                '', 'M', 'O+', '',
                idTipoIden, 350, 350, 350
            );

            SET id_personagenerado = LAST_INSERT_ID();

            -- 8) Insertar usuario
            INSERT INTO usuario (email, contrasena, idPersona, idCentroFormacion)
            VALUES (crro, encryptedPassword, id_personagenerado, val_centroId);

            SET userId = LAST_INSERT_ID();

            -- 9) Activación empresa
            INSERT INTO activation_company_users (user_id, state_id, company_id, fechaInicio, fechaFin)
            VALUES (userId, 18, companyId, feIni, feFin);

            SET activationId = LAST_INSERT_ID();

            -- 10) Asignar rol
            SET roleId = (
                SELECT id
                FROM roles
                WHERE CONVERT(name USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(val_rol USING utf8mb4) COLLATE utf8mb4_unicode_ci
                LIMIT 1
            );

            INSERT INTO model_has_roles (role_id, model_type, model_id)
            VALUES (roleId, 'App\\Models\\ActivationCompanyUser', activationId);

            -- 11) Insertar contrato
            INSERT INTO contrato (
                idpersona,
                idempresa,
                idtipoContrato,
                fechaContratacion,
                idCompany,
                fechaFinalContrato,
                valorTotalContrato,
                sueldo,
                numeroContrato,
                objetoContrato,
                observacion,
                perfilProfesional,
                otrosi,
                created_at,
                updated_at,
                idEstado,
                formaDePago,
                idCentroCosto,
                idNivelEducativo,
                horasmes,
                idCentroFormacion,
                idSalario
            ) VALUES (
                id_personagenerado,
                companyId,
                tipoConId,
                CURDATE(),
                companyId,
                feFin,
                val,
                val,
                CONCAT('CT-', id_personagenerado),
                'N/A',
                'N/A',
                'N/A',
                'N',
                NOW(),
                NOW(),
                1,
                'NORMAL',
                NULL,
                idNivelEdu,
                NULL,
                val_centroId,
                1
            );

            SET id_contratogenerado = LAST_INSERT_ID();
/*
            -- 12) Asignar Área de Conocimiento al contrato
            INSERT INTO asignacion_contrato_area_conocimiento (idContrato, idAreaConocimiento, created_at, updated_at)
            VALUES (id_contratogenerado, areaConoId, NOW(), NULL);
*/
        END IF;
    END LOOP;

    CLOSE c_trabajadores;

    TRUNCATE TABLE trabajadores;
END