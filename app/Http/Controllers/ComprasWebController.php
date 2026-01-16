<?php

namespace App\Http\Controllers;

use App\Models\AsignacionCarritoProducto;
use App\Models\ShoppingCart;
use Illuminate\Http\Request;

class ComprasWebController extends Controller
{
    public function increaseQuantity(Request $request, $id)
    {
        $shopping = AsignacionCarritoProducto::findOrFail($id);
        $shopping->cantidad = $shopping->cantidad + 1;
        $shopping->save();
        return response()->json($shopping);
    }

    public function decreaseQuantity($id)
    {

        $shopping = AsignacionCarritoProducto::findOrFail($id);
        $shopping->cantidad = $shopping->cantidad - 1;

        $shopping->save();

        return response()->json($shopping);
    }


    public function destroyShoppingCart(int $id)
    {
        $asignacion = AsignacionCarritoProducto::findOrFail($id);

        $idShoppingCart = $asignacion->idShoppingCart;


        $asignacion->delete();

        $carrito = ShoppingCart::find($idShoppingCart);
        if ($carrito && $carrito->asignaciones()->count() === 0) {
            $carrito->delete();
        }

        return response()->json([], 204);
    }

}
