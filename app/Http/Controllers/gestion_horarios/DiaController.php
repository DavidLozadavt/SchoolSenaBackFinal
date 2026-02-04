<?php

namespace App\Http\Controllers\gestion_horarios;

use App\Http\Controllers\Controller;
use App\Models\Dia;
use Illuminate\Http\JsonResponse;

class DiaController extends Controller
{
  /**
   * Get days
   *
   * @return \Illuminate\Http\Response
   */
  public function index(): JsonResponse
  {
    $days = Dia::all();
    return response()->json($days, 200);
  }
}
