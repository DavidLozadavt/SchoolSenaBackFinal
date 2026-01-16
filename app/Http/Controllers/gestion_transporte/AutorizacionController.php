<?php

namespace App\Http\Controllers\gestion_transporte;

use App\Http\Controllers\Controller;
use App\Models\AsignacionPropietario;
use App\Models\Autorizacion;
use App\Models\Tercero;
use App\Models\TipoTercero;
use App\Models\Transporte\Viaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Fpdi;


class AutorizacionController extends Controller
{
    public function storeRegistroPermisoMenor(Request $request)
    {

        $menor = new Tercero();
        $menor->nombre = $request->nombreMenor;
        $menor->idTipoIdentificacion = $request->tipoDocumentoMenor;
        $menor->identificacion = $request->numeroIdentificacionMenor;
        $menor->idTipoTercero = TipoTercero::CLIENTE;
        $menor->save();


        $responsableOrigen = new Tercero();
        $responsableOrigen->nombre = $request->nombreAutorizante;
        $responsableOrigen->idTipoIdentificacion = $request->tipoDocumentoAutorizante;
        $responsableOrigen->identificacion = $request->numeroIdentificacionAutorizante;
        $responsableOrigen->telefono = $request->telefonoAutorizante;
        $responsableOrigen->email = $request->emailAutorizante;
        $responsableOrigen->idTipoTercero = TipoTercero::CLIENTE;
        $responsableOrigen->direccion = $request->direccionAutorizante;
        $responsableOrigen->save();


        $acompanante = new Tercero();
        $acompanante->nombre = $request->nombreAcompanante;
        $acompanante->idTipoIdentificacion = $request->tipoDocumentoAcompanante;
        $acompanante->idTipoTercero = TipoTercero::CLIENTE;
        $acompanante->identificacion = $request->numeroIdentificacionAcompanante;
        $acompanante->save();


        $responsableDestino = new Tercero();
        $responsableDestino->nombre = $request->nombreReceptor;
        $responsableDestino->idTipoIdentificacion = $request->tipoDocumentoReceptor;
        $responsableDestino->identificacion = $request->numeroIdentificacionReceptor;
        $responsableDestino->telefono = $request->telefonoReceptor;
        $responsableDestino->idTipoTercero = TipoTercero::CLIENTE;
        $responsableDestino->email = $request->emailReceptor;
        $responsableDestino->direccion = $request->direccionReceptor;

        $responsableDestino->save();

        $autorizacion = new Autorizacion();
        $autorizacion->idMenorEdad = $menor->id;
        $autorizacion->idViaje = $request->idViaje;
        $autorizacion->idResponsableAutorizado = $acompanante->id;
        $autorizacion->idResponsableOrigen = $responsableOrigen->id;
        $autorizacion->idResponsableDestino = $responsableDestino->id;
        $autorizacion->discapacidad = $request->discapacidad;
        $autorizacion->comunidadEtnica = $request->comunidadEtnica;
        $autorizacion->parentesco = $request->parentesco;
        $autorizacion->tipoDiscapacidad = $request->tipoDiscapacidad;
        $autorizacion->tipoPoblacionEtnica = $request->tipoPoblacionEtnica;

        $autorizacion->save();
        $file = storage_path('app/plantillas/AUTORIZACIONTRANSMENOR.pdf');
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($file);
        $tplIdx = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tplIdx, 0, 0);

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetXY(45, 27.6);
        $pdf->Write(8, $menor->nombre);

        $pdf->SetXY(152.4, 40);
        $pdf->Write(8, $menor->identificacion);

        if ($menor->idTipoIdentificacion == 6) {
            $pdf->SetXY(10.4, 35.6);
            $pdf->Write(8, 'X');
        } elseif ($menor->idTipoIdentificacion == 2) {
            $pdf->SetXY(10.4, 39.6);
            $pdf->Write(8, 'X');
        }


        if (strtoupper($autorizacion->discapacidad) === 'SI') {

            $pdf->SetXY(10.4, 50);
            $pdf->Write(8, 'X');

            switch ($autorizacion->tipoDiscapacidad) {
                case 'FISICA':
                    $pdf->SetXY(10.4, 58);
                    break;

                case 'COGNITIVA':
                    $pdf->SetXY(10.6, 62.1);
                    break;
                case 'MENTAL':
                    $pdf->SetXY(98.4, 62.1);
                    break;
                case 'VISUAL':
                    $pdf->SetXY(98.4, 58);
                    break;
                    $pdf->SetXY(98.4, 62.1);
                    break;
                case 'AUDITIVA':
                    $pdf->SetXY(145.2, 61.6);
                    break;
                case 'MULTIPLE':
                    $pdf->SetXY(145.2, 58);
                    break;
            }
            $pdf->Write(8, 'X');
        } else {
            $pdf->SetXY(39.4, 50);
            $pdf->Write(8, 'X');
        }


        if (strtoupper($autorizacion->comunidadEtnica) === 'SI') {
            $pdf->SetXY(10.4, 71);
            $pdf->Write(8, 'X');
        } else {
            $pdf->SetXY(39.4, 71);
            $pdf->Write(8, 'X');
        }


        if (strtoupper($autorizacion->comunidadEtnica) === 'SI') {
            switch ($autorizacion->tipoPoblacionEtnica) {
                case 'INDIGENA':
                    $pdf->SetXY(10.4, 78.6);
                    break;
                case 'GITANO':
                    $pdf->SetXY(98.4, 79);
                    break;
                case 'PALENQUERO':
                    $pdf->SetXY(10.4, 82.2);
                    break;
                case 'RAIZAL':
                    $pdf->SetXY(98.4, 82.2);
                    break;
                case 'OTRA':
                    $pdf->SetXY(145, 79);
                    break;
            }
            $pdf->Write(8, 'X');
        }


        $pdf->SetXY(45, 92);
        $pdf->Write(8, $responsableOrigen->nombre);

        $pdf->SetXY(150, 108);
        $pdf->Write(8, $responsableOrigen->identificacion);

        if ($responsableOrigen->idTipoIdentificacion == 1) {
            // Cédula de ciudadanía, por ejemplo
            $pdf->SetXY(10.4, 100);
            $pdf->Write(8, 'X');
        } elseif ($responsableOrigen->idTipoIdentificacion == 3) {
            // passaporte
            $pdf->SetXY(98.4, 100);
            $pdf->Write(8, 'X');
        } elseif ($responsableOrigen->idTipoIdentificacion == 4) {
            //cc extranjeria
            $pdf->SetXY(10.4, 103.5);
            $pdf->Write(8, 'X');
        }

        if ($autorizacion->parentesco == 'PADRE') {

            $pdf->SetXY(10.4, 118);
            $pdf->Write(8, 'X');
        } elseif ($autorizacion->parentesco == 'MADRE') {

            $pdf->SetXY(10.4, 122);
            $pdf->Write(8, 'X');
        } elseif ($autorizacion->parentesco == 'TUTOR') {

            $pdf->SetXY(10.4, 126);
            $pdf->Write(8, 'X');
        }



        $pdf->SetXY(133, 118);
        $pdf->Write(8, $responsableOrigen->telefono);


        $pdf->SetXY(133, 122);
        $pdf->Write(8, $responsableOrigen->email);


        $pdf->SetXY(133, 126);
        $pdf->Write(8, $responsableOrigen->direccion);


        $pdf->SetXY(45, 208);
        $pdf->Write(8, $responsableDestino->nombre);

        $pdf->SetXY(148.4, 224);
        $pdf->Write(8, $responsableDestino->identificacion);


        if ($responsableDestino->idTipoIdentificacion == 1) {
            // Cédula de ciudadanía, por ejemplo
            $pdf->SetXY(10.4, 216);
            $pdf->Write(8, 'X');
        } elseif ($responsableDestino->idTipoIdentificacion == 3) {
            // passaporte
            $pdf->SetXY(98.4, 215.6);
            $pdf->Write(8, 'X');
        } elseif ($responsableDestino->idTipoIdentificacion == 4) {
            //cc extranjeria
            $pdf->SetXY(10.4, 220);
            $pdf->Write(8, 'X');
        }


        $pdf->SetXY(42, 231);
        $pdf->Write(8, $responsableDestino->telefono);


        $pdf->SetXY(133, 231);
        $pdf->Write(8, $responsableDestino->email);


        $pdf->SetXY(42, 235);
        $pdf->Write(8, $responsableDestino->direccion);



        $pdf->SetXY(34, 197);
        $pdf->Write(8, $request->placaVehiculo);


        $pdf->SetXY(34, 192.4);
        $pdf->Write(8, $request->fechaViaje);

        $pdf->SetXY(84, 192.4);
        $pdf->Write(8, $request->origen);

        $pdf->SetXY(88, 197);
        $pdf->Write(8, $request->numeroInterno);

        
        $pdf->SetXY(155, 197);
        $pdf->Write(8, $request->numeroTiquete);


        $pdf->SetXY(155, 192.4);
        $pdf->Write(8, $request->destino);


        $pdfOutput = $pdf->Output('S');

        return response($pdfOutput, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="autorizacion_menor.pdf"');
    }



    public function getViajesForAutorizacion()
    {
        $viajes = Viaje::whereIn('estado', ['PENDIENTE', 'PLANILLA'])
            ->whereNotNull('idVehiculo')
            ->whereNotNull('idConductor')
            ->with([
                'ruta.ciudadOrigen',
                'ruta.ciudadDestino',
                'agendarViajes',
                'tickets',
                'vehiculo'
            ])
            ->get();

        // Obtener todos los IDs de vehículos
        $vehiculoIds = $viajes->pluck('idVehiculo')->unique()->filter()->toArray();

        if (!empty($vehiculoIds)) {
            // Cargar asignaciones con afiliaciones para todos los vehículos
            $asignacionesConAfiliacion = AsignacionPropietario::with('afiliacion')
                ->whereIn('idVehiculo', $vehiculoIds)
                ->get()
                ->groupBy('idVehiculo'); // Agrupar por vehículo

            // Asignar a cada viaje
            $viajes->each(function ($viaje) use ($asignacionesConAfiliacion) {
                if ($viaje->vehiculo) {
                    $viaje->vehiculo->asignacionPropietarios = $asignacionesConAfiliacion->get($viaje->idVehiculo, []);
                }
            });
        }

        return response()->json($viajes);
    }
}