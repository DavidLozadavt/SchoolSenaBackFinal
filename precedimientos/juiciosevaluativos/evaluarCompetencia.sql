CREATE PROCEDURE vt_school.evaluarCompetencia()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE contRap,sumHorasEjecutadas,sumHorasTotales,contAux INT DEFAULT 0;
    DECLARE val_idCompAux INT DEFAULT 0;
    DECLARE val_idmateria, val_idficha, val_horas, val_estadoMatricula, val_idMatricula, val_idcompetencia,val_horasEjecutadas,val_horasTotales,val_horaEjecutadas,val_rap INT;
    DECLARE val_estado VARCHAR(255); -- Asegura suficiente espacio para estado
    DECLARE estadoAux VARCHAR(255) DEFAULT 'APROBADO';
    -- Declarar cursor correctamente
    DECLARE cur CURSOR FOR 
       	SELECT sm.idMateriaPadre, idFicha, sm.estado, sm.horasTotales,sm.horasEjecutadas,sm.idMateria
		FROM seguimientoMateria sm 
		INNER JOIN materia m 
		ON (sm.idMateriaPadre=m.id);

    -- Manejador para el fin del cursor
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    -- Abrir el cursor
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO val_idcompetencia, val_idficha, val_estado,val_horasTotales,val_horaEjecutadas,val_rap;
        IF done THEN
            LEAVE read_loop;
        END IF;
       IF val_idCompAux=0 THEN
             SET val_idCompAux=val_idcompetencia;
             SET sumHorasEjecutadas=val_horasEjecutadas;
            -- SET sumHorasTotales=val_horasTotales; 
            
       END IF;
      
       IF val_idCompAux!=val_idcompetencia THEN
			 
            IF NOT EXISTS (
	            SELECT 1 FROM `seguimientoMateria`
	            WHERE `idMateria` = val_idCompAux 
	            AND `idFicha` = val_idficha
	        ) THEN
	            INSERT INTO `seguimientoMateria`
	                (`idMateria`, `idFicha`, `tipoMateria`, `estado`, `horasTotales`, `horasEjecutadas`,`created_at`)
	            VALUES 
	                (val_idCompAux, val_idficha, 'COMPETENCIA', estadoAux, sumHorasTotales,sumHorasEjecutadas ,NOW());
	        ELSE
	            UPDATE `seguimientoMateria` 
	            SET `estado`=estadoAux 
	            WHERE `idMateria` = val_idCompAux 
	            AND `idFicha` = val_idficha; 
	        END IF;
	          -- select sumHorasTotales;
	       	  SET estadoAux='APROBADO';
		      SET sumHorasEjecutadas=val_horaEjecutadas;
		      SET sumHorasTotales=val_horasTotales;
		      SET val_idCompAux=val_idcompetencia;
       	  
       	  
       ELSE

            
		
			IF val_estado='POR EVALUAR' THEN
               SET estadoAux = 'POR EVALUAR'; 
            END IF;
            SET sumHorasEjecutadas=sumHorasEjecutadas+val_horasEjecutadas;
            SET sumHorasTotales=sumHorasTotales+val_horasTotales;
		    -- Puedes agregar un SELECT para verificar los valores
			
		    
	    END IF;
       	
       
    END LOOP;
    IF NOT EXISTS (
	   SELECT 1 FROM `seguimientoMateria`
	   WHERE `idMateria` = val_idCompAux 
	   AND `idFicha` = val_idficha
	 ) THEN
	   INSERT INTO `seguimientoMateria`
	         (`idMateria`, `idFicha`, `tipoMateria`, `estado`, `horasTotales`, `horasEjecutadas`,`created_at`)
	   VALUES 
	         (val_idCompAux, val_idficha, 'COMPETENCIA', estadoAux, sumHorasTotales,sumHorasEjecutadas ,NOW());
	   ELSE
	       UPDATE `seguimientoMateria` 
	       SET `estado`=estadoAux 
	       WHERE `idMateria` = val_idCompAux 
	       AND `idFicha` = val_idficha; 
	       END IF;
    -- Cerrar el cursor
    CLOSE cur;
END;