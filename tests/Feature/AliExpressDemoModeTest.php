<?php

namespace Tests\Feature;

use App\Models\ApiSyncLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AliExpressDemoCatalog;
use App\Services\AliExpressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HU-05 en modo demostracion: la API real de AliExpress exige verificar un
 * numero de celular y Bolivia no esta entre los paises soportados, asi que
 * sin credenciales la sincronizacion corre contra un catalogo local con la
 * misma forma de la respuesta de la API.
 *
 * El modo demo nunca tapa credenciales reales: si hay app_key y app_secret,
 * manda la API (ultimo test).
 */
class AliExpressDemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.aliexpress.app_key' => null,
            'services.aliexpress.app_secret' => null,
            'services.aliexpress.demo' => true,
            'services.aliexpress.margin_pct' => 30,
        ]);
    }

    public function test_el_modo_demo_importa_productos_sin_credenciales(): void
    {
        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])
            ->expectsOutputToContain('Modo demostración')
            ->assertSuccessful();

        $this->assertSame(20, Product::count());

        $this->assertSame(
            [],
            Product::where('external_id', 'not like', AliExpressDemoCatalog::ID_PREFIX.'%')->pluck('external_id')->all(),
        );
    }

    public function test_el_modo_demo_respeta_el_maximo_de_50_y_trae_todo_el_catalogo(): void
    {
        $this->artisan('app:sync-aliexpress-products', ['--limit' => 50])->assertSuccessful();

        // El catalogo demo tiene 24 productos: la paginacion de 20 en 20 sigue funcionando.
        $this->assertSame(24, Product::count());
        $this->assertStringContainsString('24 nuevo(s)', ApiSyncLog::first()->message);
    }

    public function test_el_modo_demo_no_duplica_al_volver_a_correr(): void
    {
        $this->artisan('app:sync-aliexpress-products', ['--limit' => 50])->assertSuccessful();

        $antes = Product::count();

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 50])->assertSuccessful();

        $this->assertSame($antes, Product::count());
        $this->assertSame(24, ApiSyncLog::latest('id')->first()->items_imported);
    }

    public function test_el_registro_de_la_corrida_queda_marcado_como_demo(): void
    {
        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $log = ApiSyncLog::first();

        $this->assertSame(ApiSyncLog::STATUS_SUCCESS, $log->status);
        $this->assertStringContainsString('modo demostración', $log->message);
    }

    public function test_el_modo_demo_calcula_el_precio_de_venta_con_el_margen(): void
    {
        config(['services.aliexpress.margin_pct' => 30]);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $product = Product::where('title', 'Auriculares inalámbricos Bluetooth 5.3 con estuche de carga')->firstOrFail();

        $this->assertSame(12.90, (float) $product->cost_price);
        $this->assertSame(30.0, (float) $product->margin_pct);
        $this->assertSame(16.77, (float) $product->sale_price);
        $this->assertNotNull($product->synced_at);
    }

    public function test_el_modo_demo_crea_las_categorias_de_la_api(): void
    {
        $this->artisan('app:sync-aliexpress-products', ['--limit' => 50])->assertSuccessful();

        $this->assertDatabaseHas('categories', ['external_category_id' => '100013', 'name' => 'Electrónica']);
        $this->assertDatabaseHas('categories', ['external_category_id' => '100009']);
        $this->assertDatabaseHas('categories', ['external_category_id' => '100020']);
        $this->assertDatabaseHas('categories', ['external_category_id' => '100017']);
        $this->assertSame(4, Category::count());
    }

    public function test_el_panel_muestra_el_aviso_de_modo_demo_y_habilita_el_boton(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.api-sync.index'));

        $response->assertOk()
            ->assertSee('Modo demostración')
            ->assertSee('Bolivia')
            ->assertDontSee('Faltan las credenciales')
            ->assertSee('Sincronizar productos');

        // El boton no puede quedar deshabilitado en modo demo. Ojo: la clase
        // "disabled:opacity-50" del Tailwind contiene la palabra disabled,
        // por eso el lookahead exige un atributo de verdad (=, espacio o >).
        $this->assertDoesNotMatchRegularExpression(
            '/<button type="submit"(?:(?!>).)*\sdisabled(?=[\s=>])/s',
            $response->getContent(),
        );
    }

    public function test_el_panel_puede_disparar_la_sincronizacion_desde_el_formulario(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.api-sync.sync'), ['limit' => 20])
            ->assertRedirect(route('admin.api-sync.index'))
            ->assertSessionHas('success');

        $this->assertSame(20, Product::count());
        $this->assertStringContainsString('modo demostración', ApiSyncLog::first()->message);
    }

    public function test_sin_credenciales_y_sin_demo_sigue_fallando(): void
    {
        config(['services.aliexpress.demo' => false]);

        $this->artisan('app:sync-aliexpress-products')
            ->expectsOutputToContain('Faltan las credenciales')
            ->assertFailed();

        $this->assertSame(0, Product::count());
        $this->assertSame(ApiSyncLog::STATUS_FAILED, ApiSyncLog::first()->status);
    }

    public function test_el_panel_sin_credenciales_y_sin_demo_sigue_avisando(): void
    {
        config(['services.aliexpress.demo' => false]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.api-sync.index'))
            ->assertOk()
            ->assertSee('Faltan las credenciales')
            ->assertDontSee('Modo demostración');
    }

    public function test_las_credenciales_reales_tienen_prioridad_sobre_el_demo(): void
    {
        config([
            'services.aliexpress.app_key' => '12345678',
            'services.aliexpress.app_secret' => 'secreto-de-prueba',
            'services.aliexpress.demo' => true,
        ]);

        $this->assertSame(AliExpressService::MODE_LIVE, app(AliExpressService::class)->mode());

        Http::fake([
            '*' => Http::response([
                'aliexpress_affiliate_product_query_response' => [
                    'total_results' => 1,
                    'products' => ['product' => [[
                        'product_id' => '330000000001',
                        'product_title' => 'Producto de la API real',
                        'product_main_image_url' => 'https://ae01.alicdn.com/kf/S1.jpg',
                        'product_detail_url' => 'https://www.aliexpress.com/item/330000000001.html',
                        'sale_price' => '10.00',
                        'first_level_category_id' => '100013',
                        'first_level_category_name' => 'Electrónica',
                    ]]],
                ],
            ]),
        ]);

        $this->artisan('app:sync-aliexpress-products', ['--limit' => 20])->assertSuccessful();

        $this->assertDatabaseHas('products', [
            'external_id' => '330000000001',
            'title' => 'Producto de la API real',
        ]);
        $this->assertSame(1, Product::count());
        $this->assertStringNotContainsString('modo demostración', ApiSyncLog::first()->message);
        Http::assertSent(fn ($request) => $request->data()['method'] === AliExpressService::PRODUCT_QUERY);
    }
}
