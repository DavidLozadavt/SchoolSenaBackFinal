<?php

namespace App\Models;

use App\Util\KeyUtil;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Session;

class ActivationCompanyUser extends Model
{
    use HasFactory, HasRoles;

    protected $guard_name = "web";

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function estado()
    {
        return $this->belongsTo(Status::class, 'state_id');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class, 'idComentario');
    }

    public function scopeActive($query)
    {
        $now = \Carbon\Carbon::now();
        return $query
            ->where('state_id', Status::ID_ACTIVE)
            ->whereDate('fechaInicio', '<=', $now)
            ->whereDate('fechaFin', '>=', $now);
    }

    public function scopeByUser($query, $idUser)
    {
        return $query->where('user_id', $idUser);
    }

    public function saveWithCompany()
    {
        $this->attributes['company_id'] = KeyUtil::idCompany();
        $this->save();
    }
}
