CREATE PROCEDURE vt_school.calcularHorasEjecutadas(
    IN p_idFicha INT,
    IN p_idMateria INT,
    OUT p_totalHoras INT
)
BEGIN
    DECLARE v_horaInicial TIME;
    DECLARE v_horaFinal TIME;
    DECLARE v_horasDiferencia INT;
    DECLARE done INT DEFAULT 0;
    DECLARE totalHoras INT DEFAULT 0;
    
    DECLARE cur CURSOR FOR
        SELECT hm.horaInicial, hm.horaFinal
        FROM sesionMateria sm 
        INNER JOIN HorarioMateria hm ON sm.idHorarioMateria = hm.id
        INNER JOIN gradoMateria gm ON gm.id = hm.idGradoMateria
        INNER JOIN materia m ON m.id = gm.idMateria
        WHERE gm.idMateria = p_idMateria 
        AND idFicha = p_idFicha;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_horaInicial, v_horaFinal;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Calcular la diferencia en horas entre la hora inicial y final
        SET v_horasDiferencia = TIMESTAMPDIFF(HOUR, v_horaInicial, v_horaFinal);
        SET totalHoras = totalHoras + v_horasDiferencia;
    END LOOP;
    
    CLOSE cur;
    
    -- Asignar el valor calculado a la variable de salida
    SET p_totalHoras = totalHoras;
END;