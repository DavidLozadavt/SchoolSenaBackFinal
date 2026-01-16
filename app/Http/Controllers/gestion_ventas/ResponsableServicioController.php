<?php

namespace App\Http\Controllers\gestion_ventas;

use App\Http\Controllers\Controller;
use App\Models\ActivationCompanyUser;
use App\Models\Person;
use App\Models\ResponsableServicio;
use App\Models\User;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class ResponsableServicioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $idCompany = KeyUtil::idCompany();
 //  CARGAR LA RELACIÓN 'persona'
    $responsables = ResponsableServicio::with('persona')->get();
        $responsable = ResponsableServicio::with('persona.usuario')
            ->whereHas('persona.usuario.activationCompanyUsers', function ($query) use ($idCompany) {
                $query->where('company_id', $idCompany);
            })
            ->get();

        return response()->json($responsable);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $persona = new Person($data);
        $persona->rutaFoto = Person::RUTA_FOTO_DEFAULT;
        $persona->save();

        $usuario = new User($data);
        $usuario->contrasena = bcrypt($request->input('contrasena'));
        $usuario->idpersona = $persona->id;
        $usuario->save();


       $responsable = new ResponsableServicio([
    'porcentajeGanancia' => $data['porcentajeGanancia'] ?? 0,
    'descripcion' => $data['descripcion'] ?? null,
    'idPersona' => $persona->id,
]);
$responsable->save();



        $activacion = new ActivationCompanyUser();
        $activacion->user_id = $usuario->id;
        $activacion->state_id = 1;
        $activacion->company_id = KeyUtil::idCompany();
        $activacion->fechaInicio = date('Y-m-d');
        $activacion->fechaFin = date('Y-m-d', strtotime('+1 year'));
        $activacion->save();

        return response()->json($usuario, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $idResponsable)
    {

        $responsable = ResponsableServicio::find($idResponsable);
        if (!$responsable) {
            return response()->json(['error' => 'Responsable no encontrado'], 404);
        }

        $persona = Person::find($responsable->idPersona);
        if (!$persona) {
            return response()->json(['error' => 'Persona no encontrada'], 404);
        }

        $usuario = User::where('idpersona', $persona->id)->first();
        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }


        $persona->update($request->only([
            'nombre1',
            'nombre2',
            'apellido1',
            'apellido2',
            'identificacion',
            'fechaNac',
            'celular'
        ]));

        $usuario->email = $request->input('email', $usuario->email);
        if ($request->filled('contrasena')) {
            $usuario->contrasena = bcrypt($request->input('contrasena'));
        }
        $usuario->save();


        $responsable->update($request->only(['porcentajeGanancia', 'descripcion']));

        return response()->json(['message' => 'Actualización exitosa'], 200);
    }




    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $responsable = ResponsableServicio::findOrFail($id);

        if ($responsable->prestaciones()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el responsable porque tiene prestaciones de servicios asociadas.'
            ], 400);
        }

        $responsable->delete();

        return response()->json([], 204);
    }



    public function getPrestadoresCompany($idCompany)
{
     $responsable = ResponsableServicio::with([
        'persona.usuario.activationCompanyUsers.roles',
        'servicios' 
    ])
    ->whereHas('persona.usuario.activationCompanyUsers', function ($query) use ($idCompany) {
        $query->where('company_id', $idCompany);
    })
    ->get();

    return response()->json($responsable);
}




}
