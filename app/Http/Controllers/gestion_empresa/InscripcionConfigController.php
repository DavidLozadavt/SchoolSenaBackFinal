<?php

namespace App\Http\Controllers\gestion_empresa;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\NexiCompany;
use App\Models\Formulario;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class InscripcionConfigController extends Controller
{
    /**
     * Obtener la configuración actual de inscripción del colegio.
     */
    public function getConfig(Request $request)
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $company = Company::findOrFail($idCompany);

            return response()->json([
                'idFormularioInscripcion' => $company->idFormularioInscripcion,
                'inscripcionHabilitada' => (int) $company->inscripcionHabilitada,
                'fechaInicioInscripcion' => $company->fechaInicioInscripcion,
                'fechaFinInscripcion' => $company->fechaFinInscripcion,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al obtener la configuración: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Guardar/Actualizar la configuración de inscripción y sincronizar con NexiService.
     */
    public function saveConfig(Request $request)
    {
        $request->validate([
            'idFormularioInscripcion' => 'nullable|integer',
            'inscripcionHabilitada' => 'required|integer|in:0,1',
            'fechaInicioInscripcion' => 'nullable|date',
            'fechaFinInscripcion' => 'nullable|date|after_or_equal:fechaInicioInscripcion',
        ]);

        try {
            $idCompany = KeyUtil::idCompany();
            $company = Company::findOrFail($idCompany);

            $company->update([
                'idFormularioInscripcion' => $request->input('idFormularioInscripcion'),
                'inscripcionHabilitada' => $request->input('inscripcionHabilitada'),
                'fechaInicioInscripcion' => $request->input('fechaInicioInscripcion'),
                'fechaFinInscripcion' => $request->input('fechaFinInscripcion'),
            ]);

            // Sincronización con NexiService
            try {
                $nexiCompany = NexiCompany::where('nit', $company->nit)->first();
                if ($nexiCompany && $nexiCompany->idCategoriaEmpresa == 7) {
                    $nexiCompany->update([
                        'idFormularioInscripcion' => $company->idFormularioInscripcion,
                        'inscripcionHabilitada' => $company->inscripcionHabilitada,
                        'fechaInicioInscripcion' => $company->fechaInicioInscripcion,
                        'fechaFinInscripcion' => $company->fechaFinInscripcion,
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $se) {
                \Log::error('Error sincronizando configuración de inscripción a NexiService: ' . $se->getMessage());
            }

            return response()->json([
                'message' => 'Configuración de inscripción guardada y sincronizada con éxito.',
                'config' => [
                    'idFormularioInscripcion' => $company->idFormularioInscripcion,
                    'inscripcionHabilitada' => (int) $company->inscripcionHabilitada,
                    'fechaInicioInscripcion' => $company->fechaInicioInscripcion,
                    'fechaFinInscripcion' => $company->fechaFinInscripcion,
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al guardar la configuración: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Listar los formularios disponibles de la institución actual.
     */
    public function getFormularios(Request $request)
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $formularios = Formulario::where('idCompany', $idCompany)
                ->orWhere('idCompany', 1)
                ->select(['id', 'titulo', 'descripcion'])
                ->get();

            return response()->json($formularios);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al listar formularios: ' . $e->getMessage()], 500);
        }
    }
}
