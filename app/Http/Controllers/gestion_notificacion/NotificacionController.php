<?php

namespace App\Http\Controllers\gestion_notificacion;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\Status;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $fecha   =  $request->input('fecha');
        $hora    =  $request->input('hora');
        $asunto  =  $request->input('asunto');
        $estado  =  $request->input('estado');
        $remitente =  $request->input('remitente');

        $notificaciones = Notificacion::with(
            'estado',
            'personaReceptor',
            'personaRemitente',
            'tipoNotificacion',
            'empresa'
        );


        $notificaciones->where('idUsuarioReceptor', $request->user()->idpersona);

        if ($fecha) {
            $notificaciones->where('fecha', '=', $fecha);
        }

        if ($hora) {
            $notificaciones->where('hora', '=', $hora);
        }

        if ($asunto) {
            $notificaciones->where('asunto', '=', $asunto);
        }

        if ($estado) {
            $notificaciones->where('idestado', '=', $estado);
        }

        if ($remitente) {
            $notificaciones->where(function ($q) use ($remitente) {
                return $q->where('idUsuarioRemitente', '=', $remitente);
            });
        }
        $notificaciones->orderBy('fecha', 'desc');

        return response()->json($notificaciones->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $carbon = \Carbon\Carbon::now();

        $data = $request->all();
        $notificacion = new Notificacion($data);
        $notificacion->idestado = 1;
        $notificacion->fecha = $carbon->toDateString();
        $notificacion->hora = $carbon->toTimeString();
        $notificacion->idpersonaRemitente = $request->user()->idpersona;
        $notificacion->save();

        return response()->json($notificacion, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id, Request $request)
    {
        $notificacion = Notificacion::where(function ($q) use ($request) {
            return $q->where('idUsuarioRemitente', $request->user()->idpersona)->orWhere('idUsuarioReceptor', $request->user()->idpersona);
        })->find($id);

        return response()->json($notificacion);
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
        $notificacion = Notificacion::where('idUsuarioRemitente', $request->user()->idpersona)->findOrFail($id);
        if ($notificacion) {
            $notificacion->fill($data);
            $notificacion->save();
        }

        return response()->json($notificacion);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function read(int $id, Request $request)
    {
        $notificacion = Notificacion::where('idUsuarioReceptor', $request->user()->idpersona)->findOrFail($id);
        $notificacion->estado_id = Status::ID_INACTIVO;
        $notificacion->save();
        return response()->json($notificacion);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id, Request $request)
    {
        $notificacion = Notificacion::where('idUsuarioRemitente', $request->user()->idpersona)->findOrFail($id);

        if ($notificacion) {
            $notificacion->delete();
        }

        return response()->json([], 204);
    }
}
