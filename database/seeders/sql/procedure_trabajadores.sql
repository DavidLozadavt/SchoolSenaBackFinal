CREATE DEFINER=`root`@`localhost` PROCEDURE `vt_school`.`cargarTrabajadores`(
	IN companyId INT,
    IN encryptedPassword VARCHAR(255))
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE nom1 VARCHAR(60);
    DECLARE nom2 VARCHAR(60);
    DECLARE ap1 VARCHAR(60);
    DECLARE ap2 VARCHAR(60);
    DECLARE iden VARCHAR(60);
    DECLARE crro VARCHAR(60);
    DECLARE cel VARCHAR(60);
    DECLARE val VARCHAR(60);
    DECLARE tContra VARCHAR(60);
    DECLARE sex VARCHAR(60);
    DECLARE rh VARCHAR(60);
    DECLARE tiIden VARCHAR(60);
    DECLARE feNac DATE;
    DECLARE feIni DATE;
    DECLARE feFin DATE;
    DECLARE nomRol VARCHAR(60);
    DECLARE areaCon VARCHAR(60);
    DECLARE RESULT INT DEFAULT 0;
    DECLARE idTipoIden INT(10) UNSIGNED;
    DECLARE id_personagenerado BIGINT(20) UNSIGNED;
    DECLARE id_contratogenerado BIGINT(20) UNSIGNED;
    DECLARE idRolExistente INT;
    DECLARE userId INT(10) UNSIGNED;
    DECLARE roleId INT;
    DECLARE activationId INT;
    DECLARE tipoConId  bigint(20) UNSIGNED;
    DECLARE areaConoId  bigint(20) UNSIGNED;
    DECLARE idInfraExistente INT;
	DECLARE nivelEdu VARCHAR(100);
	DECLARE idNivelEdu INT(10) UNSIGNED;



    DECLARE c_trabajadores CURSOR FOR
        SELECT
            nombre1,
            nombre2,
            apellido1,
            apellido2,
            tipo_identificacion,
            identificacion,
            correo,
            celular,
            fecha_nacimiento,
            tipo_contratacion,
            valor,
            fecha_inicial,
            fecha_final,
            rol,
            area_conocimientos,
            nivel_educativo
        FROM trabajadores;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN c_trabajadores;

    read_loop: LOOP
        FETCH c_trabajadores INTO
		    nom1, nom2, ap1, ap2, tiIden, iden, crro, cel,
		    feNac, tContra, val, feIni, feFin,
		    nomRol, areaCon, nivelEdu;


        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        SELECT EXISTS(
            SELECT *
            FROM persona
            WHERE identificacion COLLATE utf8mb4_unicode_ci = iden COLLATE utf8mb4_unicode_ci
        ) INTO RESULT;

        IF RESULT = 0 THEN
            SET idTipoIden = (
                SELECT id
                FROM tipoIdentificacion
                WHERE codigo COLLATE utf8mb4_unicode_ci = tiIden COLLATE utf8mb4_unicode_ci
                LIMIT 1
            );
				         IF NOT EXISTS (
				    SELECT *
				    FROM tipoContrato
				    WHERE nombreTipoContrato COLLATE utf8mb4_unicode_ci = tContra COLLATE utf8mb4_unicode_ci
				) THEN
				    INSERT INTO tipoContrato (nombreTipoContrato, descripcion)
				    VALUES (tContra, 'Nuevo tipo de contrato');
				    SET tipoConId = LAST_INSERT_ID();
				ELSE
				    SELECT id INTO tipoConId
				    FROM tipoContrato
				    WHERE nombreTipoContrato COLLATE utf8mb4_unicode_ci = tContra COLLATE utf8mb4_unicode_ci
				    LIMIT 1;
				END IF;


	            IF NOT EXISTS (
	                SELECT *
	                FROM roles
	                WHERE name COLLATE utf8mb4_unicode_ci = nomRol COLLATE utf8mb4_unicode_ci
	            ) THEN
	                INSERT INTO roles (name, guard_name)
	                VALUES (nomRol, 'web');
	            END IF;
	            
	            -- NIVEL EDUCATIVO: consultar o crear
				IF NOT EXISTS (
				    SELECT 1
				    FROM niveleducativo
				    WHERE nombreNivel COLLATE utf8mb4_unicode_ci =
				          nivelEdu COLLATE utf8mb4_unicode_ci
				) THEN
				
				    INSERT INTO niveleducativo (
				        nombreNivel,
				        activo,
				        created_at,
				        updated_at
				    )
				    VALUES (
				        nivelEdu,
				        1,
				        NOW(),
				        NOW()
				    );
				
				    SET idNivelEdu = LAST_INSERT_ID();
				ELSE
				    SELECT id
				    INTO idNivelEdu
				    FROM niveleducativo
				    WHERE nombreNivel COLLATE utf8mb4_unicode_ci =
				          nivelEdu COLLATE utf8mb4_unicode_ci
				    LIMIT 1;
				END IF;


	           IF NOT EXISTS (
				    SELECT *
				    FROM areaConocimientos
				    WHERE nombreAreaConocimiento COLLATE utf8mb4_unicode_ci = areaCon COLLATE utf8mb4_unicode_ci
				) THEN

				  INSERT INTO areaConocimientos (
				    nombreAreaConocimiento,
				    idNivelEducativo
				)
				VALUES (
				    areaCon,
				    idNivelEdu
				);
				  SET areaConoId  = LAST_INSERT_ID();
				END IF;


            SELECT id INTO idRolExistente
            FROM roles
            WHERE name COLLATE utf8mb4_unicode_ci = nomRol COLLATE utf8mb4_unicode_ci
            LIMIT 1;


       INSERT INTO persona (
                identificacion,
                nombre1,
                nombre2,
                apellido1,
                apellido2,
                fechaNac,
                direccion,
                email,
                telefonoFijo,
                celular,
                perfil,
                sexo,
                rh,
                rutaFoto,
                idTipoIdentificacion,
                idCiudadNac,
                idCiudadUbicacion
            )
            VALUES (
                iden,
                nom1,
                nom2,
                ap1,
                ap2,
                feNac,
                '',
                crro,
                '',
                cel,
                '',
                'M',
                'O+',
                '',
                idTipoIden,
                350,
                350
            );
            SET id_personagenerado = LAST_INSERT_ID();

            INSERT INTO usuario (
                email,
                contrasena,
                idPersona
            )
            VALUES (
                crro,
                encryptedPassword,
                id_personagenerado
            );

            SET userId = LAST_INSERT_ID();
          INSERT INTO activation_company_users (
			    user_id,
			    state_id,
			    company_id,
			    fechaInicio,
			    fechaFin
			)
			VALUES (
			    userId,
			    1,
			    companyId,
			    feIni,
			    feFin
			);

			SET activationId = LAST_INSERT_ID();

            SELECT id INTO roleId
            FROM roles
            WHERE name COLLATE utf8mb4_unicode_ci = nomRol COLLATE utf8mb4_unicode_ci
            LIMIT 1;

            INSERT INTO model_has_roles (role_id, model_type, model_id)
            VALUES (roleId, 'App\\Models\\ActivationCompanyUser', activationId);

           		INSERT INTO contrato (
				    idpersona,
				    idempresa,
				    idtipoContrato,
				    fechaContratacion,
				    fechaFinalContrato,
				    valorTotalContrato,
				    salario_id,
				    numeroContrato,
				    objetoContrato,
				    observacion,
				    perfilProfesional,
				    otrosi,
				    periodoPago,
				    created_at,
				    updated_at,
				    idEstado,
				    formaDePago,
				    tipoSalario
				)
				VALUES (
				    id_personagenerado,      -- persona creada
				    companyId,               -- empresa
				    tipoConId,               -- tipo de contrato
				    CURDATE(),               -- fecha contratación
				    CURDATE(),               -- fecha final (ajustable)
				    val,                     -- valor total contrato
				    21,                      -- ⚠️ NO puede ser NULL
				    CONCAT('CT-', id_personagenerado), -- número contrato
				    'Prestación de servicios profesionales',
				    'Contrato generado automáticamente',
				    'Desarrollador de software',
				    'N',                     -- otrosí (S / N)
				    30,                      -- periodo de pago (días)
				    NOW(),
				    NOW(),
				    1,                       -- estado ACTIVO (ejemplo)
				    'NORMAL',
				    'FIJO'
				);

			SET id_contratogenerado = LAST_INSERT_ID();

		INSERT INTO asingacionAreaConocimientoContratos
      ( idAreaConocimiento, idContrato, created_at, updated_at)
       VALUES( areaConoId, id_contratogenerado,CURDATE(), NULL);

        END IF;
    END LOOP;

    CLOSE c_trabajadores;
END