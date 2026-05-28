<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GrupoMultimedia;
use App\Models\MultimediaHistorias;
use App\Models\Evento;
use Carbon\Carbon;

class MultimediaAndEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $idCompany = 1;
        $idUser = 1;

        // Seed some attendees (People) if none exist
        if (\App\Models\Person::count() < 5) {
            for ($k = 1; $k <= 5; $k++) {
                \App\Models\Person::create([
                    'identificacion' => 'ATTENDEE_' . $k . '_' . time(),
                    'nombre1' => 'Invitado',
                    'nombre2' => '',
                    'apellido1' => 'Número ' . $k,
                    'apellido2' => '',
                    'fechaNac' => '1995-01-01',
                    'direccion' => 'Calle Falsa 123',
                    'email' => "invitado{$k}@virtualt.org",
                    'telefonoFijo' => '5555555',
                    'celular' => '3000000000',
                    'perfil' => 'N/A',
                    'sexo' => 'M',
                    'rh' => 'O+',
                    'rutaFoto' => '/default/user.svg',
                    'idTipoIdentificacion' => 1,
                    'idCiudad' => 1,
                    'idCiudadNac' => 1,
                    'idCiudadUbicacion' => 1,
                ]);
            }
        }

        // Clean existing to avoid duplicates
        \App\Models\Item::query()->delete();
        \App\Models\ParticipanteEvento::query()->delete();
        Evento::where('idCompany', $idCompany)->delete();
        $gruposIds = GrupoMultimedia::where('idCompany', $idCompany)->pluck('id');
        MultimediaHistorias::whereIn('idGrupoMultimedia', $gruposIds)->delete();
        GrupoMultimedia::where('idCompany', $idCompany)->delete();

        // 1. Seed 7 Stories (Historias)
        $storyImages = [
            'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1511556532299-8f662fc26c06?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1531297484001-80022131f5a1?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=600&q=80'
        ];

        for ($i = 0; $i < 7; $i++) {
            $grupo = GrupoMultimedia::create([
                'idCompany'   => $idCompany,
                'nombreGrupo' => "Historia Premium #" . ($i + 1),
                'tipo'        => 'historia',
                'descripcion' => "Descripción detallada para la Historia Premium #" . ($i + 1),
                'idUser'      => $idUser,
                'created_at'  => Carbon::now()->subMinutes($i * 10), // Keep within 24h
            ]);

            MultimediaHistorias::create([
                'idGrupoMultimedia' => $grupo->id,
                'idCompany'         => $idCompany,
                'idUser'            => $idUser,
                'tipo'              => 'historia',
                'urlMultimedia'     => $storyImages[$i],
                'created_at'        => Carbon::now()->subMinutes($i * 10),
            ]);
        }

        // 2. Seed 7 Reels (Videos)
        $reelVideos = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=9bZkp7q19f0',
            'https://www.youtube.com/watch?v=kJQP7kiw5Fk',
            'https://www.youtube.com/watch?v=OPf0YbXqDm0',
            'https://www.youtube.com/watch?v=L_LUpnjgPso',
            'https://www.youtube.com/watch?v=tgbNymZ7vqY',
            'https://www.youtube.com/watch?v=V-_O7nl0Ii0'
        ];

        for ($i = 0; $i < 7; $i++) {
            $grupo = GrupoMultimedia::create([
                'idCompany'   => $idCompany,
                'nombreGrupo' => "Reel Innovador #" . ($i + 1),
                'tipo'        => 'reel',
                'descripcion' => "Descripción futurista del Reel #" . ($i + 1),
                'idUser'      => $idUser,
                'created_at'  => Carbon::now()->subHours($i * 2), // Keep within 72h
            ]);

            MultimediaHistorias::create([
                'idGrupoMultimedia' => $grupo->id,
                'idCompany'         => $idCompany,
                'idUser'            => $idUser,
                'tipo'              => 'reel',
                'urlMultimedia'     => $reelVideos[$i],
                'created_at'        => Carbon::now()->subHours($i * 2),
            ]);
        }

        // 3. Seed 7 Events (Eventos)
        $eventImages = [
            'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1515187029135-18ee286d815b?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?auto=format&fit=crop&w=600&q=80'
        ];

        $eventTitles = [
            'Simposio Global de IA 2026',
            'Hackathon Nacional VirtualT',
            'Taller de Diseño UI/UX Avanzado',
            'Conferencia Futuro del Trabajo',
            'Bootcamp Laravel & Vue 3',
            'Foro de Innovación Educativa',
            'Networking & Demo Day 2026'
        ];

        for ($i = 0; $i < 7; $i++) {
            // Distribute start dates: some live, some pending
            $startDate = Carbon::now()->addDays($i - 2); // Event 0 & 1 are live/past, 2 to 6 are upcoming/live
            $endDate = (clone $startDate)->addDays(2); // Spans 2 days

            $evento = Evento::create([
                'idCompany'    => $idCompany,
                'idUser'       => $idUser,
                'nombre'       => $eventTitles[$i],
                'descripcion'  => "Ven y participa en el evento '" . $eventTitles[$i] . "'. Aprenderemos sobre nuevas tecnologías y metodologías ágiles de desarrollo.",
                'fechaInicial' => $startDate->format('Y-m-d'),
                'fechaFinal'   => $endDate->format('Y-m-d'),
                'hora'         => '08:00:00',
                'hora_final'   => '18:00:00',
                'linkRegistro' => 'https://virtualt.org/registro',
                'tipoEvento'   => $i % 2 === 0 ? 'VIRTUAL' : 'PRESENCIAL',
                'estado'       => 'PENDIENTE',
                'esPublico'    => true,
                'idArea'       => null,
                'url'          => $eventImages[$i],
            ]);

            // Seed 3 Activities (Item) per Event
            for ($j = 1; $j <= 3; $j++) {
                $actStart = (clone $startDate)->setTime(8 + ($j * 2), 0);
                $actEnd = (clone $startDate)->setTime(9 + ($j * 2), 30);
                
                \App\Models\Item::create([
                    'nombreItem'  => "Actividad #{$j}: " . $eventTitles[$i],
                    'seleccionar' => true,
                    'descripcion' => "Detalles prácticos de la actividad #{$j} del evento {$eventTitles[$i]}.",
                    'hora_inicio' => $actStart,
                    'hora_fin'    => $actEnd,
                    'idEvento'    => $evento->idEvento,
                ]);
            }

            // Seed 3 Attendees (Participantes) per Event
            $people = \App\Models\Person::limit(3)->get();
            foreach ($people as $person) {
                \App\Models\ParticipanteEvento::create([
                    'idEvento'      => $evento->idEvento,
                    'idPersona'     => $person->id,
                    'fechaRegistro' => Carbon::now(),
                    'estado'        => 'CONFIRMADO',
                ]);
            }
        }
    }
}
