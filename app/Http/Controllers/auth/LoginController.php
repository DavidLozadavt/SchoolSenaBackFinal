<?php

namespace App\Http\Controllers\auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationCompanyUser;
use App\Models\Person;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    /**
     * Handle an authentication attempt.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
    
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
    
            $user = auth()->user();
    
            if ($request->has('device_token')) {
                $user->device_token = $request->input('device_token');
                $user->save();
            }
    
            $activationCompanyUsers = ActivationCompanyUser::with('company')
                ->with('roles')
                ->active()
                ->byUser($user->id)
                ->where(function ($query) {
                    $query->where('fechaFin', '>', Carbon::now())
                        ->orWhere('state_id', '!=', 2);
                })
                ->get();
    
            if ($activationCompanyUsers->isEmpty()) {
                return response()->json(['error' => 'No tienes acceso. Comunícate con un administrador.'], 401);
            }
    
            return response()->json($activationCompanyUsers);           
        }
    
        return response()->json(['error' => 'Las credenciales proporcionadas no coinciden con nuestros registros.'], 401);
    }

    public function logout(Request $request)
    {
        Cache::flush('usuario' . auth()->user()->id);
        Cache::flush('permissions' . auth()->user()->id);

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->regenerate();

        return response()->json([], 204);
    }
    

 

    private function storeLogoPersona(Request $request, $default = true)
    {
        $rutaFoto = null;

        if ($default) {
            $rutaFoto = Person::RUTA_FOTO_DEFAULT;
        }
        if ($request->hasFile('rutaFotoFile')) {
            $rutaFoto =
                '/storage/' .
                $request
                ->file('rutaFotoFile')
                ->store(Person::RUTA_FOTO, ['disk' => 'public']);
        }
        return $rutaFoto;
    }

}
