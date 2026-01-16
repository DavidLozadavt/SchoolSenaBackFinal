<?php
namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use App\Models\Caja;
use Illuminate\Http\Request;

class PuntoVentaController extends Controller
{
    /**
     * Display a listing of the resource with the latest cajas.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $pVenta = PuntoVenta::all()
            ->load('sede');
        return response()->json($pVenta);
    }


    public function getPointSalesAndCajas()
    {
        $puntosVenta = PuntoVenta::with([
            'cajas' => function ($query) {
                $query->whereRaw('caja.fecha = (
                SELECT MAX(fecha)
                FROM caja AS subcaja
                WHERE subcaja.idPuntoDeVenta = caja.idPuntoDeVenta
            )')
                    ->with(['usuario.persona', 'estado']);
            }
        ])->get();

        return response()->json($puntosVenta);
    }
    public function store(Request $request)
    {
        $data = $request->all();
        $sede = new PuntoVenta($data);
        $sede->savePuntoVentaImage($request);
        $sede->save();

        return response()->json($sede, 201);
    }

    public function show($id)
    {
        return PuntoVenta::findOrFail($id);
    }


    public function updatePuntoVenta(Request $request, $id)
    {
        $pVenta = PuntoVenta::findOrFail($id);
        if ($request->hasFile('imagenUrl')) {
            $imageUrl = $pVenta->savePuntoVentaImage($request);
            $pVenta->imagenUrl = $imageUrl;
        }
        $pVenta->fill($request->except('imagenUrl'));
        $pVenta->save();
        return response()->json($pVenta, 200);
    }


    public function destroy($id)
    {
        PuntoVenta::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
    /**
     * Display the latest caja for the specified Punto de Venta.
     *
     * @param int $idPuntoDeVenta
     * @return \Illuminate\Http\Response
     */
    public function getLatestCajaByPuntoDeVenta($idPuntoDeVenta)
    {        $latestFecha = Caja::where('idPuntoDeVenta', $idPuntoDeVenta)
                            ->max('fecha');
        $caja = Caja::where('idPuntoDeVenta', $idPuntoDeVenta)
                    ->where('fecha', $latestFecha)
                    ->with(['usuario.persona', 'estado'])
                    ->first();

        return response()->json($caja);
    }


    public function getPointSalesBySede($idSede)
    {
        $puntosVenta = PuntoVenta::where('idSede', $idSede)
          ->where('tipo', 'Ventanilla')
            ->with(['cajas' => function ($query) {
                $query->whereRaw('caja.fecha = (
                    SELECT MAX(fecha)
                    FROM caja AS subcaja
                    WHERE subcaja.idPuntoDeVenta = caja.idPuntoDeVenta
                )')
                ->with(['usuario.persona', 'estado']);
            }])->get();

        return response()->json($puntosVenta);
    }

    public function getPointSalesBySedeAndTypeShop($idSede)
    {
        $puntosVenta = PuntoVenta::where('idSede', $idSede)
            ->where('tipo', 'Tienda')
            ->with(['cajas' => function ($query) {
                $query->whereRaw('caja.fecha = (
                    SELECT MAX(fecha)
                    FROM caja AS subcaja
                    WHERE subcaja.idPuntoDeVenta = caja.idPuntoDeVenta
                )')
                ->with(['usuario.persona', 'estado']);
            }])->get();

        return response()->json($puntosVenta);
    }


    public function getCajasByPointSale($idPuntoVenta)
    {
        $query = Caja::where('idPuntoDeVenta', $idPuntoVenta)
            ->with(['usuario.persona', 'estado']);

        if (request()->has('idUsuario')) {
            $query->where('idUsuario', request()->input('idUsuario'));
        }

        $cajas = $query->get();

        return response()->json($cajas);
    }



}
