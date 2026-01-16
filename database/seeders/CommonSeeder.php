<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CommonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\ContractType::create([
            'nombreTipoContrato' => 'OBRA O LABOR',
            'descripcion' => 'Es un contrato que se realiza para una labor específica y termina en el momento que la obra llegue a su fin. Este tipo de vinculación es característica de trabajos de construcción, de universidades y colegios. Este contrato es igual en términos de beneficios y descuentos a los contratos indefinidos y definidos, por ser un contrato laboral.'
        ]);
        \App\Models\ContractType::create([
            'nombreTipoContrato' => 'TERMINO FIJO',
            'descripcion' => 'Se caracteriza por tener una fecha de inicio y de terminación que no puede superar 3 años, es fundamental que sea por escrito. Puede ser prorrogado indefinidamente cuando su vigencia sea superior a un (1) año, o cuando siendo inferior, se haya prorrogado hasta por tres (3) veces.'
        ]);
        \App\Models\ContractType::create([
            'nombreTipoContrato' => 'TERMINO INDEFINIDO',
            'descripcion' => 'El contrato a término indefinido no tiene estipulada una fecha de culminación de la obligación contractual, cuya duración no haya sido expresamente estipulada o no resulte de la naturaleza de la obra o servicio que debe ejecutarse. Puede hacerse por escrito o de forma verbal.'
        ]);
        \App\Models\ContractType::create([
            'nombreTipoContrato' => 'APRENDIZAJE',
            'descripcion' => 'Es aquel mediante el cual una persona natural realiza formación teórica práctica en una entidad autorizada, a cambio de que la empresa proporcione los medios para adquirir formación profesional requerida en el oficio, actividad u ocupación, por cualquier tiempo determinado no superior a dos (2) años, y por esto recibe un apoyo de sostenimiento mensual, que sea como mínimo en la fase lectiva el equivalente al 50% de un (1) salario mínimo mensual vigente y durante la fase práctica será equivalente al setenta y cinco por ciento (75%) de un salario mínimo mensual legal vigente.'
        ]);
        \App\Models\ContractType::create([
            'nombreTipoContrato' => 'TEMPORAL, OCASIONAL O ACCIDENTAL',
            'descripcion' => 'El Código Sustantivo del Trabajo, define el trabajo ocasional, accidental o transitorio, como aquel no mayor de 30 días, y cuyas labores sean distintas de las actividades normales del empleador. Esta forma de contratación puede hacerse por escrito o verbalmente; recomendamos hacerlo por escrito, estableciendo la tarea específica del trabajador.'
        ]);

        \App\Models\Proceso::create([
            'nombreProceso' => 'LABORAL',
        ]);

        \App\Models\Proceso::create([
            'nombreProceso' => 'MATRICULA',
        ]);

        // Tipo de Documentos CONTRATACIONES
        \App\Models\TipoDocumento::create([
            'tituloDocumento' => 'SEGURO',
            'idProceso' => 1,
            'idEstado' => 1,
        ]);
        \App\Models\TipoDocumento::create([
            'tituloDocumento' => 'HOJA DE VIDA',
            'idProceso' => 1,
            'idEstado' => 1,
        ]);
        \App\Models\TipoDocumento::create([
            'tituloDocumento' => 'CEDULA',
            'idProceso' => 1,
            'idEstado' => 1,
        ]);
        \App\Models\TipoDocumento::create([
            'tituloDocumento' => 'CONTRATO',
            'idProceso' => 1,
            'idEstado' => 1,
        ]);
        \App\Models\TipoDocumento::create([
            'tituloDocumento' => 'SEGURO',
            'idProceso' => 2,
            'idEstado' => 1,
        ]);
    }
}
