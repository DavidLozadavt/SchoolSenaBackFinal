<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $medios = ['PSE', 'WOMPI', 'PAGO EN LINEA'];

        foreach ($medios as $nombre) {
            $exists = DB::table('medioPago')
                ->where('detalleMedioPago', $nombre)
                ->exists();

            if (!$exists) {
                DB::table('medioPago')->insert(['detalleMedioPago' => $nombre]);
            }
        }
    }

    public function down(): void
    {
        DB::table('medioPago')
            ->whereIn('detalleMedioPago', ['PSE', 'WOMPI', 'PAGO EN LINEA'])
            ->delete();
    }
};
