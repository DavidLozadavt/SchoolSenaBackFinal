<?php

namespace App\Models;

use App\Models\Nomina\Area;
use App\Models\Nomina\CentroCosto;
use App\Models\Nomina\HoraExtra;
use App\Models\Nomina\Nomina;
use App\Models\Nomina\SolicitudIncLicPersona;
use App\Models\Nomina\TarifaArl;
use App\Models\Nomina\Vacacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    protected $table = "contrato";
    public static $snakeAttributes = false;

    public function persona()
    {
        return $this->belongsTo(Person::class, 'idpersona');
    }

    public function tipoContrato()
    {
        return $this->belongsTo(ContractType::class, 'idtipoContrato');
    }

    public function documentosContrato()
    {
        return $this->hasMany(DocumentoContrato::class, 'idContrato');
    }

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idempresa');
    }

    public function salario()
    {
        return $this->belongsTo(Salario::class, 'salario_id');
    }

    public function transacciones()
    {
        return $this->belongsToMany(Transaccion::class, 'asignacion_contrato_transaccion', 'contrato_id', 'transaccion_id');
    }

    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }

    public function archivoContrato()
    {
        return $this->hasMany(ArchivoContrato::class, 'idContrato');
    }

    public function nominas(): HasMany
    {
        return $this->hasMany(Nomina::class, 'idContrato');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'idCentroCosto');
    }


    public function banco()
    {
        return $this->belongsTo(Banco::class, 'idBanco');
    }


    public function pension()
    {
        return $this->belongsTo(EntidadesSeguridadSocial::class, 'idPension');
    }

    public function pensionMovilidad() 
    {
        return $this->belongsTo(EntidadesSeguridadSocial::class, 'idPensionMovilidad');
    }

    public function arl()
    {
        return $this->belongsTo(EntidadesSeguridadSocial::class, 'idArl');
    }

    public function salud()
    {
        return $this->belongsTo(EntidadesSeguridadSocial::class, 'idSalud');
    }

    public function saludMovilidad()
    {
        return $this->belongsTo(EntidadesSeguridadSocial::class, 'idSaludMovilidad');
    }



    public function cajaCompensacion()
    {
        return $this->belongsTo(EntidadesSeguridadSocial::class, 'idCajaCompensacion');
    }

    public function cesantias()
    {
        return $this->belongsTo(EntidadesSeguridadSocial::class, 'idCesantias');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'idArea');
    }

    public function otrasDeducciones(): HasMany
    {
        return $this->hasMany(OtraDeduccion::class, 'idContrato');
    }

    public function tarifasRiesgo()
    {
        return $this->belongsTo(TarifaArl::class, 'idTarifaRiesgo');
    }

    public function actividadRiesgo()
    {
        return $this->belongsTo(ActividadRiesgoProfesional::class, 'idActividadRiesgo');
    }

    public function horasExtra()
    {
        return $this->hasMany(HoraExtra::class, 'idContrato', 'id');
    }

    public function vacaciones()
    {
        return $this->hasMany(Vacacion::class, 'idContrato', 'id');
    }

    public function solicitudIncLicPersonas()
    {
        return $this->hasMany(SolicitudIncLicPersona::class, 'idContrato', 'id');
    }

    public function tipoCotizante()
    {
        return $this->belongsTo(TipoCotizante::class, 'idTipoCotizante');
    }

    public function subTipoCotizante()
    {
        return $this->belongsTo(SubTipoCotizante::class, 'idSubTipoCotizante');
    }


    public function novedades()
    {
        return $this->hasMany(Novedad::class, 'idContrato', 'id');
    }

    public function nivelEducativo()
    {
        return $this->belongsTo(NivelEducativo::class, 'idNivelEducativo');
    }

    public function areasConocimiento(): BelongsToMany
    {
        return $this->belongsToMany(AreaConocimiento::class, 'asignacion_contrato_area_conocimiento', 'idContrato', 'idAreaConocimiento');
    }

    public function programas(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'asignacion_contrato_programa', 'idContrato', 'idPrograma');
    }

    public function horarioMateria()
    {
        return $this->hasMany(HorarioMateria::class, 'idContrato', 'id');
    }

    public function asignacionContratoAreaConocimiento()
    {
        return $this->hasMany(AsignacionContratoAreaConocimiento::class, 'idContrato', 'id');
    }
}
