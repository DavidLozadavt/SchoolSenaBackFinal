CREATE DEFINER=`root`@`localhost` PROCEDURE `vt_school`.`VerificarOInsertarGrupo`(
   IN nomGrupo VARCHAR(150),  
    IN idComp INT, 
    IN idPersona INT,
    IN p_idUser INT
)
BEGIN
    DECLARE v_idGrupo INT;
    DECLARE v_idParticipacion INT;

    -- Verificar si el grupo ya existe, forzando el collation a uno consistente (por ejemplo, utf8mb4_unicode_ci)
    SELECT id INTO v_idGrupo
    FROM grupoGenerales
    WHERE nombreGrupo = CONVERT(nomGrupo USING utf8mb4) COLLATE utf8mb4_unicode_ci
      AND idCompany = idComp
    LIMIT 1;

    -- Si no existe, insertar el nuevo grupo
    IF v_idGrupo IS NULL THEN
        INSERT INTO grupoGenerales (nombreGrupo, imagen, idUser, idCompany)
        VALUES (CONVERT(nomGrupo USING utf8mb4) COLLATE utf8mb4_unicode_ci, 'ruta/de/la/imagen.jpg', p_idUser, idComp);
        SET v_idGrupo = LAST_INSERT_ID();
    END IF;

    -- Verificar si ya existe una participación
    SELECT pg.id INTO v_idParticipacion
    FROM participanteGrupoGenerales pg
    WHERE pg.idGrupo = v_idGrupo
      AND pg.idPersona = idPersona
    LIMIT 1;
	
    -- Si no existe, insertar la participación
    IF v_idParticipacion IS NULL THEN
        INSERT INTO participanteGrupoGenerales (idPersona, idGrupo, created_at, updated_at)
        VALUES (idPersona, v_idGrupo, NOW(), NOW());
       
    END IF;
END