<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\CentrosFormacion;

class User extends Authenticatable  implements JWTSubject
{
    use  HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $table = "usuario";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'idCentroFormacion'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    public function persona()
    {
        return $this->belongsTo(Person::class, 'idpersona');
    }

    public function activationCompanyUsers()
    {
        return $this->hasMany(ActivationCompanyUser::class, 'user_id');
    }

    public function centroFormacion()
    {
        return $this->belongsTo(CentrosFormacion::class, 'idCentroFormacion');
    }
    

    public function cards()
    {
        return $this->belongsToMany(Card::class, 'asignacionCardUsers', 'idUser', 'idCard');
    }


    public function chekItems()
    {
        return $this->belongsToMany(ChecklistItem::class, 'asignacionCheckItemUser', 'idUser', 'idCheckListItem');
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
