<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoSalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El DemoSalesSeeder corre en cada arranque del servidor (Procfile), asi que
 * tiene que ser idempotente: si no, cada despliegue suma 14 pedidos mas.
 */
class DemoSalesSeederTest extends TestCase
{
    use RefreshDatabase;

    private function sembrarDemo(): void
    {
        $this->seed(DemoSalesSeeder::class);
    }

    public function test_genera_pedidos_pagos_y_productos_para_poder_ver_el_reporte(): void
    {
        $this->sembrarDemo();

        $this->assertSame(14, Order::count());
        $this->assertSame(8, Product::count());
        $this->assertGreaterThan(0, Payment::count());
        $this->assertGreaterThan(0, OrderItem::count());
    }

    public function test_correrlo_de_nuevo_no_duplica_pedidos(): void
    {
        $this->sembrarDemo();
        $this->sembrarDemo();
        $this->sembrarDemo();

        $this->assertSame(14, Order::count());
    }

    public function test_no_toca_los_pedidos_que_ya_creo_un_cliente(): void
    {
        $cliente = User::factory()->cliente()->create();
        $producto = Product::factory()->create(['sale_price' => 30]);

        $carrito = Cart::firstOrCreate([
            'user_id' => $cliente->id,
            'status' => 'active',
        ]);
        $carrito->items()->create(['product_id' => $producto->id, 'quantity' => 2]);

        $this->actingAs($cliente);

        $this->post(route('checkout.store'), ['shipping_address' => 'Av. Siempre Viva 742'])
            ->assertSessionHasNoErrors();

        $pedidoReal = Order::firstOrFail();
        $this->assertSame($cliente->id, $pedidoReal->user_id);

        $this->sembrarDemo();

        $this->assertSame(1, Order::count());
        $this->assertSame($pedidoReal->id, Order::firstOrFail()->id);
    }

    public function test_el_reporte_muestra_datos_tras_cargar_las_ventas_de_demo(): void
    {
        $this->sembrarDemo();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.index'));

        $response->assertOk();

        $texto = preg_replace('/\s+/', ' ', strip_tags($response->getContent()));

        $this->assertDoesNotMatchRegularExpression('/Bs\s*0\.00/', $texto);
    }
}
