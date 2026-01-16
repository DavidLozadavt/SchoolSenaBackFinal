<?php

namespace App\Http\Controllers\gestion_afiliacion;

use App\Http\Controllers\Controller;
use App\Models\ActivationCompanyUser;
use App\Models\Afiliacion;
use App\Models\AfiliacionEstado;
use App\Models\AsignacionConductor;
use App\Models\AsignacionProcesoPago;
use App\Models\AsignacionProcesoTipoDocumento;
use App\Models\AsignacionPropietario;
use App\Models\AsignacionTipoAfiliacion;
use App\Models\ContratoVinculacion;
use App\Models\DocumentoAfiliacion;
use App\Models\DocumentoConductor;
use App\Models\DocumentoPropietario;
use App\Models\DocumentoVehiculo;
use App\Models\InformacionEmpresaPersonaJuridica;
use App\Models\InformacionPersonaJuridica;
use App\Models\InformacionPersonaNatural;
use App\Models\ObservacionAfiliacion;
use App\Models\Person;
use App\Models\ReferenciaPersonal;
use App\Models\Restriccion;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehiculo;
use App\Util\KeyUtil;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use App\Models\AsignacionDetalleRevisionVehiculo;
use App\Models\AsignacionFacturaTransaccion;
use App\Models\AsignacionTerceroTipoTercero;
use App\Models\Company;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Tercero;
use App\Models\TipoFactura;
use App\Models\TipoTercero;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;

class AfiliacionVehiculoController extends Controller
{
    public function getDocumentsByProceso($id)
    {
        $documents = AsignacionProcesoTipoDocumento::with('tipoDocumento', 'proceso')
            ->where('idProceso', $id)->get();
        return response()->json($documents);
    }


    public function getDocumentsByNombreProceso($nombre)
    {
        $documents = AsignacionProcesoTipoDocumento::with('tipoDocumento', 'proceso')
            ->whereHas('proceso', function ($query) use ($nombre) {
                $query->where('nombreProceso', $nombre);
            })
            ->get();

        return response()->json($documents);
    }


    public function getPagosByNombreProceso($nombre)
    {
        $pagos = AsignacionProcesoPago::with('configuracionPago', 'proceso')
            ->whereHas('proceso', function ($query) use ($nombre) {
                $query->where('nombreProceso', $nombre);
            })
            ->whereHas('configuracionPago', function ($query) {
                $query->where('estado', 'ACTIVO');
            })
            ->get();

        return response()->json($pagos);
    }


    public function existAfiliacion($numeroOrdenServicio)
    {
        $afiliacion = Afiliacion::where('numero', $numeroOrdenServicio)->first();
        return response()->json($afiliacion);
    }

    public function existPlaca($placa)
    {
        $vehiculo = Vehiculo::where('placa', $placa)->first();
        return response()->json($vehiculo);
    }


    // public function existPerson($identificacion)
    // {
    //     $persona = Person::where('identificacion', $identificacion)->first();
    //     return response()->json($persona);
    // }


    // public function existEmailPerson($email)
    // {
    //     $persona = Person::where('identificacion', $email)->first();
    //     return response()->json($persona);
    // }


    public function storeAfiliacion(Request $request)
    {

        DB::beginTransaction();

        try {

            $afiliacion = new Afiliacion();
            $afiliacion->numero = $request->numeroOrdenServicio;
            $afiliacion->estado = 'PENDIENTE';
            $afiliacion->idEmpresa = KeyUtil::idCompany();

            $afiliacion->numeroContratoCoperativa = $request->numeroContratoCoperativa;
            $afiliacion->numeroContratoRadio = $request->numeroContratoRadio;
            $afiliacion->restricciones = $request->restricciones;
            $afiliacion->observaciones = $request->observaciones;

            $afiliacion->save();


            $afiliacionEstado = new AfiliacionEstado();
            $afiliacionEstado->fechaInicial = new DateTime();
            $afiliacionEstado->idAfiliacion = $afiliacion->id;
            $afiliacionEstado->observacion = 'Nueva afiliación';
            $afiliacionEstado->estado = 'PENDIENTE';
            $afiliacionEstado->save();


            $asignacionTipoAfiliacion = new AsignacionTipoAfiliacion();
            $asignacionTipoAfiliacion->idTipoAfiliacion = $request->tipoAfiliacion;
            $asignacionTipoAfiliacion->idAfiliacion = $afiliacion->id;
            $asignacionTipoAfiliacion->save();


            $vehiculo = new Vehiculo();
            $vehiculo->placa = $request->placa;
            $vehiculo->chasis = $request->chasis;
            $vehiculo->motor = $request->motor;
            $vehiculo->idModelo = $request->modelo;
            $vehiculo->idTipo = $request->tipoV;
            $vehiculo->idClaseVehiculo = $request->idClaseVehiculo;
            $vehiculo->tipoCombustible = $request->tipoCombustible;
            $vehiculo->idMarca = $request->marca;
            $vehiculo->idEstado = Status::ID_ACTIVE;
            $vehiculo->numPuestos = $request->numPuestos;
            $vehiculo->idEmpresa = KeyUtil::idCompany();

            if ($request->hasFile('fileVehiculo')) {
                $vehiculo->foto = $this->storeFileVehiculo($request->file('fileVehiculo'));
            } else {
                $vehiculo->foto = Vehiculo::ATTACHMENT_DEFAULT;
            }

            $vehiculo->save();

            if ($request->hasFile('vehiculoFiles')) {
                foreach ($request->file('vehiculoFiles') as $idTipoDocumento => $file) {
                    $fechaExpedicionKey = "vehiculoFilesFechaExpedicion.$idTipoDocumento";
                    $fechaExpedicion = $request->input($fechaExpedicionKey, null);

                    $numeroDocumentoKey = "vehiculoFilesNumero.$idTipoDocumento";
                    $numeroDocumento = $request->input($numeroDocumentoKey, null);

                    $rutaArchivo = $file->store('public/documentos_vehiculo');
                    $rutaPublica = str_replace('public/', 'storage/', $rutaArchivo);


                    $documentoVehiculo = new DocumentoVehiculo();
                    $documentoVehiculo->ruta = $rutaPublica;
                    $documentoVehiculo->idVehiculo = $vehiculo->id;
                    $documentoVehiculo->fechaCarga = now();
                    $documentoVehiculo->numeroDocumento = $numeroDocumento;
                    $documentoVehiculo->fecha_vigencia = $fechaExpedicion;
                    $documentoVehiculo->idTipoDocumento = $idTipoDocumento;
                    $documentoVehiculo->idEstado = Status::ID_ACTIVE;
                    $documentoVehiculo->save();
                }
            }

            DB::commit();

            return response()->json([
                'idVehiculo' => $vehiculo->id,
                'idAfiliacion' => $afiliacion->id
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.', 'message' => $e->getMessage()], 500);
        }
    }


    public function storePropietario(Request $request, $idVehiculo, $idAfiliacion)
    {
        DB::beginTransaction();

        try {
            $propietarios = $request->input('propietarios');


            foreach ($propietarios as $index => $propietarioData) {
                $propietario = new Person();
                $propietario->fechaNac = $propietarioData['fechaNac'];
                $propietario->idtipoIdentificacion = $propietarioData['idtipoIdentificacion'];
                $propietario->identificacion = $propietarioData['identificacion'];
                $propietario->nombre1 = $propietarioData['nombre1'];
                $propietario->nombre2 = $propietarioData['nombre2'] ?? null;
                $propietario->apellido1 = $propietarioData['apellido1'];
                $propietario->apellido2 = $propietarioData['apellido2'] ?? null;
                $propietario->idCiudadNac = $propietarioData['idciudadNac'];
                $propietario->celular = $propietarioData['celular'];
                $propietario->email = $propietarioData['email'];
                $propietario->direccion = $propietarioData['direccion'];
                $propietario->idCiudadUbicacion = $propietarioData['idciudadUbicacion'] ?? null;
                $propietario->telefonoFijo = $propietarioData['telefonoFijo'] ?? null;
                $propietario->sexo = $propietarioData['sexo'];
                $propietario->rh = $propietarioData['rh'];
                $propietario->celularExtra = $propietarioData['celularExtra'];
                $propietario->emailExtra = $propietarioData['emailExtra'];
                $propietario->idCiudadUbicacion = $propietarioData['idciudadUbicacion'];
                $propietario->perfil = 'N/A';
                $propietario->tipoTitular = $propietarioData['tipoTitular'];
                $propietario->rutaFoto = $this->storeLogoPersona($request, $index);

                $propietario->save();


                $user = User::where('idpersona', $propietario->id)->first();
                if (!$user) {
                    $user = new User();
                    $user->id = $propietario->id;
                    $user->email = $propietario->email;
                    $user->contrasena = bcrypt($propietarioData['identificacion']);
                    $user->idpersona = $propietario->id;
                    $user->save();
                }


                $activationUser = new ActivationCompanyUser();
                $activationUser->user_id = $user->id;
                $activationUser->state_id = Status::ID_ACTIVE;
                $activationUser->fechaInicio = new DateTime();
                $activationUser->fechaFin = (new DateTime())->modify('+1 year');

                $activationUser->saveWithCompany();

                //pendiente de crear el rol del propietario en el sistema

                $asignacionPropietario = new AsignacionPropietario();
                $asignacionPropietario->idVehiculo = $idVehiculo;
                $asignacionPropietario->fechaAsignacion = new DateTime();
                $asignacionPropietario->idAfiliacion = $idAfiliacion;
                $asignacionPropietario->idPropietario = $propietario->id;
                $asignacionPropietario->porcentaje = $propietarioData['porcentaje'];
                $asignacionPropietario->administrador = $propietarioData['propietarioAdmin'] ?? 'No';

                $asignacionPropietario->estado = 'ACTIVO';
                $asignacionPropietario->save();

                // Crear tercero para todos los propietarios
                $tecero = new Tercero();
                $tecero->nombre = trim($propietario->nombre1 . ' ' . $propietario->nombre2 . ' ' . $propietario->apellido1 . ' ' . $propietario->apellido2);
                $tecero->identificacion = $propietario->identificacion;
                $tecero->idTipoIdentificacion = $propietario->idtipoIdentificacion;
                $tecero->email = $propietario->email;
                $tecero->direccion = $propietario->direccion;
                $tecero->telefono = $propietario->celular;
                $tecero->save();

                $asignacionTerceroTipoTercero = new AsignacionTerceroTipoTercero();
                $asignacionTerceroTipoTercero->idTercero = $tecero->id;

                if ($propietario->tipoTitular === 'PERSONA NATURAL') {
                    $asignacionTerceroTipoTercero->idTipoTercero = TipoTercero::PERSONA_NATURAL_ASOCIADO;
                } elseif ($propietario->tipoTitular === 'PERSONA JURIDICA') {
                    $asignacionTerceroTipoTercero->idTipoTercero = TipoTercero::PERSONA_JURIDICA_ASOCIADO;
                }

                $asignacionTerceroTipoTercero->save();

                if (($propietarioData['propietarioAdmin'] ?? 'No') === 'Si') {

                    $company = Company::findOrFail(KeyUtil::idCompany());

                    $pagosAdicionales = $request->input('pagos', []);

                    $valorTotal = $company->valorAfiliacion;
                    foreach ($pagosAdicionales as $pagoData) {
                        $valorTotal += floatval($pagoData['valor']);
                    }

                    $factura = new Factura();

                    $lastFactura = Factura::where('idTipoFactura', TipoFactura::VENTA)
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($lastFactura) {
                        $nextNumFactura = str_pad(intval($lastFactura->numeroFactura) + 1, 5, '0', STR_PAD_LEFT);
                    } else {
                        $nextNumFactura = '00001';
                    }
                    $factura->numeroFactura = $nextNumFactura;

                    $factura->valor = $valorTotal;
                    $factura->fecha = Carbon::now();
                    $factura->valorIva = 0;
                    $factura->valorMasIva = $valorTotal;
                    $factura->idTercero = $tecero->id;
                    $factura->idTipoFactura = TipoFactura::VENTA;
                    $factura->save();


                    $detalleFactura = new DetalleFactura();
                    $detalleFactura->idFactura = $factura->id;
                    $detalleFactura->detalle = 'Concepto por pago de afiliación';
                    $detalleFactura->valor = $company->valorAfiliacion;
                    $detalleFactura->save();

                    foreach ($pagosAdicionales as $pagoData) {
                        $detalleFacturaPago = new DetalleFactura();
                        $detalleFacturaPago->idFactura = $factura->id;
                        $detalleFacturaPago->detalle = $pagoData['titulo'];
                        $detalleFacturaPago->valor = floatval($pagoData['valor']);
                        $detalleFacturaPago->save();
                    }

                    $transaccion = new Transaccion();
                    $transaccion->valor = $valorTotal;
                    $transaccion->hora = Carbon::now()->format('H:i');
                    $transaccion->fechaTransaccion = Carbon::now();
                    $transaccion->tipoCartera = 'CXP';
                    $transaccion->idTipoTransaccion = TipoTransaccion::AFILIACION;
                    $transaccion->save();

                    $asignacionFacturatransaccion = new AsignacionFacturaTransaccion();
                    $asignacionFacturatransaccion->idFactura = $factura->id;
                    $asignacionFacturatransaccion->idTransaccion = $transaccion->id;
                    $asignacionFacturatransaccion->save();

                    $pago = new Pago();
                    $pago->fechaPago = Carbon::now();
                    $pago->fechaReg = Carbon::now();
                    $pago->valor = $valorTotal;
                    $pago->excedente = $transaccion->excedente;
                    $pago->idEstado = Status::ID_PENDIENTE;
                    $pago->idTransaccion = $transaccion->id;
                    $pago->save();
                }

                if (!empty($propietarioData['documentos'])) {
                    foreach ($propietarioData['documentos'] as $idTipoDocumento => $documento) {
                        if ($request->hasFile("propietarios.{$index}.documentos.{$idTipoDocumento}.file")) {
                            $file = $request->file("propietarios.{$index}.documentos.{$idTipoDocumento}.file");
                            $rutaDocumento = $file->store('public/documentos_propietario');

                            $documentoPropietario = new DocumentoPropietario();
                            $documentoPropietario->ruta = str_replace('public/', '/storage/', $rutaDocumento);
                            $documentoPropietario->idPropietario = $propietario->id;
                            $documentoPropietario->fechaCarga = new DateTime();
                            $documentoPropietario->fecha_vigencia = $documento['fechaExpedicion'];
                            $documentoPropietario->idTipoDocumento = $idTipoDocumento;
                            $documentoPropietario->idEstado = Status::ID_ACTIVE;
                            $documentoPropietario->save();
                        }
                    }
                }


                if (!empty($propietarioData['observaciones'])) {

                    if (is_string($propietarioData['observaciones'])) {
                        $observaciones = array_map('trim', explode(',', $propietarioData['observaciones']));
                    } else {

                        $observaciones = [];
                        foreach ($propietarioData['observaciones'] as $obs) {
                            $partes = array_map('trim', explode(',', $obs));
                            $observaciones = array_merge($observaciones, $partes);
                        }
                    }

                    foreach ($observaciones as $obs) {
                        if ($obs !== '') {
                            $restriccion = new Restriccion();
                            $restriccion->idPersona = $propietario->id;
                            $restriccion->fecha = now();
                            $restriccion->observacion = $obs;
                            $restriccion->save();
                        }
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Propietarios registrados correctamente'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.', 'message' => $e->getMessage()], 500);
        }
    }




    public function storeConductor(Request $request, $idVehiculo, $idAfiliacion)
    {
        DB::beginTransaction();

        try {
            $conductores = $request->input('conductores');

            foreach ($conductores as $index => $conductoresData) {

                $conductor = new Person();
                $conductor->fechaNac = $conductoresData['fechaNac'];
                $conductor->idtipoIdentificacion = $conductoresData['idtipoIdentificacion'];
                $conductor->identificacion = $conductoresData['identificacion'];
                $conductor->nombre1 = $conductoresData['nombre1'];
                $conductor->nombre2 = $conductoresData['nombre2'] ?? null;
                $conductor->apellido1 = $conductoresData['apellido1'];
                $conductor->apellido2 = $conductoresData['apellido2'] ?? null;
                $conductor->idCiudadNac = $conductoresData['idciudadNac'];
                $conductor->celular = $conductoresData['celular'];
                $conductor->email = $conductoresData['email'];
                $conductor->direccion = $conductoresData['direccion'];
                $conductor->idCiudadUbicacion = $conductoresData['idciudadUbicacion'] ?? null;
                $conductor->telefonoFijo = $conductoresData['telefonoFijo'] ?? null;
                $conductor->sexo = $conductoresData['sexo'];
                $conductor->rh = $conductoresData['rh'];
                $conductor->perfil = 'N/A';
                $conductor->celularExtra = $conductoresData['celularExtra'] ?? null;
                $conductor->emailExtra = $conductoresData['emailExtra'] ?? null;
                $conductor->rutaFoto = $this->storeLogoConductor($request, $index);
                $conductor->save();

                // Verificar si ya existe un usuario asociado
                $user = User::where('idpersona', $conductor->id)->first();
                if (!$user) {
                    $user = new User();
                    $user->id = $conductor->id;
                    $user->email = $conductor->email;
                    $user->contrasena = bcrypt($conductoresData['identificacion']);
                    $user->idpersona = $conductor->id;
                    $user->save();
                }

                // Asignar conductor al vehículo
                $asignacionConductor = new AsignacionConductor();
                $asignacionConductor->idConductor = $conductor->id;
                $asignacionConductor->fechaAsignacion = new DateTime('now');
                $asignacionConductor->idAfiliacion = $idAfiliacion;
                $asignacionConductor->estado = 'ACTIVO';
                $asignacionConductor->save();

                // Guardar documentos si existen
                if (!empty($conductoresData['documentos'])) {
                    foreach ($conductoresData['documentos'] as $idTipoDocumento => $documento) {
                        if ($request->hasFile("conductores.{$index}.documentos.{$idTipoDocumento}.file")) {
                            $file = $request->file("conductores.{$index}.documentos.{$idTipoDocumento}.file");
                            $rutaDocumento = $file->store('public/documentos_conductor');

                            $documentConductor = new DocumentoConductor();
                            $documentConductor->ruta = str_replace('public/', '/storage/', $rutaDocumento);
                            $documentConductor->idConductor = $conductor->id;
                            $documentConductor->fechaCarga = new DateTime();
                            $documentConductor->fecha_vigencia = $documento['fechaExpedicion'];
                            $documentConductor->idTipoDocumento = $idTipoDocumento;
                            $documentConductor->idEstado = Status::ID_ACTIVE;
                            $documentConductor->save();
                        }
                    }
                }

                if (!empty($conductoresData['observaciones'])) {

                    if (is_string($conductoresData['observaciones'])) {
                        $observaciones = array_map('trim', explode(',', $conductoresData['observaciones']));
                    } else {

                        $observaciones = [];
                        foreach ($conductoresData['observaciones'] as $obs) {
                            $partes = array_map('trim', explode(',', $obs));
                            $observaciones = array_merge($observaciones, $partes);
                        }
                    }

                    foreach ($observaciones as $obs) {
                        if ($obs !== '') {
                            $restriccion = new Restriccion();
                            $restriccion->idPersona = $conductor->id;
                            $restriccion->fecha = now();
                            $restriccion->restriccion = $obs;
                            $restriccion->save();
                        }
                    }
                }
            }

            DB::commit();
            return response()->json($conductor, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.', 'message' => $e->getMessage()], 500);
        }
    }



    private function storeLogoConductor(Request $request, $index, $default = true)
    {
        $rutaFotoPersona = null;

        if ($default) {
            $rutaFotoPersona = Person::RUTA_FOTO_DEFAULT;
        }

        if ($request->hasFile("conductores.{$index}.foto")) {
            $rutaFotoPersona =
                '/storage/' .
                $request
                ->file("conductores.{$index}.foto")
                ->store(Person::RUTA_FOTO, ['disk' => 'public']);
        }

        return $rutaFotoPersona;
    }


    private function storeLogoPersona(Request $request, $index, $default = true)
    {
        $rutaFotoPersona = null;

        if ($default) {
            $rutaFotoPersona = Person::RUTA_FOTO_DEFAULT;
        }

        if ($request->hasFile("propietarios.{$index}.foto")) {
            $rutaFotoPersona =
                '/storage/' .
                $request
                ->file("propietarios.{$index}.foto")
                ->store(Person::RUTA_FOTO, ['disk' => 'public']);
        }

        return $rutaFotoPersona;
    }


    private function storeFileVehiculo($archivo)
    {
        if ($archivo) {
            $path = $archivo->store('public/vehiculo');
            return '/storage/vehiculo/' . basename($path);
        }
        return Vehiculo::ATTACHMENT_DEFAULT;
    }


    public function getAfiliaciones(Request $request)
    {
        $query = Afiliacion::with([
            'tipoAfiliacion',
            'propietario' => function ($q) {
                $q->wherePivot('administrador', 'Si');
            },
            'vehiculo' => function ($q) {
                $q->where('vehiculo.idEstado', 1)->distinct();
            }
        ])->where('idEmpresa', KeyUtil::idCompany())
            ->whereIn('estado', ['ACTIVO', 'INACTIVO']);

        if ($request->has('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->orWhere('numero', 'like', "%$search%")
                    ->orWhere('fechaAfiliacion', 'like', "%$search%")
                    ->orWhereHas('vehiculo', function ($q) use ($search) {
                        $q->where('vehiculo.placa', 'like', "%$search%");
                    })
                    ->orWhereHas('tipoAfiliacion', function ($q) use ($search) {
                        $q->where('tipoAfiliacion', 'like', "%$search%");
                    })

                    ->orWhereHas('propietario', function ($q) use ($search) {
                        $q->where('identificacion', 'like', "%$search%");
                    });
            });
        }

        $afiliaciones = $query->paginate(10);

        return response()->json($afiliaciones);
    }


    public function getAfiliacionesPendientes(Request $request)
    {
        $query = Afiliacion::with([
            'tipoAfiliacion',
            'propietario' => function ($q) {
                $q->wherePivot('administrador', 'Si');
            },
            'vehiculo' => function ($q) {
                $q->where('vehiculo.idEstado', 1)->distinct();
            }
        ])->where('idEmpresa', KeyUtil::idCompany())
            ->whereIn('estado', ['PENDIENTE']);

        if ($request->has('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->orWhere('numero', 'like', "%$search%")
                    ->orWhere('fechaAfiliacion', 'like', "%$search%")
                    ->orWhereHas('vehiculo', function ($q) use ($search) {
                        $q->where('vehiculo.placa', 'like', "%$search%");
                    })
                    ->orWhereHas('tipoAfiliacion', function ($q) use ($search) {
                        $q->where('tipoAfiliacion', 'like', "%$search%");
                    })

                    ->orWhereHas('propietario', function ($q) use ($search) {
                        $q->where('identificacion', 'like', "%$search%");
                    });
            });
        }

        $afiliaciones = $query->paginate(10);

        return response()->json($afiliaciones);
    }




    public function changeStatusAfiliacion(Request $request, $idAfiliacion)
    {
        $afiliacion = Afiliacion::find($idAfiliacion);

        if (!$afiliacion) {
            return response()->json(['message' => 'Afiliación no encontrada'], 404);
        }

        $nuevoEstado = $afiliacion->estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';

        if ($afiliacion->estado === 'PENDIENTE' && $nuevoEstado === 'ACTIVO') {
            $afiliacion->fechaAfiliacion = now();
        }

        $afiliacion->estado = $nuevoEstado;
        $afiliacion->save();

        $ultimaAfiliacionEstado = AfiliacionEstado::where('idAfiliacion', $idAfiliacion)
            ->orderBy('fechaInicial', 'desc')
            ->first();

        if ($ultimaAfiliacionEstado && !$ultimaAfiliacionEstado->fechaFinal) {
            $ultimaAfiliacionEstado->fechaFinal = now();
            $ultimaAfiliacionEstado->save();
        }

        $afiliacionEstado = new AfiliacionEstado();
        $afiliacionEstado->fechaInicial = now();
        $afiliacionEstado->estado = $nuevoEstado;
        $afiliacionEstado->observacion = $request->observacion ?? null;
        $afiliacionEstado->idAfiliacion = $idAfiliacion;
        $afiliacionEstado->save();

        return response()->json($afiliacionEstado, 201);
    }


    public function getDrivers()
    {
        $conductores = Person::whereHas('asignacionesConductor', function ($query) {
            $query->where('estado', 'ACTIVO');
        })
            ->with(['contrato' => function ($query) {
                $query->where('idEstado', 1);
            }])
            ->get();

        return response()->json($conductores);
    }



    public function getVehiculosByAfiliacion($idAfiliacion)
    {
        $afiliacion = Afiliacion::with(['vehiculo.marca', 'vehiculo.documentosVehiculo', 'vehiculo.modelo', 'vehiculo.tipoVehiculo', 'vehiculo.estado', 'vehiculo.claseVehiculo', 'tipoAfiliacion'])
            ->where('id', $idAfiliacion)
            ->first();

        if ($afiliacion) {
            $afiliacion->vehiculo = $afiliacion->vehiculo->unique('id')->values();
        }

        return response()->json($afiliacion);
    }


    public function getPropietariosByAfiliacion($idAfiliacion)
    {
        $afiliacion = Afiliacion::with([
            'propietario' => function ($query) {
                $query->where('estado', 'ACTIVO')
                    ->whereNotNull('asignacionPropietario.porcentaje');
            },
            'propietario.ciudadNac.departamento',
            'propietario.tipoIdentificacion',
            'propietario.restricciones'
        ])
            ->where('id', $idAfiliacion)
            ->get();

        return response()->json($afiliacion);
    }




    public function getConductoresByAfiliacion($idAfiliacion)
    {
        $afiliacion = Afiliacion::with([
            'conductor' => function ($query) {
                $query->where('estado', 'ACTIVO');
            },
            'conductor.ciudadNac.departamento',
            'conductor.tipoIdentificacion',
            'conductor.restricciones'
        ])
            ->where('id', $idAfiliacion)
            ->get();

        return response()->json($afiliacion);
    }



    public function changeStatusVehiculo(Request $request, $idVehiculo)
    {
        $vehiculo = Vehiculo::findOrFail($idVehiculo);

        $nuevoEstado = $vehiculo->idEstado == 1 ? 2 : 1;
        $vehiculo->update(['idEstado' => $nuevoEstado]);

        return response()->json($vehiculo);
    }


    public function getObservacionByAfiliacion($idAfiliacion)
    {
        $afiliaciones = AfiliacionEstado::where('idAfiliacion', $idAfiliacion)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($afiliaciones->isEmpty()) {
            return response()->json(['mensaje' => 'No se encontraron afiliaciones'], 404);
        }

        $afiliaciones->shift();

        $afiliaciones = $afiliaciones->sortByDesc('created_at')->values();

        $afiliaciones->transform(function ($afiliacion) {
            $fechaInicial = Carbon::parse($afiliacion->fechaInicial);
            $fechaFinal = $afiliacion->fechaFinal ? Carbon::parse($afiliacion->fechaFinal) : Carbon::now();
            $diasTranscurridos = $fechaInicial->diffInDays($fechaFinal);

            $afiliacion->diasTranscurridos = $diasTranscurridos;

            return $afiliacion;
        });

        return response()->json($afiliaciones);
    }



    public function storeVehiculoAfiliacion(Request $request, $idAfiliacion)
    {

        DB::beginTransaction();

        try {

            $vehiculo = new Vehiculo();
            $vehiculo->placa = $request->placa;
            $vehiculo->chasis = $request->chasis;
            $vehiculo->idModelo = $request->modelo;
            $vehiculo->motor = $request->motor;
            $vehiculo->idTipo = $request->tipoV;
            $vehiculo->idMarca = $request->marca;
            $vehiculo->idClaseVehiculo = $request->idClaseVehiculo;
            $vehiculo->tipoCombustible = $request->tipoCombustible;
            $vehiculo->idEstado = Status::ID_ACTIVE;
            $vehiculo->numPuestos = $request->numPuestos;
            $vehiculo->idEmpresa = KeyUtil::idCompany();
            $vehiculo->color = $request->color;
            $vehiculo->radioAccion = $request->radioAccion;


            if ($request->hasFile('fileVehiculo')) {
                $vehiculo->foto = $this->storeFileVehiculo($request->file('fileVehiculo'));
            } else {
                $vehiculo->foto = Vehiculo::ATTACHMENT_DEFAULT;
            }

            $vehiculo->save();


            $asignacionPropietarioVehiculo = AsignacionPropietario::where('idAfiliacion', $idAfiliacion)
                ->where('administrador', 'Si')
                ->first();

            $asignacionVehiculo = new AsignacionPropietario();
            $asignacionVehiculo->idVehiculo = $vehiculo->id;
            $asignacionVehiculo->fechaAsignacion = new DateTime('now');
            $asignacionVehiculo->idAfiliacion = $idAfiliacion;
            $asignacionVehiculo->idEstado = Status::ID_ACTIVE;
            $asignacionVehiculo->administrador = 'No';
            $asignacionVehiculo->idPropietario = $asignacionPropietarioVehiculo->idPropietario ?? null;
            $asignacionVehiculo->save();



            if ($request->hasFile('vehiculoFiles')) {
                foreach ($request->file('vehiculoFiles') as $idTipoDocumento => $file) {
                    $fechaExpedicionKey = "vehiculoFilesFechaExpedicion.$idTipoDocumento";
                    $fechaExpedicion = $request->input($fechaExpedicionKey, null);


                    $numeroDocumentoKey = "vehiculoFilesNumero.$idTipoDocumento";
                    $numeroDocumento = $request->input($numeroDocumentoKey, null);

                    $rutaArchivo = $file->store('public/documentos_vehiculo');
                    $rutaPublica = str_replace('public/', 'storage/', $rutaArchivo);


                    $documentoVehiculo = new DocumentoVehiculo();
                    $documentoVehiculo->ruta = $rutaPublica;
                    $documentoVehiculo->idVehiculo = $vehiculo->id;
                    $documentoVehiculo->fechaCarga = now();
                    $documentoVehiculo->fecha_vigencia = $fechaExpedicion;
                    $documentoVehiculo->idTipoDocumento = $idTipoDocumento;
                    $documentoVehiculo->numeroDocumento = $numeroDocumento;
                    $documentoVehiculo->idEstado = Status::ID_ACTIVE;
                    $documentoVehiculo->save();
                }
            }

            DB::commit();

            return response()->json([
                'idVehiculo' => $vehiculo->id,

            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.', 'message' => $e->getMessage()], 500);
        }
    }




    public function updateVehiculoAfiliacion(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            // Obtener vehículo existente
            $vehiculo = Vehiculo::findOrFail($id);

            // Actualizar campos
            $vehiculo->placa = $request->placa;
            $vehiculo->chasis = $request->chasis;
            $vehiculo->idModelo = $request->modelo;
            $vehiculo->motor = $request->motor;
            $vehiculo->idTipo = $request->tipoV;
            $vehiculo->idMarca = $request->marca;
            $vehiculo->idClaseVehiculo = $request->idClaseVehiculo;
            $vehiculo->tipoCombustible = $request->tipoCombustible;
            $vehiculo->numPuestos = $request->numPuestos;
            $vehiculo->color = $request->color;
            $vehiculo->radioAccion = $request->radioAccion;
            $vehiculo->idEmpresa = KeyUtil::idCompany();

            // Foto del vehículo
            if ($request->hasFile('fileVehiculo')) {
                $vehiculo->foto = $this->storeFileVehiculo($request->file('fileVehiculo'));
            }

            $vehiculo->save();

            if ($request->hasFile('vehiculoFiles')) {
                foreach ($request->file('vehiculoFiles') as $idTipoDocumento => $file) {


                    $documento = DocumentoVehiculo::where('idVehiculo', $vehiculo->id)
                        ->where('idTipoDocumento', $idTipoDocumento)
                        ->first();


                    $fechaExpedicionKey = "vehiculoFilesFechaExpedicion.$idTipoDocumento";
                    $fechaExpedicion = $request->input($fechaExpedicionKey);

                    $numeroDocumentoKey = "vehiculoFilesNumero.$idTipoDocumento";
                    $numeroDocumento = $request->input($numeroDocumentoKey);

                    $rutaArchivo = $file->store('public/documentos_vehiculo');
                    $rutaPublica = str_replace('public/', 'storage/', $rutaArchivo);

                    if (!$documento) {
                        $documento = new DocumentoVehiculo();
                        $documento->idVehiculo = $vehiculo->id;
                        $documento->idTipoDocumento = $idTipoDocumento;
                    }

                    $documento->ruta = $rutaPublica;
                    $documento->fechaCarga = now();
                    $documento->fecha_vigencia = $fechaExpedicion;
                    $documento->numeroDocumento = $numeroDocumento;
                    $documento->idEstado = Status::ID_ACTIVE;

                    $documento->save();
                }
            }

            DB::commit();

            return response()->json([
                'idVehiculo' => $vehiculo->id,
                'message' => 'Vehículo actualizado correctamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error interno del servidor.',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function cambiarEstadoConductor(Request $request, $idConductor)
    {

        $conductor = AsignacionConductor::where('idConductor', $idConductor)
            ->where('idAfiliacion', $request->idAfiliacion)
            ->first();

        if (!$conductor) {
            return response()->json(['error' => 'Conductor no encontrado'], 404);
        }


        $conductor->estado = $conductor->estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $conductor->save();

        return response()->json($conductor);
    }




    public function cambiarEstadoPropietario(Request $request, $idPropietario)
    {

        $propietario = AsignacionPropietario::where('idPropietario', $idPropietario)
            ->where('idAfiliacion', $request->idAfiliacion)
            ->first();

        if (!$propietario) {
            return response()->json(['error' => 'Propietario no encontrado'], 404);
        }

        $propietario->estado = $propietario->estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $propietario->save();

        return response()->json($propietario);
    }




    public function getAllVehiculos()
    {
        $vehiculos = Vehiculo::with(['marca', 'modelo', 'tipoVehiculo', 'estado', 'asignacionPropietario.afiliacion'])
            ->where('idEstado', 1)
            ->get()
            ->map(function ($vehiculo) {
                $ultimaFecha = AsignacionDetalleRevisionVehiculo::where('idVehiculo', $vehiculo->id)
                    ->max('fechaRevision');

                $numeroAfiliacion = $vehiculo->asignacionPropietario?->afiliacion?->numero;

                if (!$ultimaFecha) {
                    return [
                        'id' => $vehiculo->id,
                        'placa' => $vehiculo->placa,
                        'marca' => $vehiculo->marca?->marca,
                        'modelo' => $vehiculo->modelo?->modelo,
                        'foto' => $vehiculo->foto,
                        'estado' => $vehiculo->estado?->estado,
                        'tieneRechazo' => false,
                        'tienePendiente' => false,
                        'sinRevision' => true,
                        'numeroOrden' => $numeroAfiliacion,
                    ];
                }

                $tieneRechazo = AsignacionDetalleRevisionVehiculo::where('idVehiculo', $vehiculo->id)
                    ->where('fechaRevision', $ultimaFecha)
                    ->where('estado', 'RECHAZADO')
                    ->exists();

                $tienePendiente = AsignacionDetalleRevisionVehiculo::where('idVehiculo', $vehiculo->id)
                    ->where('fechaRevision', $ultimaFecha)
                    ->where('estado', 'PORREVISION')
                    ->exists();

                return [
                    'id' => $vehiculo->id,
                    'placa' => $vehiculo->placa,
                    'marca' => $vehiculo->marca?->marca,
                    'modelo' => $vehiculo->modelo?->modelo,
                    'foto' => $vehiculo->foto,
                    'estado' => $vehiculo->estado?->estado,
                    'tieneRechazo' => $tieneRechazo,
                    'tienePendiente' => $tienePendiente,
                    'fechaUltimaRevision' => $ultimaFecha,
                    'numeroOrden' => $numeroAfiliacion,
                ];
            });

        return response()->json($vehiculos);
    }







    public function getDocumentsVehiculos($idVehiculo)
    {
        $afiliaciones = DocumentoVehiculo::where('idVehiculo', $idVehiculo)
            ->with('tipoDocumento', 'estado')
            ->get();


        return response()->json($afiliaciones);
    }


    public function updateDocumentoVehiculo(Request $request)
    {
        $idDocumento = $request->input('idDocumento');

        $documentoVehiculo = DocumentoVehiculo::find($idDocumento);

        if (!$documentoVehiculo) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        if ($request->hasFile('rutaFile')) {
            $path = $request->file('rutaFile')->store('public/documentos_vehiculo');


            $documentoVehiculo->ruta = Storage::url($path);
        } else {
            return response()->json(['error' => 'Error al almacenar el documento'], 500);
        }

        $documentoVehiculo->fechaCarga = now()->format('Y-m-d');

        if ($request->has('fecha_vigencia')) {
            $documentoVehiculo->fecha_vigencia = $request->input('fecha_vigencia');
        }

        $documentoVehiculo->save();

        return response()->json($documentoVehiculo);
    }



    public function getDocumentsPropietario($idPropietario)
    {
        $documentos = DocumentoPropietario::where('idPropietario', $idPropietario)
            ->with('tipoDocumento')
            ->get();

        return response()->json($documentos);
    }


    public function updateDocumentoPropietario(Request $request)
    {
        $idDocumento = $request->input('idDocumento');

        $documentoPropietario = DocumentoPropietario::find($idDocumento);

        if (!$documentoPropietario) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        if ($request->hasFile('rutaFile')) {
            $path = $request->file('rutaFile')->store('public/documentos_propietario');
            $documentoPropietario->ruta = Storage::url($path);
        } else {
            return response()->json(['error' => 'Error al almacenar el documento'], 500);
        }

        $documentoPropietario->fechaCarga = now()->format('Y-m-d');

        if ($request->has('fecha_vigencia')) {
            $documentoPropietario->fecha_vigencia = $request->input('fecha_vigencia');
        }

        $documentoPropietario->save();

        return response()->json($documentoPropietario);
    }




    public function getDocumentsConductor($idConductor)
    {
        $documentos = DocumentoConductor::where('idConductor', $idConductor)
            ->with('tipoDocumento')
            ->get();

        return response()->json($documentos);
    }

    public function getDocumentsAlert($idContrato = null)
    {
        if (!$idContrato) {
            $idConductor = KeyUtil::user()->idpersona;
        } else {
            $contrato = DB::table('contrato')
                ->where('id', $idContrato)
                ->first();

            if (!$contrato) {
                return response()->json(['error' => 'Contrato no encontrado'], 404);
            }

            $idConductor = $contrato->idpersona;
        }

        $documentos = DocumentoConductor::where('idConductor', $idConductor)
            ->with('tipoDocumento')
            ->get()
            ->map(function ($doc) {
                $hoy = now()->startOfDay();
                $fechaVigencia = \Carbon\Carbon::parse($doc->fecha_vigencia)->startOfDay();

                $diasRestantes = $hoy->diffInDays($fechaVigencia, false);

                if ($diasRestantes < 0) {
                    $estado = 'VENCIDO';
                    $mensaje = "El documento {$doc->tipoDocumento->nombre} ya venció";
                } elseif ($diasRestantes <= 15) {
                    $estado = 'POR VENCER';
                    $mensaje = "El documento {$doc->tipoDocumento->nombre} vence en {$diasRestantes} días";
                } else {
                    $estado = 'VIGENTE';
                    $mensaje = null;
                }

                $doc->fecha_actual = $hoy->toDateString();
                $doc->fecha_vigencia = $fechaVigencia->toDateString();
                $doc->dias_restantes = $diasRestantes;
                $doc->estado_vigencia = $estado;
                $doc->mensaje_alerta = $mensaje;

                return $doc;
            })
            ->filter(function ($doc) {
                return $doc->idTipoDocumento != 3 &&
                    ($doc->estado_vigencia === 'VENCIDO' || $doc->estado_vigencia === 'POR VENCER');
            })
            ->values();

        return response()->json($documentos);
    }



    public function getVehicleDocumentsAlert($idVehiculo = null)
    {
        if (!$idVehiculo) {
            return response()->json(['error' => 'Debe especificar un vehículo'], 400);
        }

        $vehiculo = DB::table('vehiculo')
            ->where('id', $idVehiculo)
            ->first();

        if (!$vehiculo) {
            return response()->json(['error' => 'Vehículo no encontrado'], 404);
        }

        $documentos = DocumentoVehiculo::where('idVehiculo', $idVehiculo)
            ->with('tipoDocumento')
            ->get()
            ->map(function ($doc) {
                $hoy = now()->startOfDay();
                $fechaVigencia = \Carbon\Carbon::parse($doc->fecha_vigencia)->startOfDay();

                $diasRestantes = $hoy->diffInDays($fechaVigencia, false);

                if ($diasRestantes < 0) {
                    $estado = 'VENCIDO';
                    $mensaje = "El documento {$doc->tipoDocumento->nombre} del vehículo ya venció.";
                } elseif ($diasRestantes <= 15) {
                    $estado = 'POR VENCER';
                    $mensaje = "El documento {$doc->tipoDocumento->nombre} del vehículo vence en {$diasRestantes} días.";
                } else {
                    $estado = 'VIGENTE';
                    $mensaje = null;
                }

                $doc->fecha_actual = $hoy->toDateString();
                $doc->fecha_vigencia = $fechaVigencia->toDateString();
                $doc->dias_restantes = $diasRestantes;
                $doc->estado_vigencia = $estado;
                $doc->mensaje_alerta = $mensaje;

                return $doc;
            })
            ->filter(function ($doc) {
                return $doc->estado_vigencia === 'VENCIDO' || $doc->estado_vigencia === 'POR VENCER';
            })
            ->values();

        return response()->json($documentos);
    }


    public function updateDocumentoConductor(Request $request)
    {
        $idDocumento = $request->input('idDocumento');

        $documentoConductor = DocumentoConductor::find($idDocumento);

        if (!$documentoConductor) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        if ($request->hasFile('rutaFile')) {

            $path = $request->file('rutaFile')->store('public/documentos_conductor');
            $documentoConductor->ruta = Storage::url($path);
        } else {
            return response()->json(['error' => 'Error al almacenar el documento'], 500);
        }


        $documentoConductor->fechaCarga = now()->format('Y-m-d');


        if ($request->has('fecha_vigencia')) {
            $documentoConductor->fecha_vigencia = $request->input('fecha_vigencia');
        }

        $documentoConductor->save();

        return response()->json($documentoConductor);
    }



    public function getHistorialByAfiliacion($idAfiliacion)
    {
        $afiliaciones = ObservacionAfiliacion::where('idAfiliacion', $idAfiliacion)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($afiliaciones->isEmpty()) {
            return response()->json(['mensaje' => 'No se encontraron afiliaciones'], 404);
        }

        return response()->json($afiliaciones);
    }




    public function storeObservacionAfiliacion(Request $request)
    {
        $idAfiliacion = $request->input('idAfiliacion');

        $observacionesAfiliacion = new ObservacionAfiliacion();
        $observacionesAfiliacion->idAfiliacion = $idAfiliacion;
        $observacionesAfiliacion->observacion = $request->input('observacion');

        if ($request->hasFile('rutaArchivoFile')) {
            $path = $request->file('rutaArchivoFile')->store('public/observacion_afiliacion');
            $observacionesAfiliacion->rutaUrl = Storage::url($path);
        } else {
            $observacionesAfiliacion->rutaUrl = null;
        }

        $observacionesAfiliacion->save();

        return response()->json($observacionesAfiliacion, 200);
    }



    public function changePropietarioAdministrado(Request $request)
    {
        $idAfiliacion = $request->input('idAfiliacion');
        $idPropietario = $request->input('idPropietario');


        $propietarios = AsignacionPropietario::where('idAfiliacion', $idAfiliacion)->get();

        foreach ($propietarios as $propietario) {

            if ($propietario->idPropietario == $idPropietario) {
                $propietario->administrador = 'Si';
            } else {
                $propietario->administrador = 'No';
            }


            $propietario->save();
        }

        return response()->json(['message' => 'Propietario actualizado correctamente']);
    }

    // public function getVehiculosByDriver()
    // {
    //     $idConductor = KeyUtil::user()->idpersona;

    //     $vehiculos = Vehiculo::whereHas('asignacionPropietario', function ($q) use ($idConductor) {
    //         $q->whereHas('afiliacion.conductor', function ($sub) use ($idConductor) {
    //             $sub->where('idConductor', $idConductor)
    //                 ->where('estado', 'ACTIVO');
    //         })
    //             ->where('estado', 'ACTIVO');
    //     })
    //         ->with([
    //             'marca',
    //             'modelo',
    //             'tipoVehiculo',
    //             'estado',
    //             'asignacionPropietarios.propietario',
    //             'asignacionPropietarios.afiliacion' => function ($q) {
    //                 $q->select('id', 'numero');
    //             },
    //         ])
    //         ->get();

    //     return response()->json($vehiculos);
    // }

    public function getVehiculosByDriver()
    {
        $idConductor = KeyUtil::user()->idpersona;

        $vehiculos = Vehiculo::whereHas('estado', function ($q) {
            $q->where('estado', 'ACTIVO');
        })
            ->whereHas('asignacionPropietario', function ($q) use ($idConductor) {
                $q->whereHas('afiliacion.conductor', function ($sub) use ($idConductor) {
                    $sub->where('idConductor', $idConductor)
                        ->where('estado', 'ACTIVO');
                })
                    ->where('estado', 'ACTIVO');
            })
            ->with([
                'marca',
                'modelo',
                'tipoVehiculo',
                'estado',
                'asignacionPropietarios.propietario',
                'asignacionPropietarios.afiliacion' => function ($q) {
                    $q->select('id', 'numero');
                },
            ])
            ->get();

        return response()->json($vehiculos);
    }


    public function getVehiculosByOwner()
    {
        $idPropietario = KeyUtil::user()->idpersona;

        $vehiculos = Vehiculo::whereHas('asignacionPropietario', function ($q) use ($idPropietario) {
            $q->where('idPropietario', $idPropietario)
                ->where('estado', 'ACTIVO');
        })
            ->with([
                'marca',
                'modelo',
                'tipoVehiculo',
                'estado',

                'asignacionPropietarios.propietario',

                'asignacionPropietarios.afiliacion' => function ($q) {
                    $q->select('id', 'numero');
                },

                'asignacionPropietarios.afiliacion.conductor',
            ])
            ->get();

        return response()->json($vehiculos);
    }






    public function getContratosVinculacion($idAfiliacion)
    {
        $contratos = ContratoVinculacion::where('idAfiliacion', $idAfiliacion)

            ->get();

        return response()->json($contratos);
    }


    public function storeContratoVinculacion(Request $request)
    {
        try {
            $contrato = new ContratoVinculacion();

            if ($request->hasFile('rutaFile')) {
                $path = $request->file('rutaFile')->store('public/documentos_vinculacion');
                $contrato->urlArchivo = Storage::url($path);
            } else {
                return response()->json(['error' => 'Error al almacenar el documento'], 500);
            }


            $contrato->observacion = $request->input('observacion');
            $contrato->fechaInicio = $request->input('fechaInicial');
            $contrato->fechaFinal = $request->input('fechaFinal');
            $contrato->numeroContrato = $request->input('numeroContrato');
            $contrato->idAfiliacion = $request->input('idVinculacion');
            $contrato->estado = 'ACEPTADO';
            $contrato->save();

            return response()->json([
                'message' => 'Contrato de vinculación creado correctamente',
                'data' => $contrato
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear contrato de vinculación',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getAfiliacionesPropietario()
    {
        $idPropietario = KeyUtil::user()->idpersona;

        $afiliaciones = Afiliacion::query()
            ->with([
                'tipoAfiliacion',
                'vehiculo' => function ($q) {
                    $q->select('vehiculo.id', 'vehiculo.placa');
                },
                'propietario' => function ($q) {
                    $q->select(
                        'persona.id',
                        'persona.nombre1',
                        'persona.nombre2',
                        'persona.apellido1',
                        'persona.apellido2',
                        'persona.rutaFoto'
                    )->wherePivot('administrador', 'Si');
                }
            ])
            ->whereHas('propietario', function ($q) use ($idPropietario) {
                $q->where('idPropietario', $idPropietario)
                    ->where('estado', 'ACTIVO');
            })
            ->get();

        return response()->json($afiliaciones);
    }

    public function storeInformacionPersonaNatural(Request $request)
    {
        $info = InformacionPersonaNatural::firstOrNew(['idPersona' => $request->idPersona]);

        $info->lugarExpedicion = $request->lugarExpedicion;
        $info->lugarNacimiento = $request->lugarNacimiento;
        $info->nacionalidad = $request->nacionalidad;
        $info->actividadPrincipal = $request->actividadPrincipal;
        $info->sector = $request->sector;
        $info->cargo = $request->cargo;
        $info->ciiu = $request->ciiu;
        $info->ocupacion = $request->ocupacion;
        $info->empresa = $request->empresa;
        $info->direccionOficina = $request->direccionOficina;
        $info->ciudadOficina = $request->ciudadOficina;
        $info->departamentoOficina = $request->departamentoOficina;
        $info->telefonoOficina = $request->telefonoOficina;
        $info->actividadSecundaria = $request->actividadSecundaria;
        $info->ciiuSecundario = $request->ciiuSecundario;
        $info->productoServicio = $request->productoServicio;
        $info->ingresos = $request->ingresos;
        $info->egresos = $request->egresos;
        $info->activos = $request->activos;
        $info->pasivos = $request->pasivos;
        $info->patrimonio = $request->patrimonio;
        $info->otrosIngresos = $request->otrosIngresos;
        $info->conceptoOtrosIngresos = $request->conceptoOtrosIngresos;
        $info->pep = $request->pep;
        $info->vinculoPep = $request->vinculoPep;
        $info->adminRecursos = $request->adminRecursos;
        $info->tributariasOtroPais = $request->tributariasOtroPais;
        $info->detalleTributarias = $request->detalleTributarias;

        $info->save();

        return response()->json([
            'message' => 'Información persona natural guardada correctamente',
            'data' => $info
        ], 201);
    }


    public function getInfoPersonaNatural($idPersona)
    {
        $info = InformacionPersonaNatural::where('idPersona', $idPersona)->first();

        if (!$info) {
            return response()->json(['error' => 'Información persona natural no encontrada'], 404);
        }

        return response()->json($info);
    }



    public function getReferenciasPersonales($idPersona)
    {
        $ref = ReferenciaPersonal::where('idPersona', $idPersona)->get();

        if (!$ref) {
            return response()->json(['error' => 'Información  no encontrada'], 404);
        }

        return response()->json($ref);
    }



    public function storeReferenciasPersonales(Request $request)
    {
        foreach ($request->referencias as $ref) {
            if (isset($ref['id']) && ReferenciaPersonal::find($ref['id'])) {

                ReferenciaPersonal::where('id', $ref['id'])->update([
                    'nombre' => $ref['nombre'],
                    'profesion' => $ref['profesion'],
                    'telefono' => $ref['telefono'],
                    'empresa' => $ref['empresa'],
                    'vehiculo' => $ref['vehiculo'],
                    'placa' => $ref['placa'],
                ]);
            } else {

                ReferenciaPersonal::create([
                    'idPersona' => $request->idPersona,
                    'nombre' => $ref['nombre'],
                    'profesion' => $ref['profesion'],
                    'telefono' => $ref['telefono'],
                    'empresa' => $ref['empresa'],
                    'vehiculo' => $ref['vehiculo'],
                    'placa' => $ref['placa'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Referencias personales guardadas correctamente'
        ], 201);
    }



    public function deleteReferenciaPersonal($id)
    {
        $ref = ReferenciaPersonal::findOrFail($id);
        $ref->delete();

        return response()->json([
            'message' => 'Referencia personal eliminada correctamente'
        ], 200);
    }



    public function generarFormularioPersonaNatural($id, $idVehiculo)
    {
        $persona = Person::with('tipoIdentificacion', 'personaNatural', 'referenciasPersonales', 'ciudadNac', 'ciudadUbicacion.departamento', 'asignacionPropietario.afiliacion')->findOrFail($id);

        $vehiculo = Vehiculo::with('modelo', 'marca', 'tipoVehiculo', 'claseVehiculo', 'documentosVehiculo.tipoDocumento')->findOrFail($idVehiculo);
        $tarjetaOperacion = $vehiculo->documentosVehiculo
            ->filter(function ($doc) {
                return $doc->tipoDocumento && $doc->tipoDocumento->tituloDocumento === 'TARJETA DE OPERACIÓN';
            })
            ->first();

        $pdf = new Fpdi();

        $file = storage_path('app/plantillas/FORMATOPERSONANATURAL.pdf');
        $pageCount = $pdf->setSourceFile($file);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplIdx = $pdf->importPage($pageNo);
            $pdf->AddPage();
            $pdf->useTemplate($tplIdx, 0, 0);


            if ($pageNo === 1) {
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(33, 71);
                $pdf->Write(8, $persona->apellido1 ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(84, 71);
                $pdf->Write(8, $persona->apellido2 ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(132, 71);
                $pdf->Write(8, trim(($persona->nombre1 ?? '') . ' ' . ($persona->nombre2 ?? '')));


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(32, 78.7);
                $pdf->Write(8, $persona->tipoIdentificacion->codigo ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 78);
                $pdf->Write(8, $persona->personaNatural->lugarExpedicion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(65, 78.7);
                $pdf->Write(8, $persona->identificacion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(34, 85.7);
                $pdf->Write(8, $persona->fechaNac ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(76,  85.7);
                $pdf->Write(8, $persona->ciudadNac->descripcion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(120, 85.7);
                $pdf->Write(8, $persona->personaNatural->nacionalidad ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(19, 93.3);
                $pdf->Write(8, $persona->email ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(86, 93.3);
                $pdf->Write(8, $persona->direccion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(152, 93.3);
                $pdf->Write(8, $persona->ciudadUbicacion->descripcion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(20, 100.9);
                $pdf->Write(8, $persona->ciudadUbicacion->departamento->descripcion ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(61, 100.9);
                $pdf->Write(8, $persona->telefono ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(94, 100.9);
                $pdf->Write(8, $persona->celular ?? '');



                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(141, 100.9);
                $pdf->Write(8, $persona->personaNatural->actividadPrincipal ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(20, 107.9);
                $pdf->Write(8, $persona->personaNatural->sector ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(79, 107.9);
                $pdf->Write(8, $persona->personaNatural->ciiu ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(25, 115.9);
                $pdf->Write(8, $persona->personaNatural->ocupacion ?? '');



                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(65, 115.9);
                $pdf->Write(8, $persona->personaNatural->cargo ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(133, 115.9);
                $pdf->Write(8, $persona->personaNatural->empresa ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(30, 123.2);
                $pdf->Write(8, $persona->personaNatural->direccionOficina ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(93, 123.2);
                $pdf->Write(8, $persona->personaNatural->ciudadOficina ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(161, 123.2);
                $pdf->Write(8, $persona->personaNatural->departamentoOficina ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(31, 131.2);
                $pdf->Write(8, $persona->personaNatural->telefonoOficina ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(83, 131.2);
                $pdf->Write(8, $persona->personaNatural->actividadSecundaria ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(177, 131.2);
                $pdf->Write(8, $persona->personaNatural->ciiuSecundario ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(92, 138.2);
                $pdf->Write(8, $persona->personaNatural->productoServicio ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 146);
                $ingresos = $persona->personaNatural->ingresos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 146);
                $ingresos = $persona->personaNatural->egresos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 154);
                $ingresos = $persona->personaNatural->activos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 154);
                $ingresos = $persona->personaNatural->pasivos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 161);
                $ingresos = $persona->personaNatural->patrimonio ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 161);
                $ingresos = $persona->personaNatural->otrosIngresos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 168.9);
                $pdf->Write(8, $persona->personaNatural->conceptoOtrosIngresos ?? '');


                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(0, 0, 0);

                if (($persona->personaNatural->pep ?? 'NO') === 'SI') {

                    $pdf->SetXY(87, 177.5);
                    $pdf->Write(8, 'X');
                } else {

                    $pdf->SetXY(97.8, 177.5);
                    $pdf->Write(8, 'X');
                }


                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(0, 0, 0);

                if (($persona->personaNatural->vinculoPep ?? 'NO') === 'SI') {

                    $pdf->SetXY(184.6, 177.5);
                    $pdf->Write(8, 'X');
                } else {

                    $pdf->SetXY(194.9, 177.5);
                    $pdf->Write(8, 'X');
                }


                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(0, 0, 0);

                if (($persona->personaNatural->adminRecursos ?? 'NO') === 'SI') {

                    $pdf->SetXY(87.5, 193.5);
                    $pdf->Write(8, 'X');
                } else {

                    $pdf->SetXY(97.8, 193.5);
                    $pdf->Write(8, 'X');
                }


                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(0, 0, 0);

                if (($persona->personaNatural->tributariasOtroPais ?? 'NO') === 'SI') {

                    $pdf->SetXY(114, 200.5);
                    $pdf->Write(8, 'X');
                } else {

                    $pdf->SetXY(124.5, 200.5);
                    $pdf->Write(8, 'X');
                }


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(146, 200.5);
                $pdf->Write(8, $persona->personaNatural->detalleTributarias ?? '');



                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 216);
                $pdf->Write(8, $vehiculo->placa ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 222);
                $pdf->Write(8, $vehiculo->marca->marca ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 228);
                $pdf->Write(8, $vehiculo->claseVehiculo->nombre ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 234);
                $pdf->Write(8, $vehiculo->tipoVehiculo->tipo ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 240);
                $pdf->Write(8, $vehiculo->numPuestos ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(158, 234);
                $pdf->Write(8, $tarjetaOperacion->numeroDocumento ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(170, 240);
                $pdf->Write(8, $tarjetaOperacion->fecha_vigencia ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 246);
                $pdf->Write(8, $vehiculo->motor ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 252);
                $pdf->Write(8, $vehiculo->modelo->modelo ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 258);
                $pdf->Write(8, $vehiculo->tipoCombustible ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 216);
                $pdf->Write(8, $vehiculo->chasis ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 222);
                $pdf->Write(8, $vehiculo->color ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 228);
                $pdf->Write(8, $vehiculo->numeroManifiestoAduana ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(181, 227.9);
                $pdf->Write(8, $vehiculo->origenManifiestoAduana ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 246);
                $pdf->Write(8, $vehiculo->radioAccion ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 252);
                $pdf->Write(8, $persona->asignacionPropietario->afiliacion->numero ?? '');
            }


            if ($pageNo === 2) {
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);

                $xInicio = 12;
                $yInicio = 16.5;
                $altoFila = 7;

                $y = $yInicio;
                $contador = 1;

                foreach ($persona->referenciasPersonales as $ref) {

                    $pdf->SetXY($xInicio, $y);
                    $pdf->Write(8, $contador);

                    $pdf->SetXY($xInicio + 5, $y);
                    $pdf->Write(8, $ref->nombre ?? '');

                    $pdf->SetXY($xInicio + 57, $y);
                    $pdf->Write(8, $ref->profesion ?? '');

                    $pdf->SetXY($xInicio + 77, $y);
                    $pdf->Write(8, $ref->telefono ?? '');

                    $pdf->SetXY($xInicio + 97, $y);
                    $pdf->Write(8, $ref->empresa ?? '');

                    $pdf->SetXY($xInicio + 156, $y);
                    $pdf->Write(8, $ref->vehiculo ?? '');

                    $pdf->SetXY($xInicio + 175, $y);
                    $pdf->Cell(20, $altoFila, $ref->placa ?? '', 0, 0, 'L');

                    $y += $altoFila;
                    $contador++;
                }
            }
        }

        return response($pdf->Output('S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="formulario_rellenado.pdf"');
    }



    public function generarFormularioPersonaJuridica($id, $idVehiculo)
    {
        $persona = Person::with('tipoIdentificacion', 'personaNatural', 'referenciasPersonales', 'ciudadNac', 'ciudadUbicacion.departamento', 'asignacionPropietario.afiliacion')->findOrFail($id);

        $vehiculo = Vehiculo::with('modelo', 'marca', 'tipoVehiculo', 'claseVehiculo', 'documentosVehiculo.tipoDocumento')->findOrFail($idVehiculo);
        $tarjetaOperacion = $vehiculo->documentosVehiculo
            ->filter(function ($doc) {
                return $doc->tipoDocumento && $doc->tipoDocumento->tituloDocumento === 'TARJETA DE OPERACIÓN';
            })
            ->first();

        $pdf = new Fpdi();

        $file = storage_path('app/plantillas/FORMATOPERSONANATURAL.pdf');
        $pageCount = $pdf->setSourceFile($file);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplIdx = $pdf->importPage($pageNo);
            $pdf->AddPage();
            $pdf->useTemplate($tplIdx, 0, 0);


            if ($pageNo === 1) {
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(33, 71);
                $pdf->Write(8, $persona->apellido1 ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(84, 71);
                $pdf->Write(8, $persona->apellido2 ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(132, 71);
                $pdf->Write(8, trim(($persona->nombre1 ?? '') . ' ' . ($persona->nombre2 ?? '')));


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(32, 78.7);
                $pdf->Write(8, $persona->tipoIdentificacion->codigo ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 78);
                $pdf->Write(8, $persona->personaNatural->lugarExpedicion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(65, 78.7);
                $pdf->Write(8, $persona->identificacion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(34, 85.7);
                $pdf->Write(8, $persona->fechaNac ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(76,  85.7);
                $pdf->Write(8, $persona->ciudadNac->descripcion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(120, 85.7);
                $pdf->Write(8, $persona->personaNatural->nacionalidad ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(19, 93.3);
                $pdf->Write(8, $persona->email ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(86, 93.3);
                $pdf->Write(8, $persona->direccion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(152, 93.3);
                $pdf->Write(8, $persona->ciudadUbicacion->descripcion ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(20, 100.9);
                $pdf->Write(8, $persona->ciudadUbicacion->departamento->descripcion ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(61, 100.9);
                $pdf->Write(8, $persona->telefono ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(94, 100.9);
                $pdf->Write(8, $persona->celular ?? '');



                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(141, 100.9);
                $pdf->Write(8, $persona->personaNatural->actividadPrincipal ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(20, 107.9);
                $pdf->Write(8, $persona->personaNatural->sector ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(79, 107.9);
                $pdf->Write(8, $persona->personaNatural->ciiu ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(25, 115.9);
                $pdf->Write(8, $persona->personaNatural->ocupacion ?? '');



                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(65, 115.9);
                $pdf->Write(8, $persona->personaNatural->cargo ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(133, 115.9);
                $pdf->Write(8, $persona->personaNatural->empresa ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(30, 123.2);
                $pdf->Write(8, $persona->personaNatural->direccionOficina ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(93, 123.2);
                $pdf->Write(8, $persona->personaNatural->ciudadOficina ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(161, 123.2);
                $pdf->Write(8, $persona->personaNatural->departamentoOficina ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(31, 131.2);
                $pdf->Write(8, $persona->personaNatural->telefonoOficina ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(83, 131.2);
                $pdf->Write(8, $persona->personaNatural->actividadSecundaria ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(177, 131.2);
                $pdf->Write(8, $persona->personaNatural->ciiuSecundario ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(92, 138.2);
                $pdf->Write(8, $persona->personaNatural->productoServicio ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 146);
                $ingresos = $persona->personaNatural->ingresos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 146);
                $ingresos = $persona->personaNatural->egresos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 154);
                $ingresos = $persona->personaNatural->activos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 154);
                $ingresos = $persona->personaNatural->pasivos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 161);
                $ingresos = $persona->personaNatural->patrimonio ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 161);
                $ingresos = $persona->personaNatural->otrosIngresos ?? 0;
                $ingresosFormateados = '$' . number_format($ingresos, 0, ',', '.');
                $pdf->Write(8, $ingresosFormateados);


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 168.9);
                $pdf->Write(8, $persona->personaNatural->conceptoOtrosIngresos ?? '');


                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(0, 0, 0);

                if (($persona->personaNatural->pep ?? 'NO') === 'SI') {

                    $pdf->SetXY(87, 177.5);
                    $pdf->Write(8, 'X');
                } else {

                    $pdf->SetXY(97.8, 177.5);
                    $pdf->Write(8, 'X');
                }


                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(0, 0, 0);

                if (($persona->personaNatural->vinculoPep ?? 'NO') === 'SI') {

                    $pdf->SetXY(184.6, 177.5);
                    $pdf->Write(8, 'X');
                } else {

                    $pdf->SetXY(194.9, 177.5);
                    $pdf->Write(8, 'X');
                }


                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(0, 0, 0);

                if (($persona->personaNatural->adminRecursos ?? 'NO') === 'SI') {

                    $pdf->SetXY(87.5, 193.5);
                    $pdf->Write(8, 'X');
                } else {

                    $pdf->SetXY(97.8, 193.5);
                    $pdf->Write(8, 'X');
                }


                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(0, 0, 0);

                if (($persona->personaNatural->tributariasOtroPais ?? 'NO') === 'SI') {

                    $pdf->SetXY(114, 200.5);
                    $pdf->Write(8, 'X');
                } else {

                    $pdf->SetXY(124.5, 200.5);
                    $pdf->Write(8, 'X');
                }


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(146, 200.5);
                $pdf->Write(8, $persona->personaNatural->detalleTributarias ?? '');



                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 216);
                $pdf->Write(8, $vehiculo->placa ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 222);
                $pdf->Write(8, $vehiculo->marca->marca ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 228);
                $pdf->Write(8, $vehiculo->claseVehiculo->nombre ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 234);
                $pdf->Write(8, $vehiculo->tipoVehiculo->tipo ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 240);
                $pdf->Write(8, $vehiculo->numPuestos ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(158, 234);
                $pdf->Write(8, $tarjetaOperacion->numeroDocumento ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(170, 240);
                $pdf->Write(8, $tarjetaOperacion->fecha_vigencia ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 246);
                $pdf->Write(8, $vehiculo->motor ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 252);
                $pdf->Write(8, $vehiculo->modelo->modelo ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(52, 258);
                $pdf->Write(8, $vehiculo->tipoCombustible ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 216);
                $pdf->Write(8, $vehiculo->chasis ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 222);
                $pdf->Write(8, $vehiculo->color ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 228);
                $pdf->Write(8, $vehiculo->numeroManifiestoAduana ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(181, 227.9);
                $pdf->Write(8, $vehiculo->origenManifiestoAduana ?? '');


                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 246);
                $pdf->Write(8, $vehiculo->radioAccion ?? '');

                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY(150, 252);
                $pdf->Write(8, $persona->asignacionPropietario->afiliacion->numero ?? '');
            }


            if ($pageNo === 2) {
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);

                $xInicio = 12;
                $yInicio = 16.5;
                $altoFila = 7;

                $y = $yInicio;
                $contador = 1;

                foreach ($persona->referenciasPersonales as $ref) {

                    $pdf->SetXY($xInicio, $y);
                    $pdf->Write(8, $contador);

                    $pdf->SetXY($xInicio + 5, $y);
                    $pdf->Write(8, $ref->nombre ?? '');

                    $pdf->SetXY($xInicio + 57, $y);
                    $pdf->Write(8, $ref->profesion ?? '');

                    $pdf->SetXY($xInicio + 77, $y);
                    $pdf->Write(8, $ref->telefono ?? '');

                    $pdf->SetXY($xInicio + 97, $y);
                    $pdf->Write(8, $ref->empresa ?? '');

                    $pdf->SetXY($xInicio + 156, $y);
                    $pdf->Write(8, $ref->vehiculo ?? '');

                    $pdf->SetXY($xInicio + 175, $y);
                    $pdf->Cell(20, $altoFila, $ref->placa ?? '', 0, 0, 'L');

                    $y += $altoFila;
                    $contador++;
                }
            }
        }

        return response($pdf->Output('S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="formulario_rellenado.pdf"');
    }



    public function getInfoPersonaJuridica($idPersona)
    {
        $info = InformacionPersonaJuridica::where('idPersona', $idPersona)->first();
        $empresa = InformacionEmpresaPersonaJuridica::where('idPersona', $idPersona)->first();

        if (!$info && !$empresa) {
            return response()->json(['error' => 'Información no encontrada'], 404);
        }

        return response()->json([
            'persona' => $info,
            'empresa' => $empresa
        ], 200);
    }


    public function storeInformacionJuridica(Request $request)
    {
        $info = InformacionPersonaJuridica::firstOrNew(['idPersona' => $request->idPersona]);

        $info->lugarExpedicionRep   = $request->lugarExpedicionRep;
        $info->nacionalidad1        = $request->nacionalidad1;
        $info->nacionalidad2        = $request->nacionalidad2;
        $info->pais                 = $request->pais;
        $info->pep                  = $request->pep;
        $info->adminRecursos        = $request->adminRecursos;
        $info->detalleTributarias   = $request->detalleTributarias;
        $info->tributariasOtroPais  = $request->tributariasOtroPais;
        $info->ingresos             = $request->ingresos;
        $info->egresos              = $request->egresos;
        $info->activos              = $request->activos;
        $info->pasivos              = $request->pasivos;
        $info->patrimonio           = $request->patrimonio;
        $info->otrosIngresos        = $request->otrosIngresos;
        $info->conceptoOtrosIngresos = $request->conceptoOtrosIngresos;
        $info->save();

        $empresa = InformacionEmpresaPersonaJuridica::firstOrNew(['idPersona' => $request->idPersona]);

        $empresa->razonSocial       = $request->razonSocial;
        $empresa->tipoDocumento     = $request->tipoDocumento;
        $empresa->nit               = $request->nit;
        $empresa->dv                = $request->dv;
        $empresa->oficinaPrincipal  = $request->oficinaPrincipal;
        $empresa->direccion         = $request->direccion;
        $empresa->tipoEmpresa       = $request->tipoEmpresa;
        $empresa->actividadEconomica = $request->actividadEconomica;
        $empresa->ciiu              = $request->ciiu;
        $empresa->departamento      = $request->departamento;
        $empresa->ciudad            = $request->ciudad;
        $empresa->telefono          = $request->telefono;
        $empresa->email             = $request->email;
        $empresa->save();

        return response()->json([
            'message' => 'Información jurídica guardada correctamente',
            'data' => [
                'persona' => $info,
                'empresa' => $empresa
            ]
        ], 201);
    }



    public function changeStatusVinculacionPendiente(Request $request)
    {
        $idVinculacion = $request->input('idVinculacion');
        $nuevoEstado = $request->input('estado');


        $vinculacion = Afiliacion::where('id', $idVinculacion)->first();

        if (!$vinculacion) {
            return response()->json(['error' => 'Vinculación no encontrada'], 404);
        }


        $vinculacion->estado = $nuevoEstado;
        $vinculacion->fechaAfiliacion = Carbon::now();
        $vinculacion->save();


        $afiliacionEstado = AfiliacionEstado::where('idAfiliacion', $idVinculacion)->first();
        if ($afiliacionEstado) {
            $afiliacionEstado->estado = $nuevoEstado;
            $afiliacionEstado->save();
        }

        $observacionesAfiliacion = new ObservacionAfiliacion();
        $observacionesAfiliacion->idAfiliacion = $idVinculacion;
        $observacionesAfiliacion->observacion = $request->input('observacion');

        if ($request->hasFile('rutaArchivoFile')) {
            $path = $request->file('rutaArchivoFile')->store('public/observacion_afiliacion');
            $observacionesAfiliacion->rutaUrl = Storage::url($path);
        } else {
            $observacionesAfiliacion->rutaUrl = null;
        }

        $observacionesAfiliacion->save();

        return response()->json([
            'message' => 'Estado de vinculación actualizado correctamente',
            'vinculacion' => $vinculacion,
            'afiliacionEstado' => $afiliacionEstado,
            'observacion' => $observacionesAfiliacion
        ], 200);
    }
}
