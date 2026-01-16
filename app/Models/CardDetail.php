<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardDetail extends Model
{
    use HasFactory;

    protected $table = "cardDetails";


    public function card()
    {
        return $this->belongsTo(Card::class, 'idCard');
    }

   
    public function configuracion()
    {
        return $this->belongsTo(ConfiguracionRecordatorio::class, 'idConfiguracionRecordatorio');
    }

    public function configuracionRepeat()
    {
        return $this->belongsTo(ConfiguracionRepeatCard::class, 'idConfiguracionRepeat');
    }
}
