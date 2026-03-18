CREATE DEFINER=`root`@`localhost` PROCEDURE `vt_school`.`grupofamiliar`(
IN v_idPersona INT,
IN v_nomTotalEstudiante VARCHAR(150),
IN nomGrupo VARCHAR(100),
IN idComp INT
)
BEGIN
	DECLARE done INT DEFAULT 0;
	DECLARE v_idAcudiente INT;  -- Para el segundo cursor (acudientes)
	-- Declarar el cursor de acudientes
    DECLARE acudienteCursor CURSOR FOR
     SELECT nf.idAcudiente
       FROM nucleoFamiliar nf
      WHERE nf.idEstudiante = v_idPersona
        AND nf.tutorLegal = 1;
         
    -- Declarar el handler para terminar el cursor
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    -- Abrir el cursor de acudientes
        OPEN acudienteCursor;
        
        acudiente_loop: LOOP
            -- Obtener los valores del cursor de acudientes
            FETCH acudienteCursor INTO v_idAcudiente;
            
            -- Verificar si ya no hay más registros
            IF done THEN
                LEAVE acudiente_loop;
            END IF;
           
            
            CALL VerificarOInsertarGrupo(nomGrupo, idComp, v_idAcudiente);
			
        END LOOP;
        
        -- Cerrar el cursor de acudientes
        CLOSE acudienteCursor;
END