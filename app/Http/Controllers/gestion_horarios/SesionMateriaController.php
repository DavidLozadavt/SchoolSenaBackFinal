<?php

namespace App\Http\Controllers\gestion_horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SesionMateria;

class SesionMateriaController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    //
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \App\Http\Requests\StoreSesionMateriaRequest  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {
    $data = $request->all();

    $sesionMateria = SesionMateria::create([
      'numeroSesion'     => $data['numeroSesion'],
      'idHorarioMateria' => $data['idHorarioMateria'],
      'fechaSesion'      => $data['fechaSesion'],
    ]);

    return response()->json($sesionMateria, 201);
  }

  /**
   * Display the specified resource.
   *
   * @param  \App\Models\SesionMateria  $sesionMateria
   * @return \Illuminate\Http\Response
   */
  public function show(int $id)
  {
    $sesionMateria = SesionMateria::findOrFail($id);
    return response()->json($sesionMateria, 200);
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \App\Http\Requests\UpdateSesionMateriaRequest  $request
   * @param  \App\Models\SesionMateria  $sesionMateria
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, int $id)
  {
    $data = $request->all();

    $sesionMateria = SesionMateria::findOrFail($id);

    $sesionMateria->update([
      'numeroSesion'     => $data['numeroSesion'],
      'idHorarioMateria' => $data['idHorarioMateria'],
      'fechaSesion'      => $data['fechaSesion'],
    ]);

    return response()->json($sesionMateria, 200);
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  \App\Models\SesionMateria  $sesionMateria
   * @return \Illuminate\Http\Response
   */
  public function destroy(int $id)
  {
    $sesionMateria = SesionMateria::findOrFail($id);
    $sesionMateria->delete();
    return response()->json(null, 204);
  }
}
