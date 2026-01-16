<?php

namespace App\Http\Controllers\gestion_transporte;

use Illuminate\Http\Request;
use App\Models\Transporte\Ticket;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Jobs\CreateInvoiceJob;
use App\Models\AsignacionFacturaTransaccion;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Ruta;
use App\Models\TipoFactura;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use App\Models\Transporte\AgendarViaje;
use App\Util\KeyUtil;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;



class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $tickets = Ticket::orderBy("created_at", "desc")->get();
        return response()->json($tickets);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */


    public function store(Request $request): JsonResponse
    {
        $ticketsData = $request->input('tickets', []);

        if (empty($ticketsData)) {
            return response()->json(['message' => 'No se enviaron tickets.'], 400);
        }

        $ticketsCreados = [];

        [$esPagoGlobal, $pagosGlobales, $ticketsData] = $this->detectarPagosGlobales($request, $ticketsData);

        $esVentaMultiple = count($ticketsData) > 1;

        DB::beginTransaction();

        try {
            $valorTotalGlobal = $this->calcularValorTotalGlobal($ticketsData);

            if ($esPagoGlobal) {
                $this->validarPagosGlobales($pagosGlobales, $valorTotalGlobal);
            }

            $transaccionUnica = null;
            $facturaUnica = null;

            foreach ($ticketsData as $index => $data) {
                $ruta = Ruta::find($data['idRuta']);
                $cantidad = $data['cantidad'] ?? 1;
                $valorTotal = ($ruta->precio ?? 0) * $cantidad;

                $ticket = $this->crearTicket($data, $cantidad, $valorTotal);
                $ticketsCreados[] = $ticket;

                if ($esVentaMultiple || $esPagoGlobal) {
                    if ($index === 0) {
                        $facturaUnica = $this->crearFactura($valorTotalGlobal, $data['idTercero'] ?? null);
                    }
                    $this->agregarDetalleFactura($facturaUnica, $ticket, $valorTotal, $cantidad);
                } else {
                    $factura = $this->crearFactura($valorTotal, $data['idTercero'] ?? null);
                    $this->agregarDetalleFactura($factura, $ticket, $valorTotal, $cantidad);
                    $facturaUnica = $factura;
                }

                $esPagoMixtoIndividual = $this->esPagoMixtoIndividual($data);
                if ($esPagoMixtoIndividual && !$esPagoGlobal && !$esVentaMultiple) {
                    $this->validarPagosMixtosIndividuales($data['pagos'], $valorTotal, $ticket->numeroTicket);
                }

                if ($esVentaMultiple || $esPagoGlobal) {
                    if ($index === 0) {
                        $transaccionUnica = $this->crearTransaccionConsolidada(
                            $facturaUnica,
                            $valorTotalGlobal,
                            $data,
                            $esPagoGlobal
                        );

                        if ($esPagoGlobal) {
                            $this->crearPagosGlobales($pagosGlobales, $facturaUnica, $transaccionUnica);
                        } else {
                            $this->crearPagoSimple($valorTotalGlobal, $facturaUnica, $transaccionUnica, $data['idMedioPago']);
                        }

                        $this->asociarFacturaConTransaccion($facturaUnica, $transaccionUnica);
                    }
                } else {
                    $transaccion = $this->crearTransaccionIndividual(
                        $facturaUnica,
                        $valorTotal,
                        $data,
                        $esPagoMixtoIndividual
                    );

                    if ($esPagoMixtoIndividual) {
                        $this->crearPagosMixtos($data['pagos'], $facturaUnica, $transaccion);
                    } else {
                        $this->crearPagoSimple($valorTotal, $facturaUnica, $transaccion, $data['idMedioPago']);
                    }

                    $this->asociarFacturaConTransaccion($facturaUnica, $transaccion);
                }

                $this->procesarReserva($data);
            }

            DB::commit();

            $this->despacharFacturacionElectronica($ticketsCreados);

            return response()->json([
                'success'   => true,
                'tickets'   => $ticketsCreados,
                'ticketIds' => collect($ticketsCreados)->pluck('id')->toArray(),
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear los tickets.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detecta si hay pagos globales o si los pagos están duplicados
     */
    private function detectarPagosGlobales(Request $request, array $ticketsData): array
    {
        $pagosGlobales = $request->input('pagos', null);
        $esPagoGlobal = !empty($pagosGlobales) && is_array($pagosGlobales);

        if (!$esPagoGlobal && count($ticketsData) > 1) {
            $primerTicketPagos = $ticketsData[0]['pagos'] ?? null;
            
            if (!empty($primerTicketPagos) && is_array($primerTicketPagos)) {
                $pagosIguales = true;
                
                foreach ($ticketsData as $ticket) {
                    $pagosActuales = $ticket['pagos'] ?? null;
                    if (json_encode($pagosActuales) !== json_encode($primerTicketPagos)) {
                        $pagosIguales = false;
                        break;
                    }
                }
                
                if ($pagosIguales) {
                    $pagosGlobales = $primerTicketPagos;
                    $esPagoGlobal = true;
                    
                    foreach ($ticketsData as &$ticket) {
                        unset($ticket['pagos']);
                    }
                    unset($ticket);
                }
            }
        }

        return [$esPagoGlobal, $pagosGlobales, $ticketsData];
    }

    /**
     * Calcula el valor total de todos los tickets
     */
    private function calcularValorTotalGlobal(array $ticketsData): float
    {
        $total = 0;
        foreach ($ticketsData as $data) {
            $ruta = Ruta::find($data['idRuta']);
            $cantidad = $data['cantidad'] ?? 1;
            $total += ($ruta->precio ?? 0) * $cantidad;
        }
        return $total;
    }

    /**
     * Valida que la suma de pagos globales coincida con el valor total
     */
    private function validarPagosGlobales(array $pagosGlobales, float $valorTotal): void
    {
        $sumaPagos = collect($pagosGlobales)->sum('valor');
        if (abs($sumaPagos - $valorTotal) > 0.01) {
            throw new \Exception(
                "La suma de los pagos ({$sumaPagos}) no coincide con el valor total de todos los tickets ({$valorTotal})"
            );
        }
    }

    /**
     * Crea un ticket con sus datos
     */
    private function crearTicket(array $data, int $cantidad, float $valorTotal): Ticket
    {
        $agendamiento = AgendarViaje::where('idViaje', $data['idViaje'])
            ->latest('id')
            ->first();

        $estado = ($agendamiento && $agendamiento->fecha === now()->toDateString())
            ? 'VENDIDO'
            : 'PORDESPACHAR';

        return Ticket::create([
            'idViaje'                 => $data['idViaje'],
            'idTercero'               => $data['idTercero'] ?? null,
            'idConfiguracionVehiculo' => $data['idConfiguracionVehiculo'] ?? null,
            'idAgendaViaje'           => $agendamiento->id ?? ($data['idAgendaViaje'] ?? null),
            'idRuta'                  => $data['idRuta'],
            'cantidad'                => $cantidad,
            'estado'                  => $estado,
            'idCaja'                  => $data['idCaja'],
            'idMedioPago'             => $data['idMedioPago'] ?? null,
            'idTipoPago'              => $data['idTipoPago'],
            'numeroTicket'            => $this->generarNumeroConsecutivo(),
            'valor'                   => $valorTotal,
            'idEmpresa'               => KeyUtil::idCompany(),
        ]);
    }

    /**
     * Crea una factura
     */
    private function crearFactura(float $valor, ?int $idTercero): Factura
    {
        $nextNumFactura = $this->generarNumeroFactura();

        return Factura::create([
            'numeroFactura' => $nextNumFactura,
            'fecha'         => today(),
            'valor'         => $valor,
            'valorIva'      => 0,
            'valorMasIva'   => $valor,
            'idCompany'     => KeyUtil::idCompany(),
            'idTercero'     => $idTercero,
            'idTipoFactura' => 1,
            'idUser'        => KeyUtil::user()->id,
        ]);
    }

    /**
     * Genera el siguiente número de factura
     */
    private function generarNumeroFactura(): string
    {
        $lastFactura = Factura::where('idTipoFactura', TipoFactura::VENTA)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastFactura) {
            return str_pad(intval($lastFactura->numeroFactura) + 1, 5, '0', STR_PAD_LEFT);
        }
        
        return '00001';
    }

    /**
     * Agrega un detalle a la factura
     */
    private function agregarDetalleFactura(Factura $factura, Ticket $ticket, float $valor, int $cantidad): void
    {
        $factura->detalles()->create([
            'valor'     => $valor,
            'cantidad'  => $cantidad,
            'idTicket'  => $ticket->id,
            'detalle'   => "Venta de ticket #{$ticket->numeroTicket}" . ($cantidad > 1 ? " (x{$cantidad})" : ""),
        ]);
    }

    /**
     * Determina si es pago mixto individual
     */
    private function esPagoMixtoIndividual(array $data): bool
    {
        return isset($data['pagos']) && is_array($data['pagos']) && count($data['pagos']) > 1;
    }

    /**
     * Valida que la suma de pagos mixtos individuales sea correcta
     */
    private function validarPagosMixtosIndividuales(array $pagos, float $valorTotal, string $numeroTicket): void
    {
        $sumaPagos = collect($pagos)->sum('valor');
        
        if (abs($sumaPagos - $valorTotal) > 0.01) {
            throw new \Exception(
                "La suma de los pagos ({$sumaPagos}) no coincide con el valor total ({$valorTotal}) para el ticket #{$numeroTicket}"
            );
        }
    }

    /**
     * Crea una transacción consolidada para múltiples tickets o pagos globales
     */
    private function crearTransaccionConsolidada(Factura $factura, float $valor, array $data, bool $esPagoMixto): Transaccion
    {
        return Transaccion::create([
            'fechaTransaccion'  => today(),
            'hora'              => now()->format('H:i:s'),
            'numFacturaInicial' => $factura->numeroFactura,
            'valor'             => $valor,
            'idEstado'          => 1,
            'idTipoTransaccion' => TipoTransaccion::VENTA,
            'idTipoPago'        => $esPagoMixto ? null : $data['idTipoPago'],
            'idCaja'            => $data['idCaja'],
            'excedente'         => 0
        ]);
    }

    /**
     * Crea una transacción global para múltiples tickets (DEPRECATED - usar crearTransaccionConsolidada)
     */
    private function crearTransaccionGlobal(Factura $factura, float $valor, int $idCaja): Transaccion
    {
        return Transaccion::create([
            'fechaTransaccion'  => today(),
            'hora'              => now()->format('H:i:s'),
            'numFacturaInicial' => $factura->numeroFactura,
            'valor'             => $valor,
            'idEstado'          => 1,
            'idTipoTransaccion' => TipoTransaccion::VENTA,
            'idTipoPago'        => null,
            'idCaja'            => $idCaja,
            'excedente'         => 0
        ]);
    }

    /**
     * Crea una transacción individual para un ticket
     */
    private function crearTransaccionIndividual(Factura $factura, float $valor, array $data, bool $esPagoMixto): Transaccion
    {
        return Transaccion::create([
            'fechaTransaccion'  => today(),
            'hora'              => now()->format('H:i:s'),
            'numFacturaInicial' => $factura->numeroFactura,
            'valor'             => $valor,
            'idEstado'          => 1,
            'idTipoTransaccion' => TipoTransaccion::VENTA,
            'idTipoPago'        => $esPagoMixto ? null : $data['idTipoPago'],
            'idCaja'            => $data['idCaja'],
            'excedente'         => 0
        ]);
    }

    /**
     * Crea pagos globales
     */
    private function crearPagosGlobales(array $pagosGlobales, Factura $factura, Transaccion $transaccion): void
    {
        foreach ($pagosGlobales as $pagoData) {
            if (!isset($pagoData['valor']) || !isset($pagoData['idMedioPago'])) {
                throw new \Exception('Cada pago debe tener "valor" e "idMedioPago"');
            }

            Pago::create([
                'fechaPago'     => today(),
                'fechaReg'      => today(),
                'valor'         => $pagoData['valor'],
                'numeroFact'    => $factura->numeroFactura,
                'idEstado'      => 5,
                'idTransaccion' => $transaccion->id,
                'idMedioPago'   => $pagoData['idMedioPago'],
            ]);
        }
    }

    /**
     * Crea pagos mixtos para un ticket
     */
    private function crearPagosMixtos(array $pagos, Factura $factura, Transaccion $transaccion): void
    {
        foreach ($pagos as $pagoData) {
            if (!isset($pagoData['valor']) || !isset($pagoData['idMedioPago'])) {
                throw new \Exception('Cada pago debe tener "valor" e "idMedioPago"');
            }

            Pago::create([
                'fechaPago'     => today(),
                'fechaReg'      => today(),
                'valor'         => $pagoData['valor'],
                'numeroFact'    => $factura->numeroFactura,
                'idEstado'      => 5,
                'idTransaccion' => $transaccion->id,
                'idMedioPago'   => $pagoData['idMedioPago'],
            ]);
        }
    }

    /**
     * Crea un pago simple
     */
    private function crearPagoSimple(float $valor, Factura $factura, Transaccion $transaccion, int $idMedioPago): void
    {
        Pago::create([
            'fechaPago'     => today(),
            'fechaReg'      => today(),
            'valor'         => $valor,
            'numeroFact'    => $factura->numeroFactura,
            'idEstado'      => 5,
            'idTransaccion' => $transaccion->id,
            'idMedioPago'   => $idMedioPago,
        ]);
    }

    /**
     * Asocia una factura con una transacción
     */
    private function asociarFacturaConTransaccion(Factura $factura, Transaccion $transaccion): void
    {
        AsignacionFacturaTransaccion::create([
            'idFactura'     => $factura->id,
            'idTransaccion' => $transaccion->id,
        ]);
    }

    /**
     * Procesa una reserva si existe código de reserva
     */
    private function procesarReserva(array $data): void
    {
        if (empty($data['codigoReserva'])) {
            return;
        }

        $reserva = DB::table('reservaViajes')
            ->where('codigo', $data['codigoReserva'])
            ->lockForUpdate()
            ->first();

        if ($reserva) {
            $nuevaCantidad = max(0, $reserva->cantidad - ($data['cantidad'] ?? 1));

            DB::table('reservaViajes')
                ->where('id', $reserva->id)
                ->update([
                    'cantidad' => $nuevaCantidad,
                    'estado' => $nuevaCantidad <= 0 ? 'REDIMIDO' : $reserva->estado,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Despacha los jobs de facturación electrónica
     */
    private function despacharFacturacionElectronica(array $tickets): void
    {
        try {
            Bus::batch(
                collect($tickets)->map(fn($ticket) => new CreateInvoiceJob($ticket))
            )
                ->onQueue('facturacion_' . KeyUtil::idCompany())
                ->name('Batch Facturación Empresa ' . KeyUtil::idCompany())
                ->then(function (Batch $batch) {})
                ->catch(function (Batch $batch, Throwable $e) {})
                ->finally(function (Batch $batch) {
                    Log::info("🧾 Batch finalizado (ID: {$batch->id})");
                })
                ->dispatch();
        } catch (Throwable $e) {
            Log::error('Error despachando facturación electrónica: ' . $e->getMessage());
        }
    }

    /**
     * 🔢 Genera un número consecutivo para el ticket
     */
    private function generarNumeroConsecutivo(): string
    {
        $ultimo = Ticket::max('numeroTicket');

        if (!$ultimo) {
            return str_pad(1, 7, '0', STR_PAD_LEFT);
        }

        $nuevoNumero = intval($ultimo) + 1;
        return str_pad($nuevoNumero, 7, '0', STR_PAD_LEFT);
    }


    /**
     * Display the specified resource.
     *
     * @param  Ticket  $ticket
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        return response()->json($ticket);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Ticket  $ticket
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->update($request->all());
        return response()->json($ticket, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Ticket  $ticket
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->delete();
        return response()->json(null, 204);
    }
}
