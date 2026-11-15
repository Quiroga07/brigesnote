<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Evitar cualquier petición externa incidental asegurando que nunca se contacte a MINEDU/ESCALE
        Http::fake([
            'https://escale.minedu.gob.pe/*' => function () {
                throw new \RuntimeException('Live connection to MINEDU/ESCALE API is blocked in tests.');
            },
        ]);

        // Soporte para SQLite en memoria (LocationController usa la función LEFT nativa de MySQL)
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('LEFT', function ($string, $length) {
                if ($string === null) {
                    return '';
                }
                return substr($string, 0, $length);
            });
        }
    }

    /**
     * Endpoint 1: GET /api/regiones
     * Valida respuesta vacía cuando no existen registros en la base de datos.
     */
    public function test_get_regions_returns_empty_data_array_when_no_records_exist()
    {
        $response = $this->getJson('/api/regiones');

        $response->assertStatus(200)
            ->assertExactJson([
                'status' => 'success',
                'data' => [],
            ]);
    }

    /**
     * Endpoint 1: GET /api/regiones
     * Valida estructura JSON, campos y código 200 con datos sintéticos.
     */
    public function test_get_regions_returns_all_regions_with_correct_structure()
    {
        Region::create([
            'idRegion' => '15',
            'nombreRegion' => 'LIMA',
            'pointX' => -12.046374,
            'pointY' => -77.042793,
            'zoom' => 10,
        ]);

        Region::create([
            'idRegion' => '04',
            'nombreRegion' => 'AREQUIPA',
            'pointX' => -16.39889,
            'pointY' => -71.535,
            'zoom' => 11,
        ]);

        $response = $this->getJson('/api/regiones');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'idRegion' => '15',
                'nombreRegion' => 'LIMA',
            ])
            ->assertJsonFragment([
                'idRegion' => '04',
                'nombreRegion' => 'AREQUIPA',
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'idRegion',
                        'nombreRegion',
                        'pointX',
                        'pointY',
                        'zoom',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    /**
     * Endpoint 1: POST /api/regiones
     * Valida rechazo de métodos HTTP no permitidos.
     */
    public function test_post_regions_is_not_allowed()
    {
        $response = $this->postJson('/api/regiones', []);

        $response->assertStatus(405);
    }

    /**
     * Endpoint 2: GET /api/provincias/{idRegion}
     * Valida filtrado de provincias por prefijo de región (LEFT(idProvincia, 2) = idRegion).
     */
    public function test_get_provinces_by_region_returns_filtered_results()
    {
        Province::create([
            'idProvincia' => '1501',
            'nombreProvincia' => 'LIMA',
            'pointX' => -12.046374,
            'pointY' => -77.042793,
        ]);

        Province::create([
            'idProvincia' => '1502',
            'nombreProvincia' => 'BARRANCA',
            'pointX' => -10.75,
            'pointY' => -77.76,
        ]);

        // Provincia de otra región que no debe aparecer
        Province::create([
            'idProvincia' => '0401',
            'nombreProvincia' => 'AREQUIPA',
            'pointX' => -16.4,
            'pointY' => -71.5,
        ]);

        $response = $this->getJson('/api/provincias/15');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'idProvincia' => '1501',
                'nombreProvincia' => 'LIMA',
            ])
            ->assertJsonFragment([
                'idProvincia' => '1502',
                'nombreProvincia' => 'BARRANCA',
            ])
            ->assertJsonMissing([
                'idProvincia' => '0401',
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'idProvincia',
                        'nombreProvincia',
                        'pointX',
                        'pointY',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    /**
     * Endpoint 2: GET /api/provincias/{idRegion}
     * Valida código 404 y mensaje exacto cuando la región no tiene provincias.
     */
    public function test_get_provinces_returns_404_when_no_matching_records_found()
    {
        $response = $this->getJson('/api/provincias/99');

        $response->assertStatus(404)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron provincias para esta región.',
            ]);
    }

    /**
     * Endpoint 3: GET /api/distritos/{idProvince}
     * Valida filtrado de distritos por prefijo provincial (LEFT(idDistrito, 4) = idProvince).
     */
    public function test_get_districts_by_province_returns_filtered_results()
    {
        District::create([
            'idDistrito' => '150101',
            'nombreDistrito' => 'LIMA',
        ]);

        District::create([
            'idDistrito' => '150102',
            'nombreDistrito' => 'ANCON',
        ]);

        // Distrito de otra provincia que no debe aparecer
        District::create([
            'idDistrito' => '150201',
            'nombreDistrito' => 'BARRANCA',
        ]);

        $response = $this->getJson('/api/distritos/1501');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'idDistrito' => '150101',
                'nombreDistrito' => 'LIMA',
            ])
            ->assertJsonFragment([
                'idDistrito' => '150102',
                'nombreDistrito' => 'ANCON',
            ])
            ->assertJsonMissing([
                'idDistrito' => '150201',
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'idDistrito',
                        'nombreDistrito',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    /**
     * Endpoint 3: GET /api/distritos/{idProvince}
     * Valida código 404 y mensaje exacto cuando la provincia no tiene distritos.
     */
    public function test_get_districts_returns_404_when_no_matching_records_found()
    {
        $response = $this->getJson('/api/distritos/9999');

        $response->assertStatus(404)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron distritos para esta provincia.',
            ]);
    }

    /**
     * Endpoint 4: GET /api/colegios/{ubigeo}
     * Valida filtrado de escuelas por ubigeo exacto (idDistrito).
     */
    public function test_get_schools_by_ubigeo_returns_filtered_results()
    {
        School::create([
            'codMod' => '0273572',
            'idDistrito' => '150101',
            'nombreCenEdu' => 'JUAN JIMENEZ PIMENTEL',
            'cenPob' => 'LIMA',
            'dirCen' => 'JR. LAMPA 240',
            'director' => 'PEDRO PEREZ',
            'dis_crecer' => null,
            'dis_juntos' => null,
        ]);

        School::create([
            'codMod' => '0273580',
            'idDistrito' => '150101',
            'nombreCenEdu' => 'COLEGIO GUADALUPE',
            'cenPob' => 'LIMA',
            'dirCen' => 'AV. ALFONSO UGARTE',
            'director' => 'ANA LOPEZ',
            'dis_crecer' => '1',
            'dis_juntos' => null,
        ]);

        // Colegio de otro distrito que no debe ser retornado
        School::create([
            'codMod' => '0599999',
            'idDistrito' => '150102',
            'nombreCenEdu' => 'COLEGIO ANCON',
            'cenPob' => 'ANCON',
            'dirCen' => 'CALLE REAL 100',
            'director' => null,
        ]);

        $response = $this->getJson('/api/colegios/150101');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'codMod' => '0273572',
                'idDistrito' => '150101',
                'nombreCenEdu' => 'JUAN JIMENEZ PIMENTEL',
            ])
            ->assertJsonFragment([
                'codMod' => '0273580',
                'idDistrito' => '150101',
                'nombreCenEdu' => 'COLEGIO GUADALUPE',
            ])
            ->assertJsonMissing([
                'codMod' => '0599999',
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'codMod',
                        'idDistrito',
                        'nombreCenEdu',
                        'cenPob',
                        'dirCen',
                        'director',
                        'dis_crecer',
                        'dis_juntos',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    /**
     * Endpoint 4: GET /api/colegios/{ubigeo}
     * Valida código 404 y mensaje exacto cuando el distrito no tiene escuelas registradas.
     */
    public function test_get_schools_returns_404_when_no_schools_found_for_ubigeo()
    {
        $response = $this->getJson('/api/colegios/999999');

        $response->assertStatus(404)
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron escuelas para este distrito.',
            ]);
    }

    /**
     * Endpoint Auxiliar: GET /api/user (auth:api)
     * Valida que peticiones no autenticadas sean rechazadas con 401 Unauthorized.
     */
    public function test_get_user_without_authentication_returns_401()
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }
}

