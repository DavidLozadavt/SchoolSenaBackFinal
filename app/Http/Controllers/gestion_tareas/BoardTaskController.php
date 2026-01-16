<?php

namespace App\Http\Controllers\gestion_tareas;

use App\Events\ReloadCardContent;
use App\Http\Controllers\Controller;
use App\Jobs\SendAssignmentBoardNotification;
use App\Jobs\SendAssignmentCardNotification;
use App\Jobs\SendBasicEmail;
use App\Models\AsignacionBoardUser;
use App\Models\AsignacionCardUser;
use App\Models\AsignacionCheckItemUser;
use App\Models\AttachmentsCard;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardDetail;
use App\Models\CheckItemDetail;
use App\Models\ChecklistCard;
use App\Models\ChecklistItem;
use App\Models\CommentCheckItem;
use App\Models\ConfiguracionRepeatCard;
use App\Models\ImageBoard;
use App\Models\ListTask;
use App\Models\ResponseCommentCheckItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BoardTaskController extends Controller
{

    public function storeBoard(Request $request)
    {
        DB::beginTransaction();

        try {
            $board = new Board();

            $board->idImagenBoard = $request->input('idImagenBoard');
            $board->nombreBoard = $request->input('nombreBoard');
            $board->background = $request->input('background');
            $board->save();


            $asignacion = new AsignacionBoardUser();
            $asignacion->idBoard = $board->id;
            $asignacion->idUser = auth()->user()->id;
            $asignacion->save();

            $list = new ListTask();
            $list->nombreList = 'POR HACER';
            $list->idBoard = $board->id;
            $list->save();

            $list = new ListTask();
            $list->nombreList = 'EN PROCESO';
            $list->idBoard = $board->id;
            $list->save();

            $list = new ListTask();
            $list->nombreList = 'HECHO';
            $list->idBoard = $board->id;
            $list->save();


            DB::commit();

            return response()->json($board, 201);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function updateBoard(Request $request, int $id)
    {
        DB::beginTransaction();

        try {
            $board = Board::find($id);

            if (!$board) {
                return response()->json(['error' => 'Tablero no encontrado.'], 404);
            }

            // Verificar si se proporciona idImagenBoard o background y manejarlos adecuadamente
            $idImagenBoard = $request->input('idImagenBoard');
            $background = $request->input('background');

            if (!empty($idImagenBoard)) {
                $board->idImagenBoard = $idImagenBoard;
                $board->background = null;
            } elseif (!empty($background)) {
                $board->background = $background;
                $board->idImagenBoard = null;
            }

            $board->nombreBoard = $request->input('nombreBoard', $board->nombreBoard);
            $board->save();

            DB::commit();

            return response()->json($board, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }





    public function deleteBoard(int $id)
    {
        DB::beginTransaction();

        try {
            $board = Board::find($id);

            if (!$board) {
                return response()->json(['error' => 'Tablero no encontrado.'], 404);
            }

            $lists = ListTask::where('idBoard', $id)->get();

            foreach ($lists as $list) {
                $cards = Card::where('idList', $list->id)->get();

                foreach ($cards as $card) {
                    ChecklistCard::where('idCard', $card->id)->delete();
                    AsignacionCardUser::where('idCard', $card->id)->delete();
                    AttachmentsCard::where('idCard', $card->id)->delete();
                    CardDetail::where('idCard', $card->id)->delete();

                    $card->delete();
                }

                $list->delete();
            }

            AsignacionBoardUser::where('idBoard', $id)->delete();

            $board->delete();

            DB::commit();

            return response()->json(['message' => 'Tablero eliminado con éxito.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error interno del servidor.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }




    public function storeImageForBoard(Request $request)
    {
        DB::beginTransaction();

        try {
            $boardImage = new ImageBoard();

            if ($request->hasFile('imagenBoard')) {
                $boardImage->urlFile = $this->storeImgaenBoard($request->file('imagenBoard'));
            } else {
                $boardImage->urlFile = ImageBoard::RUTA_BOARD_DEFAULT;
            }

            $boardImage->save();


            DB::commit();

            return response()->json($boardImage, 201);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function getImageBoards()
    {
        $imagesBoards = ImageBoard::all();
        return response()->json($imagesBoards);
    }


    private function storeImgaenBoard($archivo)
    {
        if ($archivo) {
            $path = $archivo->store('public/boards');
            return '/storage/boards/' . basename($path);
        }
        return ImageBoard::RUTA_BOARD_DEFAULT;
    }


    public function getBoards()
    {
        $boards = Board::all();
        return response()->json($boards);
    }


    public function getMyBoards()
    {
        $idUser = auth()->user()->id;

        $boards = AsignacionBoardUser::with([
            'board.imageBoard',
            'board.listsTask.cards.cardDetails'
        ])
            ->where('idUser', $idUser)
            ->get()
            ->map(function ($asignacion) {
                $board = $asignacion->board;

                $totalCards = 0;
                $totalAtrasadoPorVencer = 0;
                $totalFechaCercana = 0;

                $today = now();
                $yesterday = now()->subDay();
                $twoDaysAgo = now()->subDays(2);

                foreach ($board->listsTask as $list) {
                    $totalCards += $list->cards->count();

                    foreach ($list->cards as $card) {
                        $totalAtrasadoPorVencer += $card->cardDetails
                            ->whereIn('estado', ['ATRASADO', 'POR VENCER'])
                            ->count();


                        $totalFechaCercana += $card->cardDetails
                            ->where('completado', 0)
                            ->whereBetween('fechaInicial', [$twoDaysAgo, $today])
                            ->count();
                    }
                }


                $board->total_cards = $totalCards;
                $board->total_atrasado_por_vencer = $totalAtrasadoPorVencer;
                $board->total_fecha_cercana = $totalFechaCercana;

                return $asignacion;
            });

        return response()->json($boards);
    }



    public function assignBoard(Request $request)
    {
        $users = $request->input('users');
        $idBoard = $request->input('idBoard');

        DB::beginTransaction();

        try {
            $assignments = [];
            foreach ($users as $idUser) {
                $boards = new AsignacionBoardUser();
                $boards->idBoard = $idBoard;
                $boards->idUser = $idUser;
                $boards->save();

                $user = User::with('persona')->find($idUser);
                if (!$user || !$user->persona) {

                    continue;
                }
                $email = $user->email;
                $userName = $user->persona->nombre1;


                $board = Board::find($idBoard);
                if (!$board) {

                    continue;
                }
                $nombreBoard = $board->nombreBoard;


                SendAssignmentBoardNotification::dispatch($email, $userName, $nombreBoard);

                $assignments[] = $boards;
            }

            DB::commit();

            return response()->json($assignments, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }






    public function deleteAssingBoard(Request $request)
    {
        $users = $request->input('users');
        $idBoard = $request->input('idBoard');

        DB::beginTransaction();

        try {

            AsignacionBoardUser::where('idBoard', $idBoard)
                ->whereIn('idUser', $users)
                ->delete();


            $lists = ListTask::where('idBoard', $idBoard)->get();

            foreach ($lists as $list) {

                $cards = Card::where('idList', $list->id)->get();

                foreach ($cards as $card) {

                    AsignacionCardUser::where('idCard', $card->id)
                        ->whereIn('idUser', $users)
                        ->delete();
                }
            }

            DB::commit();

            return response()->json(['message' => 'Asignaciones eliminadas exitosamente.'], 200);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }




    public function getPersonsByBoard($id)
    {
        $users = AsignacionBoardUser::with('user.persona')
            ->where('idBoard', $id)
            ->get();

        return response()->json($users);
    }



    public function getPersonsToAssign($id)
    {

        $assignedUserIds = AsignacionBoardUser::where('idBoard', $id)
            ->pluck('idUser');

        $users = User::whereNotIn('id', $assignedUserIds)
            ->with('persona')
            ->get();

        return response()->json($users);
    }





    public function storeList($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $list = new ListTask();
            $list->nombreList = $request->input('nombreList');
            $list->idBoard = $id;
            $list->save();
            DB::commit();

            return response()->json($list, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function getListByBoard($id)
    {
        $listTasks = ListTask::with(['board.imageBoard', 'cards.checkList.items', 'cards.files', 'cards.members.user.persona', 'cards.cardDetails'])
            ->where('idBoard', $id)
            ->get();


        $listTasks->each(function ($listTask) {
            $listTask->cards->each(function ($card) {

                $items = $card->checkList->flatMap->items;


                $card->files_count = $card->files->count();
                $card->items_completados = $items->where('completado', 1)->count();
                $card->items_no_completados = $items->count();
            });
        });

        return response()->json($listTasks);
    }




    public function updateList($id, Request $request)
    {


        $data = $request->all();
        $list = ListTask::findOrFail($id);
        $list->fill($data);
        $list->save();

        return response()->json($list);
    }


    public function deleteList($id)
    {
        $list = ListTask::find($id);

        if (!$list) {
            return response()->json(['error' => 'Lista no encontrada.'], 404);
        }

        if ($list->cards()->count() > 0) {
            return response()->json(['error' => 'No se puede eliminar la lista porque tiene tarjetas asociadas.'], 400);
        }

        $list->delete();

        return response()->json(['message' => 'Lista eliminada con éxito.']);
    }



    public function getCardsByList($id)
    {

        $cards = Card::with(['files', 'checkList.items', 'members.user.persona', 'cardDetails'])
            ->where('idList', $id)
            ->get();


        $cards->map(function ($card) {

            $items = $card->checkList->flatMap->items;

            $card->files_count = $card->files->count();
            $card->items_completados = $items->where('completado', 1)->count();
            $card->items_no_completados = $items->count();

            return $card;
        });

        return response()->json($cards);
    }



    public function getCardBy($id)
    {
        $cards = Card::where('id', $id)->get();
        return response()->json($cards);
    }




    public function storeCard($id, Request $request)
    {
        DB::beginTransaction();


        try {
            $card = new Card();
            $card->titulo = $request->input('titulo');
            $card->idList = $id;
            $card->idUser = auth()->user()->id;
            $card->save();
            DB::commit();

            return response()->json($card, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function updateCard($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $card = Card::find($id);

            if (!$card) {
                return response()->json(['error' => 'Tarjeta no encontrada.'], 404);
            }

            $card->titulo = $request->input('titulo', $card->titulo);
            $card->descripcion = $request->input('descripcion', $card->descripcion);
            $card->idList = $request->input('idList', $card->idList);
            $card->save();

            DB::commit();

            return response()->json($card);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function deleteCard($id)
    {
        DB::beginTransaction();

        try {
            $userId = auth()->user()->id;
            $card = Card::find($id);

            if (!$card) {
                return response()->json(['error' => 'Tarjeta no encontrada.'], 404);
            }

            if ($card->idUser != $userId) {
                return response()->json(['error' => 'No tienes permiso para eliminar esta tarjeta.'], 403);
            }

            // Eliminar ChecklistItems asociados a la tarjeta
            $checklistCards = ChecklistCard::where('idCard', $id)->get();
            foreach ($checklistCards as $checklistCard) {
                $checklistItems = ChecklistItem::where('idChecklistCard', $checklistCard->id)->get();
                foreach ($checklistItems as $checklistItem) {
                    // Eliminar CheckItemDetail y AsignacionCheckItemUser asociados
                    CheckItemDetail::where('idCheckListItem', $checklistItem->id)->delete();
                    AsignacionCheckItemUser::where('idCheckListItem', $checklistItem->id)->delete();
                }
                ChecklistItem::where('idChecklistCard', $checklistCard->id)->delete();
            }

            // Eliminar ChecklistCards asociados a la tarjeta
            ChecklistCard::where('idCard', $id)->delete();

            // Eliminar otras relaciones
            AsignacionCardUser::where('idCard', $id)->delete();
            AttachmentsCard::where('idCard', $id)->delete();
            CardDetail::where('idCard', $id)->delete();

            // Eliminar la tarjeta
            $card->delete();

            DB::commit();

            return response()->json(['message' => 'Tarjeta eliminada con éxito.']);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }







    public function getCheklistByCard($id)
    {
        $cards = ChecklistCard::where('idCard', $id)->get();
        return response()->json($cards);
    }




    public function storeCheckList($id, Request $request)
    {
        DB::beginTransaction();


        try {
            $check = new ChecklistCard();
            $check->nombreCheckList = $request->input('nombreCheckList');
            $check->idCard = $id;
            $check->save();
            DB::commit();

            return response()->json($check, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function updateCheckList($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $check = ChecklistCard::find($id);

            if (!$check) {
                return response()->json(['error' => 'Checklist no encontrado.'], 404);
            }

            $check->nombreCheckList = $request->input('nombreCheckList', $check->nombreCheckList);
            $check->save();
            DB::commit();

            return response()->json($check, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function getItemCheckListByIdCheck($id)
    {

        $itemCheck = ChecklistItem::with('checkItemDetail', 'checkItemUser.user.persona')
            ->withCount('commentCheckItems')
            ->where('idChecklistCard', $id)
            ->get();

        return response()->json($itemCheck);
    }




    public function storeItemChecklist($id, Request $request)
    {
        DB::beginTransaction();


        try {
            $checkItem = new ChecklistItem();
            $checkItem->descripcion = $request->input('descripcion');
            $checkItem->idChecklistCard = $id;
            $checkItem->completado = 0;
            $checkItem->save();
            DB::commit();

            return response()->json($checkItem, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function deleteCheckList(int $id)
    {
        $checklist = ChecklistCard::find($id);

        if (!$checklist) {
            return response()->json(['error' => 'Checklist no encontrado.'], 404);
        }

        ChecklistItem::where('idChecklistCard', $id)->delete();

        $checklist->delete();

        return response()->json(['message' => 'Checklist eliminado con éxito.']);
    }




    public function checkAndDeschekItem($id)
    {
        // Encuentra el ChecklistItem por su ID
        $checklistItem = ChecklistItem::find($id);
        // Alterna el valor del campo completado
        $checklistItem->completado = !$checklistItem->completado;

        // Si existe un CheckItemDetail asociado, también alterna su valor de completado
        if ($checklistItem->checkItemDetail) {
            $checklistItem->checkItemDetail->completado = $checklistItem->completado;
            $checklistItem->checkItemDetail->fechaCompletado =  Carbon::now();
            $checklistItem->checkItemDetail->save();
        }

        // Guarda los cambios en ChecklistItem
        $checklistItem->save();

        // Retorna la respuesta JSON
        return response()->json($checklistItem);
    }




    public function deleteCheckItem(int $id)
    {
        $checkItem = ChecklistItem::find($id);

        if (!$checkItem) {
            return response()->json(['error' => 'checkItem no encontrado.'], 404);
        }

        AsignacionCheckItemUser::where('idCheckListItem', $id)->delete();
        CheckItemDetail::where('idCheckListItem', $id)->delete();
        CommentCheckItem::where('idChecklisteItem', $id)->delete();


        $checkItem->delete();

        return response()->json(['message' => 'checkItem eliminado con éxito.']);
    }



    public function updateItemCheck($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $item = ChecklistItem::find($id);

            if (!$item) {
                return response()->json(['error' => 'item no encontrado.'], 404);
            }

            $item->descripcion = $request->input('descripcion', $item->descripcion);
            $item->save();

            DB::commit();

            return response()->json($item);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function getFilesByCard($id)
    {
        $files = AttachmentsCard::where('idCard', $id)->get();
        return response()->json($files);
    }



    public function storeFilesCard(Request $request)
    {

        DB::beginTransaction();
        $id = $request->input('idCard');

        try {
            $cardFile = new AttachmentsCard();
            if ($request->hasFile('fileCard')) {
                $cardFile->urlArchivo = $this->storeFileCard($request->file('fileCard'));
            } else {
                $cardFile->urlArchivo = AttachmentsCard::ATTACHMENT_DEFAULT;
            }
            $cardFile->idCard = $id;
            $cardFile->type = $request->input('fileExtension');
            $cardFile->nombre = $request->input('fileName');
            $cardFile->save();
            DB::commit();

            return response()->json($cardFile, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    private function storeFileCard($archivo)
    {
        if ($archivo) {
            $path = $archivo->store('public/cards');
            return '/storage/cards/' . basename($path);
        }
        return AttachmentsCard::ATTACHMENT_DEFAULT;
    }




    public function deleteFileCard(int $id)
    {
        $fileCard = AttachmentsCard::find($id);

        if (!$fileCard) {
            return response()->json(['error' => 'Archivo no encontrado.'], 404);
        }



        $fileCard->delete();

        return response()->json(['message' => 'Archivo eliminado con éxito.']);
    }


    public function assignCardUser(Request $request)
    {

        $users = $request->input('users');
        $idCard = $request->input('idCard');

        DB::beginTransaction();

        try {
            $assignments = [];
            foreach ($users as $idUser) {
                $userCard = new AsignacionCardUser();
                $userCard->idCard = $idCard;
                $userCard->idUser = $idUser;
                $userCard->save();

                SendAssignmentCardNotification::dispatch($idUser, $idCard);


                $assignments[] = $userCard;
            }

            DB::commit();

            return response()->json($assignments, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function getUsersCard($id)
    {

        $usersCard = AsignacionCardUser::with('user.persona')
            ->where('idCard', $id)
            ->get();
        return response()->json($usersCard);
    }


    public function deleteAssingCard($id)
    {

        $asignacion = AsignacionCardUser::where('id', $id)->first();

        if ($asignacion) {
            $asignacion->delete();

            return response()->json(['message' => 'Asignación eliminada correctamente.']);
        } else {
            return response()->json(['message' => 'Asignación no encontrada.'], 404);
        }
    }



    public function getUsersForAssignCard($idBoard, $idCard)
    {

        $assignedUserIdsBoard = AsignacionBoardUser::where('idBoard', $idBoard)
            ->pluck('idUser');

        $assignedUserIdsCard = AsignacionCardUser::where('idCard', $idCard)
            ->pluck('idUser');


        $userIdsNotAssignedToCard = $assignedUserIdsBoard->diff($assignedUserIdsCard);


        $usersNotAssignedToCard = User::whereIn('id', $userIdsNotAssignedToCard)
            ->with('persona')
            ->get();


        $result = $usersNotAssignedToCard->map(function ($user) {
            return
                $user;
        });

        return response()->json($result);
    }




    public function storeCardDetail($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $cardDetail = new CardDetail();
            $cardDetail->fechaInicial = $request->input('startDate');
            $cardDetail->fechaFinal = $request->input('endDate');
            $cardDetail->hora = $request->input('endTime');
            $cardDetail->idConfiguracionRecordatorio = $request->input('configuracion');
            $cardDetail->idCard = $id;

            $now = Carbon::now();

            $fechaFinalCarbon = Carbon::parse($cardDetail->fechaFinal);

            $daysRemaining = $fechaFinalCarbon->diffInDays($now);

            if ($fechaFinalCarbon->isPast()) {
                $cardDetail->estado = 'ATRASADO';
            } elseif ($daysRemaining <= 2) {
                $cardDetail->estado = 'POR VENCER';
            } else {
            }

            $cardDetail->save();
            DB::commit();

            return response()->json($cardDetail, 201);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function updateCardDetail($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $fechaInicial = $request->input('startDate');
            $fechaFinal = $request->input('endDate');
            $hora = $request->input('endTime');

            $cardDetail = CardDetail::findOrFail($id);
            $cardDetail->fechaInicial = $fechaInicial;
            $cardDetail->fechaFinal = $fechaFinal;
            $cardDetail->hora = $hora;
            $cardDetail->idConfiguracionRecordatorio = $request->input('configuracion');

            $now = now();
            $fechaFinalCarbon = Carbon::parse($fechaFinal);
            $daysRemaining = $fechaFinalCarbon->diffInDays($now);

            if ($fechaFinalCarbon->isPast()) {
                $cardDetail->estado = 'ATRASADO';
            } elseif ($daysRemaining <= 2) {
                $cardDetail->estado = 'POR VENCER';
            } else {
                $cardDetail->estado = null;
            }

            $cardDetail->save();
            DB::commit();

            return response()->json($cardDetail, 200);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function getDetailCard($id)
    {
        $detailCard = CardDetail::where('idCard', $id)
            ->with('configuracionRepeat')  // Carga la relación configuracionRepeat
            ->first();

        return response()->json($detailCard);
    }




    public function completeDateCard($id)
    {
        $cardComplete = CardDetail::find($id);

        if ($cardComplete->completado == 1) {

            $fechaFinal = Carbon::parse($cardComplete->fechaFinal);
            $fechaFinalCarbon = Carbon::now();

            if ($fechaFinal->isPast()) {
                $cardComplete->estado = 'ATRASADO';
            } elseif ($fechaFinal->diffInDays($fechaFinalCarbon) <= 2) {
                $cardComplete->estado = 'POR VENCER';
            } else {
                $cardComplete->estado = null;
            }

            $cardComplete->completado = 0;
            $cardComplete->fechaCompletado = null;
        } else {
            $cardComplete->completado = 1;
            $cardComplete->estado = 'COMPLETADO';
            $cardComplete->fechaCompletado = Carbon::now();
        }

        $cardComplete->save();

        return response()->json($cardComplete);
    }



    public function deleteDateCard($id)
    {

        $cardDate = CardDetail::where('id', $id)->first();

        if ($cardDate) {
            $cardDate->delete();

            return response()->json(['message' => 'Fecha eliminada correctamente.']);
        } else {
            return response()->json(['message' => 'Fecha no encontrada.'], 404);
        }
    }



    //guarda la fecha del item check
    public function storeCheckItemDetail($id, Request $request)
    {
        DB::beginTransaction();

        try {

            $cardDetail = CheckItemDetail::where('idCheckListItem', $id)->first();

            if ($cardDetail) {

                $cardDetail->fechaFinal = $request->input('endDateCheck');
                $cardDetail->save();
            } else {

                $cardDetail = new CheckItemDetail();
                $cardDetail->fechaFinal = $request->input('endDateCheck');
                $cardDetail->hora = $request->input('endTimeCheck');
                $cardDetail->idCheckListItem = $id;
                $cardDetail->save();
            }

            DB::commit();

            return response()->json($cardDetail, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }




    //asigna un usuario al item check
    public function assignCheckItemdUser(Request $request)
    {
        $idUser = $request->input('user');
        $idCheckListItem = $request->input('idCheckListItem');

        DB::beginTransaction();

        try {

            $user = User::with('persona')->findOrFail($idUser);
            $email = $user->email;
            $nombre = $user->persona->nombre1;
            $apellido = $user->persona->apellido1;

            $assignUserItem = new AsignacionCheckItemUser();
            $assignUserItem->idCheckListItem = $idCheckListItem;
            $assignUserItem->idUser = $idUser;
            $assignUserItem->save();


            $checkListItem = $assignUserItem->checkListItem;
            $descripcionTarea = $checkListItem->descripcion;


            $subject = "Asignación a una tarea";
            $message = "Querido/a {$nombre} {$apellido},\n\n"
                . "Has sido asignado/a a la tarea: {$descripcionTarea}.\n"
                . "Por favor, revisa tus asignaciones.\n\n"
                . "Atentamente,\n"
                . "El equipo de Virtual Technology";


            SendBasicEmail::dispatch($email, $subject, $message);

            DB::commit();

            return response()->json($assignUserItem, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function getDetailItemCheck($id)
    {

        $detailCard = CheckItemDetail::where('idCheckListItem', $id)
            ->first();
        return response()->json($detailCard);
    }


    public function getUsersItemCheck($id)
    {

        $usersCheckItem = AsignacionCheckItemUser::with('user.persona')
            ->where('idCheckListItem', $id)
            ->get();
        return response()->json($usersCheckItem);
    }



    public function getCommentCheeckItem($id)
    {
        $comments = CommentCheckItem::with([
            'user.persona',
            'responses' => function ($query) {
                $query->orderBy('created_at', 'desc')
                    ->with(['user.persona', 'parent' => function ($query) {
                        $query->orderBy('created_at', 'desc')
                            ->with('user.persona');
                    }]);
            }
        ])
            ->where('idChecklisteItem', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($comments);
    }




    public function storeCommentItemCheck(Request $request)
    {

        DB::beginTransaction();

        try {

            $comment = new CommentCheckItem();

            $comment->comment = $request->input('comment');
            $comment->nameFile = $request->input('nameFile');
            $comment->idUser =  auth()->user()->id;
            $comment->idChecklisteItem = $request->input('idChecklisteItem');
            $comment->type = $request->input('type');
            if ($request->hasFile('fileItem')) {
                $comment->urlArchivo = $this->storeFileItemCheck($request->file('fileItem'));
            } else {
                $comment->null;
            }
            $comment->save();
            DB::commit();


            return response()->json($comment, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function deleteCommentItemCheck($id)
    {
        DB::beginTransaction();

        try {
            $comment = CommentCheckItem::find($id);

            if (!$comment) {
                return response()->json(['error' => 'Comentario no encontrado.'], 404);
            }


            if ($comment->urlArchivo && Storage::exists($comment->urlArchivo)) {
                Storage::delete($comment->urlArchivo);
            }

            $comment->delete();

            DB::commit();

            return response()->json(['message' => 'Comentario eliminado correctamente.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error interno del servidor.',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    private function storeFileItemCheck($archivo)
    {
        if ($archivo) {
            $path = $archivo->store('public/itemCheck');
            return '/storage/itemCheck/' . basename($path);
        }
        return CommentCheckItem::COMMENT_DEFAULT;
    }




    // respuesta del comentario
    public function storeResponseComment(Request $request)
    {

        DB::beginTransaction();

        try {

            $comment = new ResponseCommentCheckItem();

            $comment->comment = $request->input('comment');
            $comment->nameFile = $request->input('nameFile');
            $comment->idUser =  auth()->user()->id;
            $comment->idCommentCheckItem = $request->input('idCommentCheckItem');
            $comment->type = $request->input('type');
            if ($request->hasFile('fileResponse')) {
                $comment->urlArchivo = $this->storeFileResponse($request->file('fileResponse'));
            } else {
                $comment->null;
            }
            $comment->save();
            DB::commit();


            return response()->json($comment, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }




    //respuesta de la respuesta del comment
    public function storeSubResponseComment(Request $request)
    {
        DB::beginTransaction();

        try {
            $comment = new ResponseCommentCheckItem();

            $comment->comment = $request->input('comment');
            $comment->nameFile = $request->input('nameFile');
            $comment->idUser = auth()->user()->id;
            $comment->idCommentCheckItem = $request->input('idCommentCheckItem');
            $comment->type = $request->input('type');

            if ($request->hasFile('fileSubResponse')) {
                $comment->urlArchivo = $this->storeFileResponse($request->file('fileSubResponse'));
            }
            $comment->idResponseComment = $request->input('idResponseComment');
            $comment->save();



            DB::commit();

            return response()->json($comment, 201);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    //file response
    private function storeFileResponse($archivo)
    {
        if ($archivo) {
            $path = $archivo->store('public/itemCheckResponse');
            return '/storage/itemCheckResponse/' . basename($path);
        }
        return ResponseCommentCheckItem::RESPONSE_DEFAULT;
    }



    public function sendEmailMentionCheckList(Request $request)
    {

        $emails = array_unique($request->input('emails'));
        $idCheckListItem = $request->input('selectedCheckItemId');



        try {

            $item = ChecklistItem::find($idCheckListItem);

            foreach ($emails as $email) {
                $subject = "Mención en una tarea";
                $message = "Querido/a {$email},\n\n"
                    . "Has sido mencionado/a en la tarea: {$item->descripcion}.\n"
                    . "Por favor, revisa tus asignaciones.\n\n"
                    . "Atentamente,\n"
                    . "El equipo de Virtual Technology";


                SendBasicEmail::dispatch($email, $subject, $message);
            }



            return response()->json('Emails enviados correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function sendEmailMentionComment(Request $request)
    {

        $emails = array_unique($request->input('emails'));
        $idComment = $request->input('idComment');


        try {

            $item = CommentCheckItem::find($idComment);

            foreach ($emails as $email) {
                $subject = "Mención en un comentario";
                $message = "Querido/a {$email},\n\n"
                    . "Has sido mencionado/a en el comentario: {$item->comment}.\n"
                    . "Por favor, revisa tus asignaciones.\n\n"
                    . "Atentamente,\n"
                    . "El equipo de Virtual Technology";


                SendBasicEmail::dispatch($email, $subject, $message);
            }



            return response()->json('Emails enviados correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }





    public function sendEmailMentionCommentResponse(Request $request)
    {


        $emails = array_unique($request->input('emails'));
        $idComment = $request->input('idComment');



        try {

            $item = ResponseCommentCheckItem::find($idComment);

            foreach ($emails as $email) {
                $subject = "Mención en una respuesta";
                $message = "Querido/a {$email},\n\n"
                    . "Has sido mencionado/a en la repuesta: {$item->comment}.\n"
                    . "Por favor, revisa tus asignaciones.\n\n"
                    . "Atentamente,\n"
                    . "El equipo de Virtual Technology";


                SendBasicEmail::dispatch($email, $subject, $message);
            }


            return response()->json('Emails enviados correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function deleteItemCheckDetail($id)
    {

        $detail = CheckItemDetail::where('id', $id)->first();

        if ($detail) {
            $detail->delete();

            return response()->json(['message' => 'Asignación eliminada correctamente.']);
        } else {
            return response()->json(['message' => 'Asignación no encontrada.'], 404);
        }
    }

    public function deleteUserItemCheck($id)
    {

        $asignacion = AsignacionCheckItemUser::where('id', $id)->first();

        if ($asignacion) {
            $asignacion->delete();

            return response()->json(['message' => 'Asignación eliminada correctamente.']);
        } else {
            return response()->json(['message' => 'Asignación no encontrada.'], 404);
        }
    }



    public function getConfigurationRepeat()
    {
        $config = ConfiguracionRepeatCard::all();
        return response()->json($config);
    }



    public function storeConfigurationRepeat($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $cardDetail = CardDetail::find($id);
            $cardDetail->idConfiguracionRepeat = $request->input('idConfiguracionRepeat');
            $cardDetail->save();
            DB::commit();

            return response()->json($cardDetail, 201);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function deleteConfigurationRepeat($id)
    {

        $cardDate = CardDetail::where('id', $id)->first();

        if ($cardDate) {

            $cardDate->idConfiguracionRepeat = null;
            $cardDate->save();

            return response()->json(['message' => 'Campo idConfiguracionRepeat actualizado a null correctamente.']);
        } else {
            return response()->json(['message' => 'Registro no encontrado.'], 404);
        }
    }


    public function archivateCard($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $card = Card::find($id);

            if (!$card) {
                return response()->json(['error' => 'Tarjeta no encontrada.'], 404);
            }


            if ($card->cardDetails->isEmpty()) {

                $newCardDetail = new CardDetail();
                $newCardDetail->idCard = $card->id;
                $newCardDetail->estado = 'ARCHIVADO';
                $newCardDetail->save();
            } else {

                foreach ($card->cardDetails as $cardDetail) {
                    $cardDetail->estado = 'ARCHIVADO';
                    $cardDetail->save();
                }
            }

            DB::commit();

            return response()->json($card);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function unarchiveCard($id, Request $request)
    {
        DB::beginTransaction();

        try {

            $card = Card::find($id);

            if (!$card) {
                return response()->json(['error' => 'Tarjeta no encontrada.'], 404);
            }

            foreach ($card->cardDetails as $cardDetail) {

                if ($cardDetail->estado === 'ARCHIVADO') {
                    $fechaFinal = Carbon::parse($cardDetail->fechaFinal);
                    $fechaFinalCarbon = Carbon::now();


                    if ($cardDetail->completado == 1) {
                        $cardDetail->estado = 'COMPLETADO';
                    } else {

                        if ($fechaFinal->isPast()) {
                            $cardDetail->estado = 'ATRASADO';
                        } elseif ($fechaFinal->diffInDays($fechaFinalCarbon) <= 2) {
                            $cardDetail->estado = 'POR VENCER';
                        } else {
                            $cardDetail->estado = null;
                        }
                    }

                    $cardDetail->completado = 0;
                    $cardDetail->fechaCompletado = null;

                    $cardDetail->save();
                }
            }

            DB::commit();

            return response()->json($card);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }
}
