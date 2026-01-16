<?php

namespace App\Console\Commands;

use App\Models\AsignacionCardUser;
use App\Models\Card;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RepeatCard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repeat:card';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea una copia de una tarjeta segun la configuración';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cards = Card::with('cardDetails.configuracionRepeat', 'members', 'files', 'checkList.items')
                     ->whereHas('cardDetails.configuracionRepeat')
                     ->get();
    
        foreach ($cards as $card) {
            foreach ($card->cardDetails as $detail) {
                if ($detail->configuracionRepeat) {
                    $valueInMinutes = $detail->configuracionRepeat->value;
                    $fechaFinal = Carbon::parse($detail->fechaFinal . ' ' . $detail->hora);
                    $fechaObjetivo = $fechaFinal->copy()->addMinutes($valueInMinutes);
    
                    if (now()->format('Y-m-d H:i') === $fechaObjetivo->format('Y-m-d H:i')) {
                        // Replicar la Card
                        $nuevaCard = $card->replicate();
                        $nuevaCard->created_at = now();
                        $nuevaCard->updated_at = now();
                        $nuevaCard->save();
    
                        // Replicar los detalles de la Card
                        $nuevoDetalle = $detail->replicate();
                        $nuevoDetalle->idCard = $nuevaCard->id; 
                        $nuevoDetalle->fechaInicial = $detail->fechaFinal; // Utilizar la fechaFinal existente
                        $nuevoDetalle->fechaFinal = $fechaObjetivo->format('Y-m-d');
                        $nuevoDetalle->hora = $fechaObjetivo->format('H:i:s');
                        $nuevoDetalle->completado = 0;
                        $nuevoDetalle->estado = null;
                        $nuevoDetalle->fechaCompletado = null;
                        $nuevoDetalle->created_at = now();
                        $nuevoDetalle->updated_at = now();
                        $nuevoDetalle->save();
    
                        // Replicar las asignaciones de usuarios (members)
                        foreach ($card->members as $member) {
                            $nuevaAsignacion = new AsignacionCardUser();
                            $nuevaAsignacion->idCard = $nuevaCard->id;
                            $nuevaAsignacion->idUser = $member->idUser;
                            $nuevaAsignacion->save();
                        }
    
                        // Replicar los archivos adjuntos (files)
                        foreach ($card->files as $file) {
                            $nuevoArchivo = $file->replicate();
                            $nuevoArchivo->idCard = $nuevaCard->id;
                            $nuevoArchivo->created_at = now();
                            $nuevoArchivo->updated_at = now();
                            $nuevoArchivo->save();
                        }
    
                        // Replicar los elementos de la lista de verificación (checkList)
                        foreach ($card->checkList as $checkItem) {
                            $nuevoCheckItem = $checkItem->replicate();
                            $nuevoCheckItem->idCard = $nuevaCard->id;
                            $nuevoCheckItem->created_at = now();
                            $nuevoCheckItem->updated_at = now();
                            $nuevoCheckItem->save();
    
                            // Replicar los ítems de la lista de verificación (checklistItems)
                            foreach ($checkItem->items as $item) {
                                // Replicar el item de la lista de verificación
                                $nuevoItem = $item->replicate();
                                $nuevoItem->idChecklistCard = $nuevoCheckItem->id;
                                $nuevoItem->created_at = now();
                                $nuevoItem->completado = 0;
                                $nuevoItem->updated_at = now();
                                $nuevoItem->save();
                            
                                // Replicar el detalle del checkItem (checkItemDetail)
                                if ($item->checkItemDetail) {
                                    $nuevoCheckItemDetail = $item->checkItemDetail->replicate();
                                    $nuevoCheckItemDetail->idCheckListItem = $nuevoItem->id;
                                    
                                    // Ajustar la fechaFinal de checkItemDetail
                                    $fechaFinalCheckItemDetail = Carbon::parse($item->checkItemDetail->fechaFinal);
                                    $nuevaFechaFinalCheckItemDetail = $fechaFinalCheckItemDetail->copy()->addMinutes($valueInMinutes);
                                    
                                    $nuevoCheckItemDetail->fechaFinal = $nuevaFechaFinalCheckItemDetail->format('Y-m-d');
                                    $nuevoCheckItemDetail->completado = 0;
                                    $nuevoCheckItemDetail->created_at = now();
                                    $nuevoCheckItemDetail->updated_at = now();
                                    $nuevoCheckItemDetail->save();
                                }
                            
                                // Replicar la asignación de usuario del checkItem (checkItemUser)
                                if ($item->checkItemUser) {
                                    $nuevaAsignacionCheckItemUser = $item->checkItemUser->replicate();
                                    $nuevaAsignacionCheckItemUser->idCheckListItem = $nuevoItem->id;
                                    $nuevaAsignacionCheckItemUser->created_at = now();
                                    $nuevaAsignacionCheckItemUser->updated_at = now();
                                    $nuevaAsignacionCheckItemUser->save();
                                }
                            }
                        }
    
                    } else {
                        $this->info('No coincide con la fecha objetivo:');
                        $this->info('Hora actual: ' . now()->format('Y-m-d H:i'));
                        $this->info('Fecha Final: ' . $detail->fechaFinal);
                        $this->info('Hora: ' . $detail->hora);
                        $this->info('Value (minutos): ' . $valueInMinutes);
                        $this->info('Fecha objetivo: ' . $fechaObjetivo->format('Y-m-d H:i'));
                    }
                }
            }
        }
    }
    
    
    
}
