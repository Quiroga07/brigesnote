<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncLocationsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Valida la sincronización exitosa de regiones, provincias, distritos y colegios
     * usando Http::fake() con payloads sintéticos (sin tocar el servicio en vivo de MINEDU).
     */
    public function test_sync_locations_command_populates_database_from_faked_api()
    {
        Http::fake([
            'https://escale.minedu.gob.pe/padron/rest/regiones' => Http::response([
                'region' => [
                    [
                        'idRegion' => '15',
                        'nombreRegion' => 'LIMA',
                        'pointX' => -12.046374,
                        'pointY' => -77.042793,
                        'zoom' => 10,
                    ],
                ],
            ], 200),

            'https://escale.minedu.gob.pe/padron/rest/provincias*' => Http::response([
                'provincia' => [
                    'idProvincia' => '1501',
                    'nombreProvincia' => 'LIMA',
                    'pointX' => -12.046374,
                    'pointY' => -77.042793,
                ],
            ], 200),

            'https://escale.minedu.gob.pe/padron/rest/distritos*' => Http::response([
                'distrito' => [
                    'idDistrito' => '150101',
                    'nombreDistrito' => 'LIMA',
                ],
            ], 200),

            'https://escale.minedu.gob.pe/padron/rest/instituciones*' => Http::response([
                'items' => [
                    'codMod' => '0273572',
                    'cenEdu' => 'JUAN JIMENEZ PIMENTEL',
                    'cenPob' => 'LIMA',
                    'dirCen' => 'JR. LAMPA 240',
                    'director' => 'PEDRO PEREZ',
                    'dis_crecer' => null,
                    'dis_juntos' => null,
                ],
            ], 200),
        ]);

        $this->artisan('sync:locations')
            ->assertExitCode(0);

        $this->assertDatabaseHas('regions', [
            'idRegion' => '15',
            'nombreRegion' => 'LIMA',
        ]);

        $this->assertDatabaseHas('provinces', [
            'idProvincia' => '1501',
            'nombreProvincia' => 'LIMA',
        ]);

        $this->assertDatabaseHas('districts', [
            'idDistrito' => '150101',
            'nombreDistrito' => 'LIMA',
        ]);

        $this->assertDatabaseHas('schools', [
            'codMod' => '0273572',
            'idDistrito' => '150101',
            'nombreCenEdu' => 'JUAN JIMENEZ PIMENTEL',
        ]);
    }

    /**
     * Valida manejo de error cuando la API remota falla (500 Internal Server Error)
     * asegurando que no se produzcan excepciones no controladas.
     */
    public function test_sync_locations_command_handles_api_failure_gracefully()
    {
        Http::fake([
            'https://escale.minedu.gob.pe/padron/rest/regiones' => Http::response(null, 500),
        ]);

        $this->artisan('sync:locations')
            ->assertExitCode(0);

        $this->assertDatabaseCount('regions', 0);
    }
}

