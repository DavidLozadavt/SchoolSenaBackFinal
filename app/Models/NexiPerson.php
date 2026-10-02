<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexiPerson extends Model
{
    protected $table = 'persona';
    protected $connection = 'mysql_nexiservice';
    protected $guarded = [];
}
