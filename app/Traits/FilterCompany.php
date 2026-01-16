<?php

namespace App\Traits;

use Illuminate\Support\Facades\Session;

trait FilterCompany
{

    public function scopeByCompany($query)
    {
        return $query->where('company_id', Session::get('company_id'));
    }

    public function saveWithCompany()
    {
        $this->attributes['company_id'] = Session::get('company_id');
        $this->save();
    }
}

