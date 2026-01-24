<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
{
    DB::statement("
        ALTER TABLE asignacionperiodoprograma
        MODIFY pension TINYINT(1) NULL DEFAULT NULL
    ");
}

public function down(): void
{
    DB::statement("
        ALTER TABLE asignacionperiodoprograma
        MODIFY pension TINYINT(1) NOT NULL DEFAULT 1
    ");
}

};
