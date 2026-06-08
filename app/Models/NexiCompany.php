<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexiCompany extends Model
{
    protected $table = 'empresa';
    protected $connection = 'mysql_nexiservice';
    protected $guarded = [];
}
