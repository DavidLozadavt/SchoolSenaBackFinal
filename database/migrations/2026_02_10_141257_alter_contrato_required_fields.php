<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        DB::statement("
            ALTER TABLE contrato
                MODIFY idpersona INT UNSIGNED NOT NULL,
                MODIFY idempresa INT UNSIGNED NOT NULL,
                MODIFY idtipoContrato INT UNSIGNED NOT NULL,
                MODIFY fechaContratacion DATE NOT NULL,
                MODIFY idCompany INT UNSIGNED NOT NULL,

                MODIFY numeroContrato VARCHAR(50) NULL,
                MODIFY objetoContrato TEXT NULL,
                MODIFY perfilProfesional TEXT NULL,
                MODIFY otrosi CHAR(1) NULL,
                MODIFY fechaFinalContrato DATE NULL,
                MODIFY valorTotalContrato DOUBLE NULL,
                MODIFY salario_id INT UNSIGNED NULL,
                MODIFY observacion TEXT NULL,
                MODIFY idEstado INT UNSIGNED NULL,
                MODIFY idCentroCosto BIGINT UNSIGNED NULL,
                MODIFY idPension INT UNSIGNED NULL,
                MODIFY idArl INT UNSIGNED NULL,
                MODIFY idSalud INT UNSIGNED NULL,
                MODIFY idCajaCompensacion INT UNSIGNED NULL,
                MODIFY idCesantias INT UNSIGNED NULL,
                MODIFY idBanco INT UNSIGNED NULL,
                MODIFY idTipoTerminoContrato INT UNSIGNED NULL,
                MODIFY idArea BIGINT UNSIGNED NULL,
                MODIFY idNivelEducativo INT UNSIGNED NULL,
                MODIFY porcentajeVentas DECIMAL(5,2) NULL,
                MODIFY idActividadRiesgo INT UNSIGNED NULL,
                MODIFY idTarifaRiesgo BIGINT UNSIGNED NULL,
                MODIFY idTipoCotizante INT UNSIGNED NULL,
                MODIFY idSubTipoCotizante INT UNSIGNED NULL,
                MODIFY idPensionMovilidad INT UNSIGNED NULL,
                MODIFY idSaludMovilidad INT UNSIGNED NULL,
                MODIFY idGrupoNomina INT UNSIGNED NULL,
                MODIFY horasmes INT UNSIGNED NULL,
                MODIFY idSalario INT UNSIGNED NULL,
                MODIFY sueldo DOUBLE(8,2) NULL,
                MODIFY siif DOUBLE NULL
        ");
        DB::statement("
        ALTER TABLE contrato
        ADD CONSTRAINT contrato_idCompany_fk
            FOREIGN KEY (idCompany) REFERENCES empresa(id)
    ");
    }
    

    public function down(): void
    {

    }
};
