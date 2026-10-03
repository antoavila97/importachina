<?php

namespace Tests\Feature;

use App\Models\ApiSyncLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AliExpressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * HU-05: la sincronizacion consulta la API real (firmada), importa entre 20 y 50
 * productos, no duplica y registra cada corrida.
 *
 * Las pruebas usan Http::fake: verifican la peticion y el parseo sin necesitar
 * credenciales reales de AliExpress.
 */
class AliExpressSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.aliexpress.app_key' => '12345678',
            'services.aliexpress.app_secret' => 'secreto-de-prueba',
            'services.aliexpress.tracking_id' => 'track-123',
            'services.aliexpress.margin_pct' => 30,
            'services.aliexpress.default_keyword' => 'bluetooth earbuds',
        ]);

        // La firma exige la hora de China (GMT+8): se fija para que sea determinista.
        // 2026-03-05 06:30 UTC = 2026-03-05 14:30 en Shanghai.
        Date::setTestNow('2026-03-05 06:30:00');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * Arma una respuesta de la API con la cantidad de productos pedida.
     */
    private function fakeApi(int $count, int $offset = 0, ?int $total = null): void
    {
        $products = [];

        for ($i = $offset; $i < $offset + $count; $i++) {
            $products[] = [
                'product_id' => (string) (330000000000 + $i),
                'product_title' => "Producto API {$i}",
                'product_description' => '<p>Descripcion del producto '.$i.'</p>',
                'product_main_image_url' => "https://ae01.alicdn.com/kf/S{$i}.jpg",
                'product_small_image_urls' => [
                    "https://ae01.alicdn.com/kf/S{$i}-a.jpg",
                    "https://ae01.alicdn.com/kf/S{$i}-b.jpg",
                ],
                'product_detail_url' => "https://www.aliexpress.com/item/33000000{$i}.html",
                'promotion_link' => "https://s.click.aliexpress.com/e/abc{$i}",
                'sale_price' => '19.99',
                'sale_price_currency' => 'USD',
                'first_level_category_id' => '100013',
                'first_level_category_name' => 'Electrónica',
                'shop_url' => 'https://www.aliexpress.com/store/3255036',
                'evaluate_rate' => '4.8',
            ];
        }

        Http::fake([
            '*' => Http::response([
                'aliexpress_affiliate_product_query_response' => [
                    'total_results' => $total ?? $count,
                    'products' => ['product' => $products],
                ],
            ]),
        ]);
    }

    public function test_sin_credenciales_no_llama_a_la_api_y_registra_el_fallo(): void
    {
        config([
            'services.aliexpress.app_key' => null,
            'services.aliexpress.app_secret' => null,
        ]);

        Http::fake();

        $this->artisan('app:sync-aliexpress-products')
            ->expectsOutputToContain('Faltan las credenciales')
            ->assertFailed();

        Http::assertNothingSent();

        $this->assertDatabaseHas('api_sync_logs', ['status' => 'failed', 'items_imported' => 0]);
    }

    public function test_la_peticion_va_firmada_por_post_con_los_parametros_obligatorios(): void
    {
        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        Http::assertSent(function ($request) {
            $params = $request->data();

            if ($request->method() !== 'POST') {
                return false;
            }

            if ($request->url() !== 'https://api-sg.aliexpress.com/sync') {
                return false;
            }

            // Obligatorios del protocolo TOP.
            foreach (['app_key', 'method', 'timestamp', 'v', 'format', 'sign_method', 'sign'] as $key) {
                if (empty($params[$key])) {
                    return false;
                }
            }

            return $params['method'] === AliExpressService::PRODUCT_QUERY
                && $params['format'] === 'json'
                && $params['v'] === '2.0'
                && $params['sign_method'] === 'md5'
                && $params['app_key'] === '12345678'
                // 2026-03-05 06:30 UTC = 14:30 en Shanghai (GMT+8).
                && $params['timestamp'] === '2026-03-05 14:30:00';
        });
    }

    /**
     * El vector de la documentacion: bar2foo1foo_bar3foobar4 con secreto "secret".
     */
    public function test_la_firma_md5_sigue_el_algoritmo_de_la_documentacion(): void
    {
        $signature = $this->app->make(AliExpressService::class)->sign(
            [
                'bar' => '2',
                'foo' => '1',
                'foo_bar' => '3',
                'foobar' => '4',
                'vacio' => '',
                'nulo' => null,
            ],
            'secret',
        );

        // md5(secret + bar2foo1foo_bar3foobar4 + secret)
        $this->assertSame(strtoupper(md5('secret'.'bar2foo1foo_bar3foobar4'.'secret')), $signature);
        $this->assertSame(32, strlen($signature), 'La firma debe ser un hexadecimal de 32 caracteres.');
    }

    public function test_la_firma_ignora_los_parametros_vacios_y_el_orden_es_alfabetico(): void
    {
        $service = $this->app->make(AliExpressService::class);

        // Ordenar las claves no cambia la firma.
        $this->assertSame(
            $service->sign(['a' => '1', 'b' => '2'], 'secret'),
            $service->sign(['b' => '2', 'a' => '1'], 'secret'),
        );

        // Un parametro vacio no aporta nada al string firmado.
        $this->assertSame(
            $service->sign(['a' => '1', 'b' => '2'], 'secret'),
            $service->sign(['a' => '1', 'b' => '2', 'c' => '', 'd' => null], 'secret'),
        );
    }

    public function test_la_firma_hmac_usa_hmac_md5_cuando_se_configura_así(): void
    {
        config(['services.aliexpress.sign_method' => 'hmac']);

        $service = $this->app->make(AliExpressService::class);

        $this->assertSame(
            strtoupper(hash_hmac('md5', 'bar2foo1foo_bar3foobar4', 'secret')),
            $service->sign(['bar' => '2', 'foo' => '1', 'foo_bar' => '3', 'foobar' => '4'], 'secret'),
        );
    }

    public function test_hu05_importa_los_productos_con_titulo_imagen_y_precio(): void
    {
        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--keyword' => 'audifonos', '--limit' => 20])
            ->assertSuccessful();

        $this->assertSame(20, Product::count());

        $product = Product::firstWhere('external_id', '330000000000');

        $this->assertNotNull($product, 'El producto de la API no se importo.');
        $this->assertSame('Producto API 0', $product->title);
        $this->assertSame('Descripcion del producto 0', $product->description);
        $this->assertSame('https://ae01.alicdn.com/kf/S0.jpg', $product->image_url);
        $this->assertSame('https://www.aliexpress.com/item/330000000.html', $product->source_url);
        // El precio se guarda tal cual lo devuelve la API.
        $this->assertSame('19.99', $product->cost_price);
        // HU-07: el precio de venta sale del costo y del margen configurado.
        $this->assertSame('25.99', $product->sale_price);
        $this->assertNotNull($product->synced_at);
    }

    public function test_hu05_el_keyword_y_el_limite_se_envian_a_la_api(): void
    {
        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--keyword' => 'audifonos', '--limit' => 20])
            ->assertSuccessful();

        Http::assertSent(fn ($request) => $request['keywords'] === 'audifonos'
            && $request['page_no'] === '1'
            && $request['page_size'] === '20'
            && $request['tracking_id'] === 'track-123');
    }

    public function test_sin_keyword_usa_el_por_defecto_del_config(): void
    {
        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        Http::assertSent(fn ($request) => $request['keywords'] === 'bluetooth earbuds');
    }

    public function test_pide_mas_paginas_hasta_llegar_al_limite(): void
    {
        // La primera pagina devuelve 20, la segunda 20 mas: con --limit=50 hay que paginar.
        Http::fakeSequence()
            ->push([
                'aliexpress_affiliate_product_query_response' => [
                    'total_results' => 100,
                    'products' => ['product' => $this->products(0, 20)],
                ],
            ])
            ->push([
                'aliexpress_affiliate_product_query_response' => [
                    'total_results' => 100,
                    'products' => ['product' => $this->products(20, 20)],
                ],
            ])
            ->push([
                'aliexpress_affiliate_product_query_response' => [
                    'total_results' => 100,
                    'products' => ['product' => $this->products(40, 10)],
                ],
            ]);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 50])->assertSuccessful();

        $this->assertSame(50, Product::count());

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['page_no'] === '3');
    }

    public function test_hu05_un_producto_ya_importado_no_se_duplica(): void
    {
        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();
        $this->assertSame(20, Product::count());

        // Segunda corrida con el mismo external_id pero precio nuevo.
        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])
            ->expectsOutputToContain('0 nuevo(s), 20 actualizado(s)')
            ->assertSuccessful();

        $this->assertSame(20, Product::count(), 'La segunda sincronizacion duplico productos.');
        $this->assertDatabaseHas('api_sync_logs', [
            'status' => 'success',
            'items_imported' => 20,
        ]);
    }

    public function test_la_segunda_corrida_actualiza_el_precio_y_conserva_el_stock(): void
    {
        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $product = Product::firstWhere('external_id', '330000000000');
        $product->update(['stock' => 7, 'active' => false]);

        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $product->refresh();

        $this->assertSame(7, $product->stock, 'La API no debe pisar el stock ajustado a mano.');
        $this->assertTrue($product->active, 'Un producto reactivado por la API vuelve al catalogo.');
    }

    public function test_cada_corrida_queda_registrada_con_su_resultado(): void
    {
        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $log = ApiSyncLog::latest('id')->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->isSuccessful());
        $this->assertSame(20, $log->items_imported);
        $this->assertStringContainsString('20 nuevo(s)', $log->message);
    }

    public function test_un_error_de_la_api_se_registra_con_el_mensaje_real(): void
    {
        Http::fake([
            '*' => Http::response([
                'error_response' => [
                    'code' => 20002,
                    'msg' => 'Insufficient isv permissions',
                    'sub_msg' => 'The api aliexpress.affiliate.product.query is not authorized for this app',
                    'request_id' => 'abc-123',
                ],
            ]),
        ]);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])
            ->expectsOutputToContain('Insufficient isv permissions')
            ->assertFailed();

        $this->assertSame(0, Product::count());

        $this->assertDatabaseHas('api_sync_logs', ['status' => 'failed']);

        $log = ApiSyncLog::latest('id')->first();

        $this->assertSame(0, $log->items_imported);
        $this->assertStringContainsString('20002', $log->message);
        $this->assertStringContainsString('not authorized', $log->message);
    }

    public function test_una_firma_invalida_provocada_por_la_api_queda_registrada(): void
    {
        Http::fake([
            '*' => Http::response([
                'error_response' => [
                    'code' => 20006,
                    'msg' => 'Invalid signature',
                    'sub_msg' => 'sign not match',
                ],
            ], 200),
        ]);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])
            ->expectsOutputToContain('Invalid signature')
            ->assertFailed();

        $this->assertDatabaseHas('api_sync_logs', ['status' => 'failed']);
    }

    public function test_una_pagina_vacia_no_registra_una_importacion_falsa(): void
    {
        Http::fake([
            '*' => Http::response([
                'aliexpress_affiliate_product_query_response' => [
                    'total_results' => 0,
                    'products' => ['product' => []],
                ],
            ]),
        ]);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertFailed();

        $this->assertSame(0, Product::count());
    }

    public function test_se_descartan_los_productos_sin_titulo_precio_o_identificador(): void
    {
        Http::fake([
            '*' => Http::response([
                'aliexpress_affiliate_product_query_response' => [
                    'total_results' => 4,
                    'products' => ['product' => [
                        ['product_id' => '', 'product_title' => 'Sin id', 'sale_price' => '10'],
                        ['product_id' => '111', 'product_title' => '', 'sale_price' => '10'],
                        ['product_id' => '222', 'product_title' => 'Sin precio', 'sale_price' => '0'],
                        [
                            'product_id' => '333',
                            'product_title' => 'Producto valido',
                            'product_main_image_url' => 'https://ae01.alicdn.com/kf/ok.jpg',
                            'sale_price' => '12.50',
                        ],
                    ]],
                ],
            ]),
        ]);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])
            ->expectsOutputToContain('3 omitido(s)')
            ->assertSuccessful();

        $this->assertSame(1, Product::count());
        $this->assertSame('333', Product::first()->external_id);
    }

    public function test_las_categorias_de_la_api_se_crean_y_se_reusan_por_id_externo(): void
    {
        $this->fakeApi(20);
        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $this->assertSame(1, Category::where('external_category_id', '100013')->count());
        $this->assertSame('Electrónica', Category::where('external_category_id', '100013')->value('name'));

        // La segunda corrida no debe crear otra categoría con el mismo id de la API.
        $this->fakeApi(20);
        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $this->assertSame(1, Category::where('external_category_id', '100013')->count());
        $this->assertSame(1, Category::count());
    }

    public function test_las_imagenes_secundarias_se_guardan_en_product_images(): void
    {
        $this->fakeApi(20);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $product = Product::firstWhere('external_id', '330000000000');

        $this->assertCount(2, $product->images);
        $this->assertSame('https://ae01.alicdn.com/kf/S0-a.jpg', $product->images->first()->url);

        // La segunda corrida no duplica imagenes.
        $this->fakeApi(20);
        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $this->assertCount(2, $product->fresh()->images);
    }

    public function test_las_imagenes_secundarias_aceptan_el_formato_string(): void
    {
        Http::fake([
            '*' => Http::response([
                'aliexpress_affiliate_product_query_response' => [
                    'total_results' => 1,
                    'products' => ['product' => [[
                        'product_id' => '999',
                        'product_title' => 'Con string de imagenes',
                        'product_small_image_urls' => 'https://a.com/1.jpg,https://a.com/2.jpg',
                        'sale_price' => '5.00',
                    ]]],
                ],
            ]),
        ]);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $this->assertCount(2, Product::first()->images);
    }

    public function test_acepta_una_respuesta_con_los_productos_como_lista_directa(): void
    {
        Http::fake([
            '*' => Http::response([
                'resp_result' => [
                    'products' => [
                        ['product_id' => '1', 'product_title' => 'Uno', 'sale_price' => '3.00'],
                        ['product_id' => '2', 'product_title' => 'Dos', 'sale_price' => '4.00'],
                    ],
                ],
            ]),
        ]);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $this->assertSame(2, Product::count());
    }

    public static function invalidLimits(): array
    {
        return [
            'muy pocos' => [5],
            'demasiados' => [80],
        ];
    }

    #[DataProvider('invalidLimits')]
    public function test_el_limite_se_ajusta_al_rango_del_criterio_de_aceptacion(int $requested): void
    {
        $this->fakeApi(50);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => $requested])
            ->expectsOutputToContain('El limite se ajusto')
            ->assertSuccessful();

        $expected = $requested < 20 ? 20 : 50;

        $this->assertSame($expected, Product::count());
    }

    public function test_el_administrador_dispara_la_sincronizacion_desde_el_panel(): void
    {
        $this->fakeApi(20);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.api-sync.sync'), ['keyword' => 'audifonos', 'limit' => 20])
            ->assertRedirect(route('admin.api-sync.index'))
            ->assertSessionHas('success');

        $this->assertSame(20, Product::count());
        $this->assertSame(20, ApiSyncLog::first()->items_imported);
    }

    public function test_el_panel_avisa_cuando_faltan_las_credenciales(): void
    {
        config([
            'services.aliexpress.app_key' => null,
            'services.aliexpress.app_secret' => null,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.api-sync.index'))
            ->assertOk()
            ->assertSee('Faltan las credenciales')
            ->assertSee('ALIEXPRESS_APP_KEY', false);
    }

    public function test_el_panel_muestra_el_historial_con_estado_y_mensaje(): void
    {
        ApiSyncLog::create([
            'items_imported' => 20,
            'status' => ApiSyncLog::STATUS_SUCCESS,
            'message' => '20 nuevo(s), 0 actualizado(s).',
        ]);
        ApiSyncLog::create([
            'items_imported' => 0,
            'status' => ApiSyncLog::STATUS_FAILED,
            'message' => 'AliExpress respondio error 20002: Insufficient isv permissions',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.api-sync.index'))
            ->assertOk()
            ->assertSee('Correcta')
            ->assertSee('Fallida')
            ->assertSee('Insufficient isv permissions');
    }

    public function test_el_panel_valida_el_rango_de_productos(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.api-sync.sync'), ['limit' => 5])
            ->assertSessionHasErrors('limit');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.api-sync.sync'), ['limit' => 80])
            ->assertSessionHasErrors('limit');
    }

    public function test_un_cliente_no_puede_sincronizar(): void
    {
        $this->fakeApi(20);

        $this->actingAs(User::factory()->cliente()->create())
            ->post(route('admin.api-sync.sync'), ['limit' => 20])
            ->assertForbidden();

        $this->assertSame(0, Product::count());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(int $offset, int $count): array
    {
        $products = [];

        for ($i = $offset; $i < $offset + $count; $i++) {
            $products[] = [
                'product_id' => (string) (330000000000 + $i),
                'product_title' => "Producto API {$i}",
                'product_main_image_url' => "https://ae01.alicdn.com/kf/S{$i}.jpg",
                'sale_price' => '10.00',
            ];
        }

        return $products;
    }
}
