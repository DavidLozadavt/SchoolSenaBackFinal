<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hermano;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Item;
use App\Models\EjecucionItem;
use Carbon\Carbon;

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

    public function autoClaimByToken($token)
    {
        $hermano = Hermano::where('qr_token', $token)->firstOrFail();

        $now = Carbon::now();

        // Items en ventana actual
        $items = Item::where('hora_inicio', '<=', $now)
            ->where('hora_fin', '>=', $now)
            ->get();

        foreach ($items as $item) {

            // 🔒 verificar si YA fue reclamado en esta ventana
            $yaReclamado = EjecucionItem::where('idHermano', $hermano->id)
                ->where('idItem', $item->id)
                ->where('recibido', true)
                ->whereBetween('fecha_scan', [$item->hora_inicio, $item->hora_fin])
                ->exists();

            if ($yaReclamado) {
                continue; // ❌ ya fue reclamado en esta ventana
            }

            // 🟢 crear o actualizar
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
            'message' => 'Items reclamados sin duplicar por ventana horaria',
            'items' => $items
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
}
