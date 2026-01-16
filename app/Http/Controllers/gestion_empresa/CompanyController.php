<?php

namespace App\Http\Controllers\gestion_empresa;

use App\Http\Controllers\Controller;
use App\Models\ActivationCompanyUser;
use App\Models\Company;
use App\Models\Contract;
use App\Util\KeyUtil;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session as FacadesSession;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $companies = Company::all();
        return response()->json($companies);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\Response
     */
    public function show(Company $company)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\Response
     */
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\Response
     */


    public function update(Request $request)
    {
        try {
            $company = Company::findOrFail(KeyUtil::idCompany());

            if ($request->has('nit')) {
                $existingNit = Company::where('nit', $request->input('nit'))
                    ->where('id', '!=', $company->id)
                    ->first();
                if ($existingNit) {
                    return response()->json(['error' => 'El NIT ya está registrado para otra empresa.'], 422);
                }
            }

            $fields = [
                'razonSocial',
                'nit',
                'email',
                'representanteLegal',
                'idCategoriaEmpresa',
                'valorAdministracion',
                'valorAfiliacion',
                'digitoVerificacion',
                'valorIva',
                'telefono',
                'direccion',
                'devolucion',
                'garantia',
                'facturaElectronica',
                'diasMoraInventario',
                'diasMoraAdministracion',
                'diasVencimientoFacturacion',
                'diaCorteAdministracion',   
                'descuentoAsociado',
                'descuentoEmpleado',
                'stockMinimo',
            ];

            foreach ($fields as $field) {
                if ($request->has($field) && !in_array($request->input($field), ['undefined', 'null', null])) {
                    $company->$field = $request->input($field);
                }
            }

            if ($request->has('responsableIva')) {
                $company->responsableIva = filter_var($request->input('responsableIva'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }

            if ($request->has('retenciones')) {
                $company->retenciones = filter_var($request->input('retenciones'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }

            if ($request->has('facturacionElectronica')) {
                $company->facturaElectronica = filter_var($request->input('facturacionElectronica'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }

            if ($request->hasFile('rutaLogoFile')) {
                $company->rutaLogo = $this->storeLogoCompany($request);
            }


            if ($request->has('itemsEmpresa')) {
                $items = json_decode($request->input('itemsEmpresa'), true);

                if (is_array($items)) {
                    $company->productos = in_array('productos', $items) ? 1 : 0;
                    $company->servicios = in_array('servicios', $items) ? 1 : 0;
                    $company->catalogo = in_array('catalogo', $items) ? 1 : 0;
                }
            }

            $company->save();

            return response()->json(['message' => 'Empresa actualizada con éxito', 'company' => $company]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al actualizar la empresa: ' . $e->getMessage()], 500);
        }
    }

    private function storeLogoCompany(Request $request, $default = true)
    {
        $rutaLogo = null;

        if ($default) {
            $rutaLogo = Company::RUTA_LOGO_DEFAULT;
        }
        if ($request->hasFile('rutaLogoFile')) {
            $rutaLogo = '/storage/' . $request
                ->file('rutaLogoFile')
                ->store(Company::RUTA_LOGO, ['disk' => 'public']);
        }
        return $rutaLogo;
    }



    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\Response
     */
    public function destroy(Company $company)
    {
        //
    }

    public function getUsersCompany()
    {
        $idCompany = KeyUtil::idCompany();

        $contratos = Contract::with('persona', 'estado', 'salario.rol')
            ->where('idEstado', 1)
            ->where('idempresa', $idCompany)
            ->get();

        return response()->json($contratos);
    }
}
