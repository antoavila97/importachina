<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU-08: el detalle de un producto muestra descripción, imágenes y botón de carrito.
 */
class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_detalle_muestra_descripcion_precio_y_boton_de_carrito(): void
    {
        $product = Product::factory()->create([
            'title' => 'Auriculares Bluetooth',
            'description' => 'Cancelacion de ruido activa.',
            'sale_price' => 129.9,
            'stock' => 10,
        ]);

        $this->get(route('catalog.show', $product))
            ->assertOk()
            ->assertSee('Auriculares Bluetooth')
            ->assertSee('Cancelacion de ruido activa.')
            ->assertSee('Bs 129.90')
            ->assertSee(route('cart.add', $product));
    }

    public function test_hu08_el_detalle_muestra_todas_las_imagenes_del_producto(): void
    {
        $product = Product::factory()->create([
            'image_url' => 'https://cdn.importachina.com/portada.jpg',
        ]);
        $product->images()->createMany([
            ['url' => 'https://cdn.importachina.com/detalle-1.jpg', 'position' => 1],
            ['url' => 'https://cdn.importachina.com/detalle-2.jpg', 'position' => 2],
        ]);

        $this->get(route('catalog.show', $product))
            ->assertOk()
            ->assertSee('https://cdn.importachina.com/portada.jpg')
            ->assertSee('https://cdn.importachina.com/detalle-1.jpg')
            ->assertSee('https://cdn.importachina.com/detalle-2.jpg');
    }

    public function test_un_producto_sin_imagenes_adicionales_no_arma_galeria(): void
    {
        $product = Product::factory()->create(['image_url' => 'https://cdn.importachina.com/portada.jpg']);

        $this->get(route('catalog.show', $product))
            ->assertOk()
            ->assertSee('https://cdn.importachina.com/portada.jpg');
    }

    public function test_sin_stock_se_muestra_el_aviso_y_no_el_boton_de_carrito(): void
    {
        $product = Product::factory()->create(['stock' => 0]);

        $this->get(route('catalog.show', $product))
            ->assertOk()
            ->assertSee('Sin stock por el momento')
            ->assertDontSee(route('cart.add', $product));
    }

    public function test_un_producto_inactivo_no_es_publico(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->get(route('catalog.show', $product))->assertNotFound();
    }

    public function test_el_detalle_muestra_el_enlace_de_agregar_al_carrito_para_un_cliente(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->cliente()->create())
            ->get(route('catalog.show', $product))
            ->assertOk()
            ->assertSee('Añadir al carrito');
    }
}
