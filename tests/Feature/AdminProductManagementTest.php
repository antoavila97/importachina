<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * HU-07: el administrador carga productos indicando el costo y el margen;
 * el precio de venta lo calcula el sistema.
 */
class AdminProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Auriculares Bluetooth',
            'description' => 'Cancelacion de ruido y 20h de bateria.',
            'category_id' => null,
            'cost_price' => 100,
            'margin_pct' => 30,
            'stock' => 25,
            'image_url' => 'https://cdn.importachina.com/auriculares.jpg',
            'source_url' => 'https://www.aliexpress.com/item/100500.html',
            'active' => '1',
            'external_id' => 'AE-1001',
        ], $overrides);
    }

    public function test_el_administrador_ve_el_listado_de_productos(): void
    {
        Product::factory()->create(['title' => 'Lampada LED', 'external_id' => 'AE-9']);

        $this->actingAs($this->admin())
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Productos')
            ->assertSee('Lampada LED')
            ->assertSee('Activo');
    }

    public function test_el_listado_se_filtra_por_titulo_categoria_y_estado(): void
    {
        $this->actingAs($this->admin());

        $categoria = Category::factory()->create(['name' => 'Iluminacion']);
        $conStock = Product::factory()->for($categoria)->create(['title' => 'Lampada LED', 'external_id' => 'AE-1']);
        Product::factory()->create(['title' => 'Funda movil', 'external_id' => 'AE-2']);
        $inactivo = Product::factory()->inactive()->create(['title' => 'Producto viejo', 'external_id' => 'AE-3']);

        $this->get(route('admin.products.index', ['search' => 'Lampada']))
            ->assertOk()
            ->assertSee('Lampada LED')
            ->assertDontSee('Funda movil');

        $this->get(route('admin.products.index', ['category_id' => $categoria->id]))
            ->assertOk()
            ->assertSee('Lampada LED')
            ->assertDontSee('Funda movil');

        $this->get(route('admin.products.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Producto viejo')
            ->assertDontSee('Lampada LED');

        $this->assertNotNull($conStock);
        $this->assertNotNull($inactivo);
    }

    public function test_hu07_el_precio_de_venta_se_calcula_del_costo_y_el_margen(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['cost_price' => 80, 'margin_pct' => 25]))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $product = Product::where('external_id', 'AE-1001')->first();

        $this->assertNotNull($product, 'El producto no se guardo.');
        $this->assertSame('100.00', $product->sale_price);
        $this->assertEquals(20.0, $product->profit());
    }

    public function test_el_precio_de_venta_del_formulario_se_ignora(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload([
                'cost_price' => 100,
                'margin_pct' => 30,
                'sale_price' => 9999,
            ]));

        $product = Product::where('external_id', 'AE-1001')->first();

        $this->assertSame('130.00', $product->sale_price, 'El administrador no puede escribir el precio de venta.');
    }

    public function test_el_administrador_puede_fijar_el_precio_para_que_la_sincronizacion_no_lo_cambie(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['price_locked' => '1']))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $fijado = Product::where('external_id', 'AE-1001')->first();

        $this->assertNotNull($fijado);
        $this->assertTrue($fijado->price_locked);

        // Sin marcar la casilla el precio sigue siendo actualizable por la API.
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['external_id' => 'AE-1002']));

        $this->assertFalse(Product::where('external_id', 'AE-1002')->first()->price_locked);
    }

    public function test_se_puede_desfijar_el_precio_al_editar(): void
    {
        $product = Product::factory()->create(['external_id' => 'AE-77', 'price_locked' => true]);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), $this->payload([
                'external_id' => 'AE-77',
                'price_locked' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->price_locked);
    }

    public function test_el_formulario_valida_titulo_costo_margen_y_stock(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), [])
            ->assertSessionHasErrors(['title', 'cost_price', 'margin_pct', 'stock', 'active']);

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['cost_price' => -5]))
            ->assertSessionHasErrors('cost_price');

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['margin_pct' => 900]))
            ->assertSessionHasErrors('margin_pct');

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['image_url' => 'no-es-una-url']))
            ->assertSessionHasErrors('image_url');
    }

    public function test_no_se_repiten_identificadores_externos(): void
    {
        Product::factory()->create(['external_id' => 'AE-1001']);

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload())
            ->assertSessionHasErrors('external_id');
    }

    public function test_el_administrador_crea_un_producto_asignando_categoria(): void
    {
        $categoria = Category::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['category_id' => $categoria->id]));

        $product = Product::where('external_id', 'AE-1001')->first();

        $this->assertSame($categoria->id, $product->category_id);
        $this->assertTrue($product->active);
    }

    public function test_el_administrador_edita_un_producto_y_recalcula_el_precio(): void
    {
        $product = Product::factory()->create([
            'title' => 'Titulo viejo',
            'external_id' => 'AE-77',
            'cost_price' => 50,
            'margin_pct' => 10,
            'sale_price' => 55,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Editar producto')
            ->assertSee('Titulo viejo')
            ->assertSee('Vaciar');

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), $this->payload([
                'title' => 'Titulo nuevo',
                'external_id' => 'AE-77',
                'cost_price' => 200,
                'margin_pct' => 50,
            ]))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $product->refresh();

        $this->assertSame('Titulo nuevo', $product->title);
        $this->assertSame('300.00', $product->sale_price);
    }

    public function test_editar_acepta_el_mismo_identificador_externo_del_producto(): void
    {
        $product = Product::factory()->create(['external_id' => 'AE-77']);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), $this->payload([
                'external_id' => 'AE-77',
                'title' => 'Otro titulo',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Otro titulo', $product->fresh()->title);
    }

    public function test_un_producto_sin_pedidos_se_elimina(): void
    {
        $product = Product::factory()->create(['title' => 'Sin ventas']);

        $this->actingAs($this->admin())
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($product);
    }

    public function test_un_producto_con_pedidos_se_desactiva_en_vez_de_eliminarse(): void
    {
        $product = Product::factory()->create(['title' => 'Con ventas']);
        OrderItem::factory()->for($product)->for(Order::factory())->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('warning');

        $product->refresh();

        $this->assertTrue($product->exists);
        $this->assertFalse($product->active);
    }

    public function test_el_listado_informa_cuantas_imagenes_tiene_cada_producto(): void
    {
        $product = Product::factory()->create(['title' => 'Con imagenes']);
        $product->images()->createMany([
            ['url' => 'https://cdn.importachina.com/1.jpg', 'position' => 1],
            ['url' => 'https://cdn.importachina.com/2.jpg', 'position' => 2],
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Con imagenes')
            ->assertSee('(2 imagen(es))');
    }

    public function test_un_cliente_no_puede_gestionar_productos(): void
    {
        $cliente = User::factory()->cliente()->create();
        $product = Product::factory()->create();

        $this->actingAs($cliente)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($cliente)->post(route('admin.products.store'), $this->payload())->assertForbidden();
        $this->actingAs($cliente)->delete(route('admin.products.destroy', $product))->assertForbidden();
    }

    public function test_la_ruta_show_de_productos_no_existe(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('admin.products.show'));

        $this->actingAs($this->admin())
            ->get(route('admin.products.index').'/'.Product::factory()->create()->id)
            ->assertStatus(405);
    }
}
