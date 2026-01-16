<?php

namespace App\Http\Controllers\gestion_chat;

use App\Http\Controllers\Controller;
use App\Http\Controllers\gestion_chat\ComentarioArchivosController;
use App\Models\ActivationCompanyUser;
use App\Models\AsignacionComentarios;
use App\Models\AsignacionParticipante;
use App\Models\Comentario;
use App\Models\ComentarioArchivos;
use App\Models\GrupoChat;
use App\Models\User;
use App\Services\FCMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pusher\Pusher;

class ComentarioController extends Controller
{

    private array $relations;

    function __construct()
    {
        $this->relations = [];
    }

    /**
     * Authorization of channel pusher
     *
     * @param Request $request
     * @return void
     */
    public function authorizationPusher(Request $request)
    {


        $socketId = $request->input('socket_id');
        $channel = $request->input('channel_name');

        if (!$channel || !is_string($channel)) {
            return response()->json(['error' => 'El nombre del canal es inválido'], 400);
        }

        $pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS' => true,
            ]
        );

        return $pusher->authorizeChannel((string) $channel, (string) $socketId);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $comments = Comentario::all();
        return response()->json($comments);
    }

    /**
     * Get all comments messages by group
     * @param \Illuminate\Http\Request $request
     * @param int $idGrupo
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCommentsByGrupo(Request $request, int $idGrupo): JsonResponse
    {
        $data = json_decode($request->input('data'), true);
        $relations = $data['relations'] ?? $this->relations;

        $user = auth()->user();
        $activeUser = $user->activationCompanyUsers;


        $comments = Comentario::with(array_merge($relations, ['archivos']))
            ->whereHas('asignaciones.grupo', function ($query) use ($idGrupo) {
                $query->where('idGrupo', $idGrupo);
            })
            ->get();


        foreach ($comments as $comment) {

            $comment->side = ($comment->idActivationCompanyUser == $activeUser[0]['id']) ? 'right' : 'left';

            if (!empty($comment->archivos)) {
                foreach ($comment->archivos as $archivo) {
                    if (!empty($archivo->archivo)) {
                        $archivo->archivo = url($archivo->archivo);
                    }
                }
            }
        }

        $comments->load(["activationCompanyUser.user.persona", "asignaciones"]);

        return response()->json($comments, 200);
    }

    /**
     * Get comments between two users
     * @param Request|null $request
     * @param integer $idUser
     * @return JsonResponse
     */
    // public function getCommentsOneToOne(Request $request, int $idUser): JsonResponse
    // {

    //     // Traer todos los comentarios donde sea el del idActiveUser
    //     // y el idcommentable sea el id del usuario al que uno cliclea
    //     $data = json_decode($request->input('data'), true);

    //     $relations = $data['relations'] ?? $this->relations;

    //     $user = auth()->user();
    //     $idPersona = $user->persona->id;
    //     $user = User::findOrFail($idPersona);
    //     $activeUser = $user->activationCompanyUsers;

    //     $userOfFront = User::findOrFail($idUser);

    //     $activeUserOfFront = $userOfFront->activationCompanyUsers;



    //     $comments = Comentario::with($relations)
    //         ->join('asignacionComentarios', 'comentarios.id', '=', 'asignacionComentarios.idComentario')
    //         ->where(function ($query) use ($activeUserOfFront, $activeUser) {
    //             $query->where('comentarios.idActivationCompanyUser', $activeUser[0]['id'])
    //                 ->where('commentable_type', 'App\Model\ActivationCompanyUser')
    //                 ->where('asignacionComentarios.commentable_id', $activeUserOfFront[0]['id']);
    //         })
    //         ->orWhere(function ($query) use ($activeUserOfFront, $activeUser) {
    //             $query->where('comentarios.idActivationCompanyUser', $activeUserOfFront[0]['id'])
    //                 ->where('commentable_type', 'App\Model\ActivationCompanyUser')
    //                 ->where('asignacionComentarios.commentable_id', $activeUser[0]['id']);
    //         })
    //         ->get();

    //     foreach ($comments as $comment) {
    //         $archivosComentario = [];

    //         $archivos = ComentarioArchivos::where('idComentario', $comment->idComentario)->get();

    //         if (!$archivos->isEmpty()) {
    //             foreach ($archivos as $archivo) {
    //                 $archivosComentario[] = $archivo;
    //             }
    //         }

    //         $comment->archivos = $archivosComentario;

    //         $comment->side = ($comment->idActivationCompanyUser == $activeUser[0]['id']) ? 'right' : 'left';
    //     }

    //     $comments->load(["activationCompanyUser.user.persona", "asignaciones"]);

    //     return response()->json($comments, 200);
    // }
    public function getCommentsOneToOne(Request $request, int $idUser): JsonResponse
    {
        $data = json_decode($request->input('data'), true);
        $relations = $data['relations'] ?? $this->relations;

        $user = auth()->user();
        $idPersona = $user->persona->id;
        $user = User::findOrFail($idPersona);
        $activeUser = $user->activationCompanyUsers;

        $userOfFront = User::findOrFail($idUser);
        $activeUserOfFront = $userOfFront->activationCompanyUsers;

        $limit = $request->get('limit', 30);
        $offset = $request->get('offset', 0);

        $comments = Comentario::with($relations)
            ->join('asignacionComentarios', 'comentarios.id', '=', 'asignacionComentarios.idComentario')
            ->where(function ($query) use ($activeUserOfFront, $activeUser) {
                $query->where('comentarios.idActivationCompanyUser', $activeUser[0]['id'])
                    ->where('commentable_type', 'App\Model\ActivationCompanyUser')
                    ->where('asignacionComentarios.commentable_id', $activeUserOfFront[0]['id']);
            })
            ->orWhere(function ($query) use ($activeUserOfFront, $activeUser) {
                $query->where('comentarios.idActivationCompanyUser', $activeUserOfFront[0]['id'])
                    ->where('commentable_type', 'App\Model\ActivationCompanyUser')
                    ->where('asignacionComentarios.commentable_id', $activeUser[0]['id']);
            })
            ->orderBy('comentarios.created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        foreach ($comments as $comment) {
            $archivosComentario = ComentarioArchivos::where('idComentario', $comment->idComentario)->get();
            $comment->archivos = $archivosComentario;
            $comment->side = ($comment->idActivationCompanyUser == $activeUser[0]['id']) ? 'right' : 'left';
        }

        $comments->load(["activationCompanyUser.user.persona", "asignaciones"]);

        return response()->json($comments->reverse()->values(), 200);
    }


    /**
     * Send comment to between two users
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function addCommentOneToOne(Request $request, int $idUser): JsonResponse
    {

        $user = auth()->user();
        $idPersona = $user->persona->id;
        $nombreUsuario = $user->persona->nombre1 . ' ' . $user->persona->apellido1;
        $user = User::findOrFail($idPersona);
        $activeUser = $user->activationCompanyUsers;

        $userOfFront = User::findOrFail($idUser);
        $activeUserOfFrom = $userOfFront->activationCompanyUsers;
        $deviceToken = $userOfFront->device_token;

        if ($deviceToken) {
            FCMService::send(
                $nombreUsuario,
                $request->input('body'),
                $deviceToken
            );
        }


        // if ($userOfFront->estadoMensajeria == EstadoMensajeriaChat::NO_DISPONIBLE) {
        //     return response()->json(['message' => 'No puedes enviar mensajes a este usuario porque su estado es ' . $userOfFront->estadoMensajeria], 422);
        // }

        $comment = Comentario::create([
            'body'                    => $request->input('body'),
            'idActivationCompanyUser' => $activeUser[0]['id'],
            'commentable_type'        => 'App\Model\ActivationCompanyUser',
            'origen'                  => $request->input('origen')
        ]);

        AsignacionComentarios::create([
            'commentable_id' => $activeUserOfFrom[0]['id'],
            'idComentario'   => $comment->id,
        ]);

        $urlfiles = ComentarioArchivosController::createFiles($request, $comment->id);

        $files = $urlfiles->original;

        $comment->load(['activationCompanyUser.user.persona']);

        if (isset($files['message']) && $files['message'] === "No se encontraron archivos en la solicitud") {
            $archivos = [];
        } else {
            $archivos = [];
            foreach ($files as $file) {
                $archivos[] = $file;
            }
        }

        $comment->archivos->archivo = $archivos;

        if (!empty($comment->archivos->archivo)) {
            foreach ($comment->archivos as $archivo) {
                $archivo->archivo = url($archivo->archivo);
            }
        }
        $comment->side = ($comment->idActivationCompanyUser == $activeUser[0]['id']) ? 'right' : 'left';

        return response()->json($comment, 201);
    }

    /**
     * Add new comment with files to group
     *
     * @param Request $request
     * @param integer $idGroup
     * @return JsonResponse
     */
    public function addCommentGroup(Request $request, int $idGroup): JsonResponse
    {

        $user = auth()->user();
        $idPersona = $user->persona->id;
        $user = User::findOrFail($idPersona);
        $activeUser = $user->activationCompanyUsers;

        $group = GrupoChat::findOrFail($idGroup);

        $comment = Comentario::create([
            'body'                    => $request->input('body'),
            'idActivationCompanyUser' => $activeUser[0]['id'],
            'commentable_type'        => 'App\Models\GrupoChat',
            'origen'                  => $request->input('origen')
        ]);

        $group->comentarios()->attach($comment);

        $urlfiles = ComentarioArchivosController::createFiles($request, $comment->id);

        $files = $urlfiles->original;

        $comment->load(['activationCompanyUser.user.persona']);

        if (isset($files['message']) && $files['message'] === "No se encontraron archivos en la solicitud") {
            $archivos = [];
        } else {
            $archivos = [];
            foreach ($files as $file) {
                $archivos[] = $file;
            }
        }

        $comment->archivos->archivo = $archivos;

        if (!empty($comment->archivos->archivo)) {
            foreach ($comment->archivos as $archivo) {
                $archivo->archivo = url($archivo->archivo);
            }
        }

        return response()->json($comment, 201);
    }

    /**
     * Send messages to groups or (students, users)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendCommentMultipleGroupsOrUsers(Request $request): JsonResponse
    {

        // { comment: ComentarioModel, students: [ids], groups: [ids], archivos: [] }

        $data = $request->all();

        $jsonStudents = $data['students'];
        $students = json_decode($jsonStudents, true);

        $jsonGroups = $data['groups'];
        $groups = json_decode($jsonGroups, true);

        $messagesStudents = [];
        if ($students) {
            foreach ($students as $index => $idUserStudent) {
                $response = $this->addCommentOneToOne($request, $idUserStudent);
                $messagesStudents[] = $response->original;
            }
        }

        $messagesGroups = [];
        if ($groups) {
            foreach ($groups as $index => $idGroup) {
                $response = $this->addCommentGroup($request, $idGroup);
                $messagesGroups[] = $response->original;
            }
        }

        // Validate message because all message is same by user
        if (!empty($messagesGroups)) {
            $message = $messagesGroups[0];
        } else {
            $message = $messagesStudents[0];
        }

        return response()->json([
            $message
        ], 200);
    }


    public function storeGrupo(Request $request)
    {
        $grupo = new GrupoChat();
        $grupo->nombreGrupo = $request->input('nombreGrupo');
        $grupo->estado = 'ACTIVO';
        $grupo->save();

        $userIds = $request->input('selectedIds');

        $loggedUserId = auth()->user()->id;
        $activationUser = ActivationCompanyUser::where('user_id', $loggedUserId)->first();

        if ($activationUser) {
            $asignacion = new AsignacionParticipante();
            $asignacion->idGrupo = $grupo->id;
            $asignacion->idActivationCompanyUser = $activationUser->id;
            $asignacion->save();
        }

        foreach ($userIds as $userId) {

            if ($userId == $loggedUserId) {
                continue;
            }

            $activationUser = ActivationCompanyUser::where('user_id', $userId)->first();

            if ($activationUser) {
                $asignacion = new AsignacionParticipante();
                $asignacion->idGrupo = $grupo->id;
                $asignacion->idActivationCompanyUser = $activationUser->id;
                $asignacion->save();
            }
        }

        return response()->json(['message' => 'Grupo y participantes creados correctamente'], 201);
    }
}
