<?php

namespace App\Http\Controllers\gestion_rol;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Salario;
use App\Permission\PermissionConst;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Models\Role;

class RolController extends Controller
{
    public function __construct()
    {
        // $this->middleware('permission:' . PermissionConst::GESTION_ROLES);
    }

    /**
     * Display a listing of the resource.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request  $request)
    {


        $roles = Rol::with("company", 'salario');

        return response()->json($roles->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $rol = new Rol();
        $rol->guard_name = 'web';
        $rol->company_id = KeyUtil::idCompany();
        $rol->name = $request->input('name');;
        $rol->save();

        $salario = new Salario();
        $salario->fecha = now();
        $salario->valor = $request->input('valor');
        $salario->estado_id = 1;
        $salario->rol_id = $rol->id;
        $salario->save();

        return response()->json($rol->load(['company']), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        $rol = Rol::find($id);

        return response()->json($rol);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        $data = $request->all();
        $rol = Rol::findOrFail($id);
        $rol->fill($data);
        $rol->save();


        if (isset($data['salario']) && strtoupper($data['salario']) === 'SI') {
            if (isset($data['valor'])) {

                if ($rol->salario) {
                    $rol->salario->valor = $data['valor'];
                    $rol->salario->save();
                } else {
                    $rol->salario()->create([
                        'valor' => $data['valor'],
                        'fecha' => now()->toDateString()
                    ]);
                }
            }
        }

        return response()->json($rol->load('company', 'salario'));
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $rol = Rol::findOrFail($id);

        Salario::where('rol_id', $id)->delete();

        $rol->delete();

        return response()->json(null, 204);
    }
}
