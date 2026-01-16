<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\IdentificationType;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Person::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $ciudad = City::inRandomOrder()->limit(3)->get();
        return [
            'identificacion' => $this->faker->numberBetween([1000, 100000]),
            'nombre1' => $this->faker->firstName,
            'nombre2' => $this->faker->firstName,
            'apellido1' => $this->faker->lastName,
            'apellido2' => $this->faker->lastName,
            'fechaNac' => $this->faker->date,
            'direccion' => $this->faker->streetAddress,
            'email' => $this->faker->safeEmail,
            'telefonoFijo' => $this->faker->phoneNumber,
            'celular' => $this->faker->phoneNumber,
            'perfil' => $this->faker->paragraph(),
            'sexo' => $this->faker->randomElement(['M', 'F', 'O']),
            'rh' => $this->faker->randomElement(["O+", "O-", "A+", "O-"]),
            'rutaFoto' => '/default/user.svg',
            'idTipoIdentificacion' => IdentificationType::inRandomOrder()->first()->id,
            'idCiudad' => $ciudad[0]->id,
            'idCiudadNac' => $ciudad[1]->id,
            'idCiudadUbicacion' => $ciudad[2]->id
        ];
    }
}
