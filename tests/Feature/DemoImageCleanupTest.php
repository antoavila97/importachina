<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Database\Seeders\DemoSalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El seeder corre en cada arranque del servidor, asi que ademas de ser
 * idempotente tiene que reparar los datos viejos: los productos de la demo
 * inicial guardaban la URL de via.placeholder.com, servicio apagado en 2024.
 */
class DemoImageCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_limpia_las_imagenes_de_servicios_apagados_que_quedaron_en_la_base(): void
    {
        $conUrlMuerta = Product::factory()->create([
            'image_url' => 'https://via.placeholder.com/600x400.png/00ff22?text=aut',
        ]);
        $conPlacehold = Product::factory()->create([
            'image_url' => 'https://placehold.co/600x400',
        ]);
        $conGato = Product::factory()->create([
            'image_url' => 'https://placekitten.com/600/400',
        ]);
        $conUrlReal = Product::factory()->create([
            'image_url' => 'https://ae01.alicdn.com/kf/S123.jpg',
        ]);

        $this->seed(DemoSalesSeeder::class);

        $this->assertNull($conUrlMuerta->fresh()->image_url);
        $this->assertNull($conPlacehold->fresh()->image_url);
        $this->assertNull($conGato->fresh()->image_url);

        // Las imagenes de la API de AliExpress se respetan: son las de verdad.
        $this->assertSame('https://ae01.alicdn.com/kf/S123.jpg', $conUrlReal->fresh()->image_url);
    }

    public function test_la_limpieza_corre_aunque_no_haya_que_generar_ventas(): void
    {
        $producto = Product::factory()->create([
            'image_url' => 'https://via.placeholder.com/600x400.png',
        ]);

        // El segundo arranque ya no genera pedidos, pero todavia tiene que limpiar.
        $this->seed(DemoSalesSeeder::class);
        $this->assertNull($producto->fresh()->image_url);
    }

    public function test_el_seeder_no_revienta_si_el_catalogo_tiene_un_solo_producto(): void
    {
        Product::factory()->create();

        // Collection::random() lanza si se piden mas lineas de las que hay.
        $this->seed(DemoSalesSeeder::class);

        $this->assertGreaterThan(0, Order::count());
    }
}
