<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Exceptions\UnauthorizedException as ExceptionsUnauthorizedException;

class Permisions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permissionsList)
    {
        $permissions = is_array($permissionsList)
            ? $permissionsList
            : explode('|', $permissionsList);

        $permissionsText = Session::get('permissions');

        foreach ($permissions as $permission) {
            if (strrpos($permissionsText, $permission)) {
                return $next($request);
            }
        }


        return ExceptionsUnauthorizedException::forPermissions($permissions);;
    }
}
