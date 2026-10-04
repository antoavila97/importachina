<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La demo inicial se creo con Product::factory(), que llena titles y categorias
 * con Faker ("Sint Saepe Ipsa", "Animi Magnam"). El seeder nuevo solo crea su
 * catalogo en espanol si la tabla products esta vacia, asi que en una base que
 * ya tenia los productos viejos hay que reescribirlos en el sitio.
 *
 * En produccion eso es exactamente lo que paso: el catalogo seguia en latin
 * porque la migracion no existia.
 */
class DemoCatalogSpanishMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduce lo que hay en la base de produccion: productos viejos de la
     * factory, ya referenciados por pedidos.
     */
    private function catalogoViejo(): void
    {
        Product::factory()->count(8)->create([
            'title' => 'Sint Saepe Ipsa',
            'image_url' => 'https://via.placeholder.com/600x400.png/00ff22?text=aut',
            'source_url' => 'https://www.aliexpress.com/item/12345',
        ]);

        $producto = Product::firstOrFail();

        $pedido = Order::create([
            'user_id' => User::factory()->cliente()->create()->id,
            'status' => Order::STATUS_ENTREGADO,
            'shipping_address' => 'Av. Siempre Viva 742',
        ]);

        OrderItem::create([
            'order_id' => $pedido->id,
            'product_id' => $producto->id,
            'quantity' => 2,
            'unit_price' => 30,
        ]);
    }

    /**
     * Corre la migracion. No sirve artisan('migrate') porque RefreshDatabase ya
     * aplico todas las migraciones al preparar cada test, asi que no queda ninguna
     * pendiente y el comando no haria nada.
     */
    private function correrMigracion(): void
    {
        $migration = require database_path('migrations/2026_10_04_120000_rewrite_demo_catalog_to_spanish.php');
        $migration->up();
    }

    public function test_reescribe_los_titulos_y_categorias_de_latin_a_espanol(): void
    {
        $this->catalogoViejo();

        $this->correrMigracion();

        $titulos = Product::orderBy('id')->pluck('title');

        $this->assertCount(8, $titulos);
        $this->assertNotContains('Sint Saepe Ipsa', $titulos);
        $this->assertContains('Organizador de almacenaje plegable 3 niveles', $titulos);

        foreach ($titulos as $titulo) {
            $this->assertMatchesRegularExpression(
                '/^[\p{L}\p{N}\s.,%+\-()\/]+$/u',
                $titulo,
                "El titulo \"{$titulo}\" todavia tiene palabras de latin, no espanol."
            );
        }

        $this->assertNotContains(
            'Animi Magnam',
            Category::pluck('name'),
            'Las categorias de Faker quedaron en el filtro del catalogo.'
        );

        $this->assertGreaterThan(0, Category::count(), 'Los productos quedaron sin categoria.');
    }

    public function test_los_pedidos_antiguos_seguen_apuntando_a_productos_validos(): void
    {
        $this->catalogoViejo();

        $itemId = OrderItem::firstOrFail()->id;
        $productoIdViejo = OrderItem::firstOrFail()->product_id;

        $this->correrMigracion();

        // No se borra ni se recrea nada: los UPDATE son en el sitio.
        $this->assertNotNull(OrderItem::find($itemId));
        $this->assertSame($productoIdViejo, OrderItem::find($itemId)->product_id);
        $this->assertNotNull(Product::find($productoIdViejo));
    }

    public function test_las_imagenes_muertas_y_el_source_url_falso_se_limpian(): void
    {
        $this->catalogoViejo();

        $this->correrMigracion();

        foreach (Product::all() as $producto) {
            $this->assertNull($producto->image_url);
            $this->assertNull($producto->source_url);
        }
    }

    public function test_no_toca_los_productos_que_vienen_de_la_api_de_aliexpress(): void
    {
        $real = Product::factory()->create([
            'external_id' => '100500123456',
            'title' => 'Producto real de AliExpress',
            'image_url' => 'https://ae01.alicdn.com/kf/S123.jpg',
            'source_url' => 'https://www.aliexpress.com/item/100500123456.html',
            'category_id' => Category::factory()->create(['name' => 'Ropa', 'slug' => 'ropa'])->id,
        ]);

        $this->catalogoViejo();

        $this->correrMigracion();

        $this->assertSame('Producto real de AliExpress', $real->fresh()->title);
        $this->assertSame('https://ae01.alicdn.com/kf/S123.jpg', $real->fresh()->image_url);
        $this->assertSame(
            'https://www.aliexpress.com/item/100500123456.html',
            $real->fresh()->source_url,
            'La sincronizacion real no se toca.'
        );

        // Y su categoria sobrevive al borrado de las huerfanas.
        $this->assertNotNull($real->fresh()->category);
        $this->assertSame('Ropa', $real->fresh()->category->name);
    }

    public function test_es_idempotente(): void
    {
        $this->catalogoViejo();

        $this->correrMigracion();
        $primero = Product::orderBy('id')->pluck('title', 'id')->all();

        // Volver a correr la migracion no debe cambiar nada ni fallar.
        $this->correrMigracion();

        $this->assertSame($primero, Product::orderBy('id')->pluck('title', 'id')->all());
    }
}
