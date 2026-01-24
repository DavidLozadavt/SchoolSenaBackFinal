<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $path = 'database/seeders/sql/countries.sql';
        DB::unprepared(file_get_contents($path));
        $path = 'database/seeders/sql/cities.sql';
        DB::unprepared(file_get_contents($path));
        $path = 'database/seeders/sql/statuses.sql';
        DB::unprepared(file_get_contents($path));
        $path = 'database/seeders/sql/identification_types.sql';
        DB::unprepared(file_get_contents($path));
        $path = 'database/seeders/sql/datosTipoTransaccion.sql';
        DB::unprepared(file_get_contents($path));
        $path = 'database/seeders/sql/medio_pago.sql';
        DB::unprepared(file_get_contents($path));
        $path = 'database/seeders/sql/tipo_pago.sql';
        DB::unprepared(file_get_contents($path));

        $this->call(CompanySeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(PersonSeeder::class);
        $this->call(CommonSeeder::class);

        
        $this->call(NivelesEducativosSeeder::class);
        $this->call(TiposFormacionSeeder::class);
        $this->call(EstadosProgramaSeeder::class);
        $this->call([TipoJornadaSeeder::class]);
        $this->call([PermissionsSeeder::class,]);
        $this->call([
        TipoGradoSeeder::class,
        
    ]);
        $this->call(TipoInfraestructuraSeeder::class);
    }
}
