<?php

namespace App\Http\Controllers\gestion_reportes;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;

class ReportesController extends Controller
{
    public function getReportMonthTasks(Request $request)
    {
        $idContrato = $request->input('idContrato');
        $fecha = $request->input('fecha');


        $fechaCarbon = \Carbon\Carbon::parse($fecha);
        $mes = $fechaCarbon->month;
        $anio = $fechaCarbon->year;

        $cards = Contract::where('id', $idContrato)
            ->with(['persona.usuario.cards.cardDetails' => function ($query) use ($mes, $anio) {
                $query->whereMonth('fechaInicial', $mes)->whereYear('fechaInicial', $anio);
            }])
            ->get()
            ->pluck('persona.usuario.cards')
            ->flatten()
            ->filter(function ($card) {

                return $card->cardDetails && $card->cardDetails->isNotEmpty();
            });


        $checkItems = Contract::where('id', $idContrato)
            ->with(['persona.usuario.chekItems.checkItemDetail' => function ($query) use ($mes, $anio) {
                $query->whereMonth('fechaFinal', $mes)->whereYear('fechaFinal', $anio);
            }])
            ->get()
            ->pluck('persona.usuario.chekItems')
            ->flatten()
            ->filter(function ($checkItem) {

                return $checkItem->checkItemDetail !== null;
            });

        return response()->json([
            'cards_count' => $cards->count(),
            'check_items_count' => $checkItems->count(),
            'cards' => $cards,
            'check_items' => $checkItems,
        ]);
    }
}
