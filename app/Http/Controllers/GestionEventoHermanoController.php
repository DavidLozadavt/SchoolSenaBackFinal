<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hermano;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Item;
use App\Models\EjecucionItem;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GestionEventoHermanoController extends Controller
{
    // 🔹 LISTAR
    public function index()
    {
        return response()->json(
            Hermano::orderBy('nombre')->get()
        );
    }

    // 🔹 VER UNO
    public function show($id)
    {
        $hermano = Hermano::findOrFail($id);

        return response()->json($hermano);
    }

    // 🔹 CREAR
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:240',
            'celularContacto' => 'nullable|string|max:100',
            'edad' => 'nullable|integer',
            'nombreContactoF' => 'nullable|string|max:240',
            'celularContactoF' => 'nullable|string|max:240',
            'pago' => 'nullable|numeric',
            'saldo' => 'nullable|numeric',
            'formaPago' => 'nullable|string|max:100',
            'email' => 'nullable|string|max:100',
            'celularEmergencia' => 'nullable|string|max:100',
            'parentesco' => 'nullable|string|max:100',
            'observacion' => 'nullable|string',
        ]);

        $hermano = Hermano::create([
            ...$request->all(),
            'qr_token' => null // 🔥 se genera después
        ]);

        return response()->json([
            'message' => 'Hermano creado correctamente',
            'data' => $hermano
        ], 201);
    }

    // 🔹 ACTUALIZAR
    public function update(Request $request, $id)
    {
        $hermano = Hermano::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|string|max:240',
            'celularContacto' => 'nullable|string|max:100',
            'edad' => 'nullable|integer',
            'pago' => 'nullable|numeric',
            'saldo' => 'nullable|numeric',
        ]);

        $hermano->update($request->all());

        return response()->json([
            'message' => 'Hermano actualizado correctamente',
            'data' => $hermano
        ]);
    }

    // 🔹 ELIMINAR
    public function destroy($id)
    {
        $hermano = Hermano::findOrFail($id);

        // 🔥 SOLO elimina sus ejecuciones
        $hermano->ejecuciones()->delete();

        // 🔥 limpia pivote (solo de ese hermano)
        $hermano->items()->detach();

        $hermano->delete();

        return response()->json([
            'message' => 'Hermano eliminado correctamente'
        ]);
    }

    // 🔥 GENERAR QR TOKENS PARA TODOS
    public function generarQrs()
    {
        $hermanos = Hermano::whereNull('qr_token')->get();

        foreach ($hermanos as $hermano) {
            $hermano->qr_token = Str::uuid();
            $hermano->save();
        }

        return response()->json([
            'message' => 'QRs generados correctamente',
            'total' => $hermanos->count()
        ]);
    }

    // 🔥 GUARDAR IMAGEN QR DESDE FRONT
    public function guardarQrImagen(Request $request, $id)
    {
        $request->validate([
            'qr_image' => 'required|string' // base64
        ]);

        $hermano = Hermano::findOrFail($id);

        // 🔥 limpiar base64
        $image = $request->qr_image;
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = str_replace(' ', '+', $image);

        $imageName = 'qr_' . $hermano->id . '.png';

        Storage::disk('public')->put(
            'qrs/' . $imageName,
            base64_decode($image)
        );

        // opcional guardar ruta
        $hermano->qr_path = 'qrs/' . $imageName;
        $hermano->save();

        return response()->json([
            'message' => 'QR guardado correctamente',
            'path' => $hermano->qr_path
        ]);
    }

    public function getByToken($token)
    {
        $hermano = Hermano::where('qr_token', $token)->first();

        if (!$hermano) {
            return response()->json([
                'message' => 'Invitado no encontrado'
            ], 404);
        }

        return response()->json([
            'data' => $hermano
        ]);
    }

    public function getItemsByToken(Request $request, $token)
    {
        $hermano = Hermano::where('qr_token', $token)->first();

        if (!$hermano) {
            return response()->json([
                'message' => 'Invitado no encontrado'
            ], 404);
        }

        // 🔥 traer ejecuciones en 1 sola consulta
        $ejecuciones = EjecucionItem::where('idHermano', $hermano->id)
            ->get()
            ->keyBy('idItem');

        // 🔥 filtrar items por evento si se especifica
        $query = Item::query();
        if ($request->has('idEvento')) {
            $query->where('idEvento', $request->query('idEvento'));
        }

        $items = $query->get()->map(function ($item) use ($ejecuciones) {

            $ejecucion = $ejecuciones->get($item->id);

            return [
                'id' => $item->id,
                'nombreItem' => $item->nombreItem,
                'descripcion' => $item->descripcion,
                'hora_inicio' => $item->hora_inicio,
                'hora_fin' => $item->hora_fin,

                'recibido' => $ejecucion ? (bool) $ejecucion->recibido : false,
                'fecha_scan' => $ejecucion?->fecha_scan,
            ];
        });

        return response()->json([
            'hermano' => $hermano,
            'items' => $items
        ]);
    }

    public function toggleItem($token, $itemId)
    {
        $hermano = Hermano::where('qr_token', $token)->firstOrFail();

        $ejecucion = EjecucionItem::where('idHermano', $hermano->id)
            ->where('idItem', $itemId)
            ->first();

        if ($ejecucion) {

            // 🔁 Toggle estado
            $ejecucion->recibido = !$ejecucion->recibido;

            // 📌 fecha solo si está recibido
            $ejecucion->fecha_scan = $ejecucion->recibido ? now() : null;

            $ejecucion->save();

        } else {

            // 🆕 crear registro si no existe
            $ejecucion = EjecucionItem::create([
                'idHermano' => $hermano->id,
                'idItem' => $itemId,
                'recibido' => true,
                'fecha_scan' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'data' => $ejecucion
        ]);
    }

    public function autoClaimByToken(Request $request, $token)
    {
        $hermano = Hermano::where('qr_token', $token)->firstOrFail();

        $now = Carbon::now();

        $query = Item::query();

        // 🎯 Filtrar estrictamente por evento si se proporciona
        if ($request->has('idEvento') && $request->query('idEvento') !== '') {
            $query->where('idEvento', $request->query('idEvento'));
        }

        // ⏳ Ventana de tolerancia profesional:
        // - Permite check-in desde 20 minutos antes de iniciar.
        // - Permite check-in hasta 15 minutos después de finalizar.
        $items = $query->get()->filter(function ($item) use ($now) {
            if (!$item->hora_inicio) return false;
            
            $start = Carbon::parse($item->hora_inicio)->subMinutes(20);
            $end = $item->hora_fin 
                ? Carbon::parse($item->hora_fin)->addMinutes(15)
                : Carbon::parse($item->hora_inicio)->addHours(2);
                
            return $now->between($start, $end);
        });

        foreach ($items as $item) {
            // 🔒 Verificar si ya fue reclamado para no duplicar fechas de escaneo
            $yaReclamado = EjecucionItem::where('idHermano', $hermano->id)
                ->where('idItem', $item->id)
                ->where('recibido', true)
                ->exists();

            if ($yaReclamado) {
                continue;
            }

            EjecucionItem::updateOrCreate(
                [
                    'idHermano' => $hermano->id,
                    'idItem' => $item->id,
                ],
                [
                    'recibido' => true,
                    'fecha_scan' => $now,
                ]
            );
        }

        return response()->json([
            'message' => 'Items reclamados exitosamente en la ventana actual',
            'items' => $items->values()
        ]);
    }

    public function claimAllItemsByToken(Request $request, $token)
    {
        $hermano = Hermano::where('qr_token', $token)->firstOrFail();
        
        $query = Item::query();
        if ($request->has('idEvento') && $request->query('idEvento') !== '') {
            $query->where('idEvento', $request->query('idEvento'));
        }
        
        $items = $query->get();
        $now = Carbon::now();
        
        foreach ($items as $item) {
            EjecucionItem::updateOrCreate(
                [
                    'idHermano' => $hermano->id,
                    'idItem' => $item->id,
                ],
                [
                    'recibido' => true,
                    'fecha_scan' => $now,
                ]
            );
        }
        
        return response()->json([
            'message' => 'Asistencia completa registrada con éxito',
            'total' => $items->count()
        ]);
    }

    public function estadoPorItem($itemId)
    {
        $item = Item::findOrFail($itemId);

        // 🟢 quienes SÍ recibieron este item
        $recibieron = EjecucionItem::where('idItem', $itemId)
            ->where('recibido', true)
            ->with('hermano')
            ->get()
            ->map(function ($e) {
                return [
                    'id' => $e->hermano->id ?? null,
                    'nombre' => $e->hermano->nombre ?? null,
                    'fecha_scan' => $e->fecha_scan,
                ];
            });

        // 🔴 quienes NO recibieron este item
        $noRecibieron = Hermano::whereDoesntHave('ejecuciones', function ($q) use ($itemId) {
            $q->where('idItem', $itemId)
              ->where('recibido', true);
        })->get()
        ->map(function ($h) {
            return [
                'id' => $h->id,
                'nombre' => $h->nombre,
            ];
        });

        return response()->json([
            'item' => [
                'id' => $item->id,
                'nombreItem' => $item->nombreItem,
                'hora_inicio' => $item->hora_inicio,
                'hora_fin' => $item->hora_fin,
            ],

            'recibieron' => $recibieron,
            'no_recibieron' => $noRecibieron,

            'total_recibieron' => $recibieron->count(),
            'total_no_recibieron' => $noRecibieron->count(),
        ]);
    }

    public function abonar(Request $request, $id)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'formaPago' => 'required|string|max:100',
        ]);

        $hermano = Hermano::findOrFail($id);

        // 🟢 Inicializar si viene null
        $hermano->saldo = $hermano->saldo ?? 0;
        $hermano->pago = $hermano->pago ?? 0;

        // 🔥 aplicar abono correctamente
        $hermano->pago += $request->monto;
        $hermano->saldo -= $request->monto;

        // 🚨 evitar saldo negativo (opcional pero recomendado)
        if ($hermano->saldo < 0) {
            $hermano->saldo = 0;
        }

        // 🧾 guardar última forma de pago
        $hermano->formaPago = $request->formaPago;

        $hermano->save();

        return response()->json([
            'message' => 'Abono realizado correctamente',
            'data' => $hermano
        ]);
    }

    /**
     * Dashboard stats for the guest management module.
     */
    public function stats()
    {
        $hermanos = Hermano::all();
        $totalHermanos = $hermanos->count();
        $totalPago = $hermanos->sum('pago');
        $totalSaldo = $hermanos->sum('saldo');
        $conQr = $hermanos->whereNotNull('qr_token')->count();

        // Attendance: unique hermanos that have at least 1 recibido=true
        $asistentes = EjecucionItem::where('recibido', true)
            ->distinct('idHermano')
            ->count('idHermano');

        return response()->json([
            'total_invitados' => $totalHermanos,
            'total_recaudado' => round($totalPago, 2),
            'saldo_pendiente' => round($totalSaldo, 2),
            'con_qr' => $conQr,
            'sin_qr' => $totalHermanos - $conQr,
            'asistencia_confirmada' => $asistentes,
            'porcentaje_asistencia' => $totalHermanos > 0
                ? round(($asistentes / $totalHermanos) * 100, 1)
                : 0,
        ]);
    }

    /**
     * Export guest list + attendance as CSV.
     */
    public function exportCsv(Request $request)
    {
        $hermanos = Hermano::orderBy('nombre')->get();

        // Build items query
        $itemsQuery = Item::query();
        if ($request->has('idEvento') && $request->query('idEvento') !== '') {
            $itemsQuery->where('idEvento', $request->query('idEvento'));
        }
        $items = $itemsQuery->orderBy('hora_inicio')->get();

        $ejecuciones = EjecucionItem::where('recibido', true)->get();
        $ejecMap = [];
        foreach ($ejecuciones as $e) {
            $ejecMap[$e->idHermano][$e->idItem] = $e->fecha_scan;
        }

        $response = new StreamedResponse(function () use ($hermanos, $items, $ejecMap) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            // Header
            $header = ['Nombre', 'Email', 'Celular', 'Pago', 'Saldo', 'Forma Pago', 'QR'];
            foreach ($items as $item) {
                $header[] = $item->nombreItem;
            }
            fputcsv($handle, $header, ';');

            // Rows
            foreach ($hermanos as $h) {
                $row = [
                    $h->nombre,
                    $h->email ?? '',
                    $h->celularContacto ?? '',
                    $h->pago ?? 0,
                    $h->saldo ?? 0,
                    $h->formaPago ?? '',
                    $h->qr_token ? 'Sí' : 'No',
                ];
                foreach ($items as $item) {
                    $row[] = isset($ejecMap[$h->id][$item->id]) ? '✓' : '—';
                }
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        });

        $filename = 'invitados_' . date('Y-m-d_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$filename\"");

        return $response;
    }

    /**
     * Historial completo de escaneos QR.
     * Devuelve todos los hermanos con sus items escaneados y pendientes.
     */
    public function historialScan(Request $request)
    {
        $query = Item::query();
        if ($request->has('idEvento') && $request->query('idEvento') !== '') {
            $query->where('idEvento', $request->query('idEvento'));
        }
        $items = $query->orderBy('hora_inicio')->get();
        $itemIds = $items->pluck('id');

        $hermanos = Hermano::whereNotNull('qr_token')->orderBy('nombre')->get();

        $ejecuciones = EjecucionItem::whereIn('idItem', $itemIds)->get();
        $ejecMap = [];
        foreach ($ejecuciones as $e) {
            $ejecMap[$e->idHermano][$e->idItem] = $e;
        }

        $resultado = [];

        foreach ($hermanos as $h) {
            $escaneados = [];
            $pendientes = [];

            foreach ($items as $item) {
                $ejec = $ejecMap[$h->id][$item->id] ?? null;

                $itemData = [
                    'idItem'      => $item->id,
                    'nombreItem'  => $item->nombreItem,
                    'hora_inicio' => $item->hora_inicio,
                    'hora_fin'    => $item->hora_fin,
                ];

                if ($ejec && $ejec->recibido) {
                    $itemData['fecha_scan'] = $ejec->fecha_scan;
                    $escaneados[] = $itemData;
                } else {
                    $pendientes[] = $itemData;
                }
            }

            $resultado[] = [
                'id'        => $h->id,
                'nombre'    => $h->nombre,
                'email'     => $h->email,
                'celular'   => $h->celularContacto,
                'qr_token'  => $h->qr_token,
                'escaneados'   => $escaneados,
                'pendientes'   => $pendientes,
                'total_escaneados' => count($escaneados),
                'total_pendientes' => count($pendientes),
                'total_items'      => count($escaneados) + count($pendientes),
            ];
        }

        // Ordenar: los que tienen escaneos recientes primero
        usort($resultado, function ($a, $b) {
            $lastA = !empty($a['escaneados']) ? $a['escaneados'][count($a['escaneados'])-1]['fecha_scan'] : null;
            $lastB = !empty($b['escaneados']) ? $b['escaneados'][count($b['escaneados'])-1]['fecha_scan'] : null;
            if (!$lastA && !$lastB) return 0;
            if (!$lastA) return 1;
            if (!$lastB) return -1;
            return strcmp($lastB, $lastA);
        });

        return response()->json([
            'data'  => $resultado,
            'total' => count($resultado),
            'total_items' => $items->count(),
        ]);
    }
}
