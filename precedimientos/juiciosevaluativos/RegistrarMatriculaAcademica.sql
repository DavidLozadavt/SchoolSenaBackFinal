CREATE PROCEDURE vt_school.RegistrarMatriculaAcademica( IN p_idFicha BIGINT,
  IN p_idCompany INT,
  IN p_password VARCHAR(240),
  IN p_grado INT,
  IN p_idSede INT,
  IN p_idUser INT,
  IN p_idPrograma INT
)
BEGIN
	DECLARE done INT DEFAULT 0;
  DECLARE v_id BIGINT;
  DECLARE v_tipoIde VARCHAR(30);
  DECLARE v_identificacion VARCHAR(100);
  DECLARE v_nombre1 VARCHAR(50);
  DECLARE v_nombre2 VARCHAR(50);
  DECLARE v_apellido1 VARCHAR(50);
  DECLARE v_apellido2 VARCHAR(50);
  DECLARE v_estado VARCHAR(100);
  DECLARE v_competencia TEXT;
  DECLARE v_rap TEXT;
  DECLARE v_idMateria BIGINT; -- Para almacenar el id de la materia
  DECLARE v_idMatricula BIGINT; -- Para almacenar el id de la matrícula
  DECLARE v_idPersona INT; -- Para almacenar el id de la persona
  DECLARE v_idRap BIGINT; -- Para almacenar el id del RAP
  DECLARE v_nombreMateria VARCHAR(255); -- Nombre de la materia a validar
  DECLARE v_idResponsable INT; -- ID de la persona responsable de la evaluación
  DECLARE v_numeroResponsable VARCHAR(50); -- Número de identificación del responsable
  DECLARE v_responsableEvaluacion TEXT;
  DECLARE v_evaluacion VARCHAR(100);
  DECLARE v_fechaEvaluacion DATETIME;
  DECLARE v_codcompetencia VARCHAR(50);
  DECLARE v_codrap VARCHAR(50);
  DECLARE v_razonSocial VARCHAR(100);
  DECLARE v_idTipoIde, v_idGradoMateriaComp,v_idAddMatRap,v_idAddMatComp, v_idGradoMateriaRap ,v_idGradoPrograma,activationId,v_idRol,v_idHorarioMateriaComp, v_idHorarioMateriaRap INT;
  DECLARE v_idUsuario INT;
  DECLARE v_idMatriculaAcadem INT;
  DECLARE v_nomTotalEstudiante VARCHAR(200);
  DECLARE nomGrupo VARCHAR(150);
  DECLARE v_nombreSede VARCHAR (150);
  DECLARE v_nomSede VARCHAR (150);
  DECLARE v_nomPrograma VARCHAR (150);
  DECLARE v_nomEstudiante VARCHAR (150);
  DECLARE v_apeEstudiante VARCHAR (150);
  DECLARE v_identEstudiante,v_codProg,v_codFicha VARCHAR (150);
  
 
  
  -- Declarar el cursor para recorrer los registros de tmpRaps
  DECLARE tmpRapsCursor CURSOR FOR
  SELECT id, tipoIde, identificacion, 
      SUBSTRING_INDEX(nombre, ' ', 1) AS nombre1, 
      NULLIF(SUBSTRING_INDEX(nombre, ' ', -1), SUBSTRING_INDEX(nombre, ' ', 1)) AS nombre2,
      SUBSTRING_INDEX(apellidos, ' ', 1) AS apellido1,
      NULLIF(SUBSTRING_INDEX(apellidos, ' ', -1), SUBSTRING_INDEX(apellidos, ' ', 1)) AS apellido2,
      estado, competencia, rap, evaluacion, fechaEvaluacion, responsableEvaluacion
  FROM tmpRaps
  WHERE idUser=p_idUser;

  -- Declarar el handler para terminar el cursor cuando no haya más filas
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  -- Abrir el cursor
  OPEN tmpRapsCursor;

  -- Iniciar el bucle para recorrer el cursor
  read_loop: LOOP
      -- Obtener los valores del cursor en las variables
      FETCH tmpRapsCursor INTO v_id, v_tipoIde, v_identificacion, 
                          v_nombre1, v_nombre2, 
                          v_apellido1, v_apellido2, 
                          v_estado, v_competencia, v_rap, v_evaluacion, 
                          v_fechaEvaluacion, v_responsableEvaluacion;
                         
                                         

      
      -- Verificar si ya no hay más registros
      IF done THEN
          LEAVE read_loop;
      END IF;

      -- Verificar o insertar la persona
      SET v_idPersona = (SELECT id FROM persona WHERE identificacion = CONVERT(v_identificacion USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
      
      IF v_idPersona IS NULL THEN
          SET v_idTipoIde=(SELECT id FROM tipoIdentificacion WHERE codigo = CONVERT(v_tipoIde USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
          INSERT INTO persona (identificacion, idTipoIdentificacion, nombre1, nombre2, apellido1, apellido2, fechaNac,direccion,email, celular, perfil, sexo, created_at) 
          VALUES (v_identificacion, v_idTipoIde, v_nombre1, v_nombre2, v_apellido1, v_apellido2,'2000-01-01', 'SIN DIRECCION',v_identificacion,'111111111','NN','M', NOW());
          SET v_idPersona = LAST_INSERT_ID();
      END IF;
      -- Separar el número inicial de competencia y rap
      SET v_codcompetencia = TRIM(SUBSTRING_INDEX(v_competencia, ' ', 1)); -- Extrae la parte sin el número
      SET v_codrap = TRIM(SUBSTRING_INDEX(v_rap, ' ', 1)); -- Extrae la parte sin el número
      -- Verificar si el usuario existe
  SET v_idUsuario = (SELECT id FROM usuario WHERE idPersona = CONVERT(v_idPersona USING utf8mb4) COLLATE utf8mb4_unicode_ci and email=CONVERT(v_identificacion USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);
      
      IF v_idUsuario IS NULL THEN
          INSERT INTO usuario (email, contrasena, idPersona,created_at) 
          VALUES (v_identificacion, p_password, v_idPersona, NOW());
          SET v_idUsuario = LAST_INSERT_ID();
      END IF;
      -- Verificar o insertar la matrícula
   SET v_idMatricula = (SELECT id FROM matricula WHERE idPersona = v_idPersona AND idCompany = p_idCompany);
      
      IF v_idMatricula IS NULL THEN
          INSERT INTO matricula (fecha, idAcudiente, idFicha, idPersona, idCompany, idGrado, estado, created_at) 
          VALUES (NOW(), NULL, p_idFicha, v_idPersona, p_idCompany, p_grado, TRIM(v_estado), NOW());
          SET v_idMatricula = LAST_INSERT_ID();
      ELSE
         IF v_estado='EN FORMACION' THEN
	         UPDATE matricula SET estado = v_estado, idGrado=p_grado
	          WHERE idPersona = v_idPersona 
	            AND idCompany = p_idCompany;
	     ELSE
	        UPDATE matricula SET estado = v_estado
	          WHERE idPersona = v_idPersona 
	            AND idCompany = p_idCompany;
	     END IF;
      END IF;
        
      -- Validar o insertar la materia
      SET v_idMateria = (SELECT id FROM materia 
                      WHERE codigo = CONVERT(v_codcompetencia  USING utf8mb4) COLLATE utf8mb4_unicode_ci 
                          AND idEmpresa = p_idCompany
                      LIMIT 1);

                            
                            
      
           IF v_idMateria IS NULL THEN
          INSERT INTO materia (nombreMateria, descripcion, idEmpresa, idAreaConocimiento, codigo, created_at) 
          VALUES (v_codcompetencia, v_competencia, p_idCompany, 9, v_codcompetencia, NOW());
          SET v_idMateria = LAST_INSERT_ID();
         	
       END IF;
      
       SET v_idAddMatComp = (SELECT id FROM agregarMateriaPrograma  
                          WHERE idMateria = v_idMateria 
                            AND idPrograma = p_idPrograma);
  	   IF v_idAddMatComp IS NULL THEN
  	   	  INSERT INTO agregarMateriaPrograma
			(idMateria, idPrograma)
			VALUES(v_idMateria, p_idPrograma);
  	   
  	   END IF;
      -- Validar o insertar el RAP
      
SET v_idRap = (SELECT id FROM materia 
                              WHERE codigo = CONVERT(v_codrap  USING utf8mb4) COLLATE utf8mb4_unicode_ci
                              AND idMateriaPadre = v_idMateria 
                              AND idEmpresa = p_idCompany 
                              LIMIT 1);
                          
      IF v_idRap IS NULL THEN
          INSERT INTO materia (nombreMateria, descripcion, idEmpresa, idAreaConocimiento, codigo,idMateriaPadre, created_at) 
          VALUES (v_codrap, v_rap, p_idCompany, 9, v_codrap,v_idMateria, NOW());
          SET v_idRap = LAST_INSERT_ID();
          
      END IF;
      SET v_idAddMatRap = (SELECT id FROM agregarMateriaPrograma  
                          WHERE idMateria = v_idRap 
                            AND idPrograma = p_idPrograma);
                           
  	   IF v_idAddMatRap IS NULL THEN
  	   	  INSERT INTO agregarMateriaPrograma
			(idMateria, idPrograma)
			VALUES(v_idRap, p_idPrograma);
  	   
  	   END IF;

      -- Validación de Evaluación
      IF v_evaluacion = 'APROBADO' THEN
      
          -- Extraer el número de identificación del responsable
          SET v_numeroResponsable = TRIM(
              SUBSTRING_INDEX(SUBSTRING_INDEX(CONVERT(v_responsableEvaluacion USING utf8mb4) COLLATE utf8mb4_unicode_ci, ' ', 2), ' ', -1)
          );
          
          -- Verificar si el responsable existe en la tabla persona
          SET v_idResponsable = (SELECT id FROM persona WHERE identificacion = CONVERT(v_numeroResponsable USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1);

          -- Si no existe, guardar el dato completo en el campo observacion de matriculaacademica
          IF v_idResponsable IS NULL THEN
              SET v_numeroResponsable = CONVERT(v_responsableEvaluacion USING utf8mb4) COLLATE utf8mb4_unicode_ci;
          END IF;
      ELSE
          SET v_numeroResponsable = NULL;
          
      END IF;
     
  
     
           SET v_idGradoPrograma = (SELECT gp.id FROM gradoPrograma gp WHERE gp.idGrado = p_grado AND gp.idPrograma = p_idPrograma LIMIT 1);
						
				    IF v_idGradoPrograma IS NULL THEN
						     INSERT INTO gradoPrograma
						     ( idPrograma, idGrado, cupos, created_at)
						     VALUES( p_idPrograma, p_grado, 30, NOW());
						     SET v_idGradoPrograma = LAST_INSERT_ID();
					END IF;
		
		-- Agregando la Matricula academica solo a los RAPS			
            SET v_idMatriculaAcadem = (SELECT id FROM matriculaAcademica 
										 WHERE idFicha = p_idFicha
										   AND idMatricula = v_idMatricula
										   and idMateria = v_idRap);
                                   
                             
            IF v_idMatriculaAcadem IS NULL THEN
                -- Insertar en la tabla matriculaacademica con observacion
                INSERT INTO matriculaAcademica (
                    idFicha, 
                    idMatricula, estado, idEvaluador, observacion,idMateria, created_at
                ) VALUES (
                    p_idFicha,
                    v_idMatricula, v_evaluacion, v_idResponsable,concat('FUE EVALUADO POR: ',v_responsableEvaluacion),  v_idRap, NOW()
                );
            ELSE
                UPDATE matriculaAcademica SET idEvaluador = v_idResponsable, observacion=v_responsableEvaluacion,estado=v_evaluacion 
                                    WHERE idFicha = p_idFicha
									  AND idMatricula = v_idMatricula
									  AND idMateria = v_idRap; 
                                    END IF;
 -- Verificar si ya existe una activación para el usuario en la compañía
SET activationId = (
  SELECT id 
  FROM activation_company_users 
  WHERE user_id = v_idUsuario 
    AND company_id = p_idCompany 
  LIMIT 1
);

-- Si no existe activación, crear una nueva
IF activationId IS NULL THEN
  INSERT INTO activation_company_users (
      user_id,
      state_id,
      company_id,
      fechaInicio,
      fechaFin
  )
  VALUES (
      v_idUsuario,
      1,
      p_idCompany,
      NOW(),
      '2100-12-12'
  );

  -- Obtener el ID de la nueva activación
  SET activationId = LAST_INSERT_ID();
END IF;

-- Buscar el rol "APRENDIZ" en la compañía especificada
SET v_idRol = (
  SELECT id
  FROM roles
  WHERE name = 'APRENDIZ' AND company_id = p_idCompany
  LIMIT 1
);

-- Si no existe el rol "APRENDIZ", crearlo
IF v_idRol IS NULL THEN
  INSERT INTO roles (name, guard_name, company_id)
  VALUES ('APRENDIZ', 'web', p_idCompany);
  SET v_idRol = LAST_INSERT_ID();
END IF;

-- Verificar si ya existe una relación en model_has_roles
IF NOT EXISTS (
  SELECT 1 
  FROM model_has_roles
  WHERE role_id = v_idRol 
    AND model_type = 'App\\Models\\ActivationCompanyUser' 
    AND model_id = activationId
) THEN
  -- Si no existe, hacer la inserción
  INSERT INTO model_has_roles (role_id, model_type, model_id)
  VALUES (v_idRol, 'App\\Models\\ActivationCompanyUser', activationId);
END IF;

 
  
  -- Proceso de verificación/creación del grupo y participación

      -- 1. Grupo: 'GrupoGeneral_SENA NACIONAL'
      SET nomGrupo = 'GrupoGeneral_SENA NACIONAL';
      CALL VerificarOInsertarGrupo(nomGrupo,  p_idCompany, v_idPersona,p_idUser);
  
      -- 2. Grupo: 'GrupoGeneral_SENA REGIONAL'
      SELECT razonSocial INTO v_razonSocial FROM empresa WHERE id=p_idCompany;
      SET nomGrupo = CONCAT('GrupoGeneral_',v_razonSocial);
     
      -- CALL VerificarOInsertarGrupo(nomGrupo,  p_idCompany, v_idPersona,p_idUser);
  
      -- 3. Grupo: 'GrupoSede'
      SELECT nombre INTO v_nombreSede FROM sedes WHERE id=p_idSede AND idEmpresa=p_idCompany;
      SET nomGrupo = CONCAT('GrupoSede_', v_nombreSede);
      CALL VerificarOInsertarGrupo(nomGrupo, p_idCompany, v_idPersona,p_idUser);

      -- 4. Grupo: 'GrupoPrograma_{v_nomPrograma}'
     SELECT nombrePrograma,codigoPrograma INTO v_nomPrograma,v_codProg FROM programa WHERE id=p_idPrograma;
      SET nomGrupo = CONCAT('GrupoPrograma_', v_codProg);
      CALL VerificarOInsertarGrupo(nomGrupo, p_idCompany, v_idPersona,p_idUser);

      -- 5. Grupo: 'GrupoPrograma_{v_nomPrograma}_{p_idFicha}'
     SELECT codigo INTO v_codFicha FROM ficha WHERE id=p_idFicha;
      SET nomGrupo = CONCAT('GrupoPrograma_', v_codProg, '_', v_codFicha);
      CALL VerificarOInsertarGrupo(nomGrupo, p_idCompany, v_idPersona,p_idUser);
  
  
      -- Verificar/Insertar la participación del acudiente en el grupo
     
      SET v_nomTotalEstudiante=CONCAT(v_nomEstudiante,v_apeEstudiante);
      SET nomGrupo = CONCAT('GrupoFamiliar_', v_nombre1, '', v_apellido1,'',v_identificacion);
      CALL VerificarOInsertarGrupo(nomGrupo, p_idCompany, v_idPersona,p_idUser);
      CALL grupofamiliar(v_idPersona,v_nomTotalEstudiante,nomGrupo,p_idCompany);
      -- ELIMINAR REGISTRO AFECTADO
      DELETE FROM tmpRaps WHERE id=v_id AND idUser=p_idUser;
      
  
  END LOOP;
  -- Cerrar el cursor
  --falta este procedimiento almacenado, apenas lo quite funciono.
  -- CALL seguimientomateriasProcedure();
  CLOSE tmpRapsCursor;  
END;
