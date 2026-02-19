CREATE PROCEDURE vt_school.seguimientomateriasProcedure()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE val_idmateria, val_idficha, val_horas, val_estadoMatricula, val_idMatricula, val_idCompetencia,val_horasEjecutadas,val_horasFaltantes INT;
    DECLARE val_estado VARCHAR(255); -- Asegura suficiente espacio para estado
    
    -- Declarar cursor correctamente
    DECLARE cur CURSOR FOR 
    SELECT 
        ma.idMateria, 
        ma.idFicha, 
        m.horas, 
        ma.estado,
        m2.estado,
        ma.idMatricula,
        m.idMateriaPadre
    FROM matriculaAcademica ma
    INNER JOIN (
        SELECT MAX(id) AS id
        FROM matriculaAcademica
        GROUP BY idMateria, idFicha
    ) latest ON ma.id = latest.id
    INNER JOIN materia m ON ma.idMateria = m.id
    INNER JOIN matricula m2 ON m2.id = ma.idMatricula
    WHERE m2.estado IN ('EN FORMACION');

    -- Manejador para el fin del cursor
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    -- Abrir el cursor
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO val_idmateria, val_idficha, val_horas, val_estado, val_estadoMatricula, val_idMatricula, val_idCompetencia;
        IF done THEN
            LEAVE read_loop;
        END IF;

       CALL calcularHorasEjecutadas(val_idficha, val_idmateria,val_horasEjecutadas);
       SET val_horasFaltantes=val_horas-val_horasEjecutadas;
        -- Verificar si el registro ya existe antes de insertarlo
	        IF NOT EXISTS (
	            SELECT 1 FROM seguimientoMateria
	            WHERE idMateria = val_idmateria 
	            AND idFicha = val_idficha
	        ) THEN
	            INSERT INTO seguimientoMateria
	                (idMateria, idFicha, tipoMateria, estado, horasTotales,horasEjecutadas,horasFaltantes,idMateriaPadre, created_at)
	            VALUES 
	                (val_idmateria, val_idficha, 'RAP', val_estado, val_horas,val_horasEjecutadas,val_horasFaltantes,val_idCompetencia, NOW());
	        ELSE
	            UPDATE seguimientoMateria 
	            SET estado=val_estado 
	            WHERE idMateria = val_idmateria 
	            AND idFicha = val_idficha; 
	        END IF;
    END LOOP;
    -- cargar competencias 
    CALL evaluarCompetencia();
    -- Cerrar el cursor
    CLOSE cur;
END;