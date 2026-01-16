<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Company::factory()->create([
            'razonSocial' => "Virtual T"
        ]);

        \App\Models\Company::factory()->create([
            'razonSocial' => "Rápido Tambo",
            'rutaLogo' => '/default/rapido-tambo-logo.jpg'
        ]);

        \App\Models\Company::factory(10)->create();
    }
}
