<?php

namespace App\Services;

use App\Models\Company;
use App\Models\EmpresaModuloEduexce;

class EduExceInstitucionService
{
    public function __construct(private EduExceApiService $eduexceApi)
    {
    }

    public function ensureInstitucionProvisionada(EmpresaModuloEduexce $modulo, Company $company): void
    {
        if ($modulo->id_institucion_eduexce) {
            return;
        }

        $result = $this->eduexceApi->provisionarInstitucion([
            'external_school_id' => $company->id,
            'nombre_institucion' => $company->razonSocial,
            'codigo_dane' => $company->nit ? "NIT-{$company->nit}" : "SCH-{$company->id}",
            'ciudad' => 'Sin especificar',
            'departamento' => 'Sin especificar',
            'direccion' => $company->direccion ?? null,
            'telefono' => $company->telefono ?? null,
            'correo' => $company->email,
            'jornada' => 'completa',
        ]);

        $modulo->id_institucion_eduexce = $result['id_institucion'] ?? null;
        $modulo->provisionado_at = now();
        $modulo->ultimo_error = null;
        $modulo->save();
    }
}
