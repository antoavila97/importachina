<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visitante_es_redirigido_al_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_el_panel_dejo_de_mostrar_el_mensaje_de_breeze(): void
    {
        $user = User::factory()->cliente()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee("You're logged in!")
            ->assertSee('Hola, '.$user->name);
    }

    public function test_el_admin_ve_los_indicadores_de_la_tienda(): void
    {
        $admin = User::factory()->admin()->create();

        Order::factory()->paid()->create(['total' => 150]);
        Order::factory()->create(['total' => 80]);
        Product::factory()->create(['active' => true, 'stock' => 3]);
        Product::factory()->create(['active' => true, 'stock' => 10]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Ventas del mes')
            ->assertSee('Productos activos')
            ->assertSee('Stock bajo')
            ->assertSee('Accesos rápidos')
            ->assertSee('<p class="text-2xl font-bold text-gray-900">Bs 150.00</p>', false)
            ->assertSee(route('admin.products.create'), false)
            ->assertSee(route('admin.reports.index'), false)
            ->assertDontSee('Por cobrar');
    }

    public function test_el_vendedor_ve_sus_indicadores_sin_secciones_de_admin(): void
    {
        $seller = User::factory()->vendedor()->create();

        Order::factory()->paid()->create(['total' => 90]);
        Order::factory()->create(['total' => 60]);
        Product::factory()->create(['active' => true, 'stock' => 1]);

        $response = $this->actingAs($seller)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Ventas del mes')
            ->assertSee('Por cobrar')
            ->assertSee('Pendientes de despacho')
            ->assertSee('<p class="text-2xl font-bold text-gray-900">Bs 60.00</p>', false)
            ->assertSee(route('vendedor.orders.index'), false)
            ->assertDontSee('Productos activos')
            ->assertDontSee('Stock bajo')
            ->assertDontSee(route('admin.products.create'), false);
    }

    public function test_el_cliente_ve_sus_propios_pedidos(): void
    {
        $client = User::factory()->cliente()->create();
        $other = User::factory()->cliente()->create();

        $mine = Order::factory()->delivered()->create(['user_id' => $client->id, 'total' => 120]);
        $minePending = Order::factory()->create(['user_id' => $client->id, 'total' => 45]);
        Order::factory()->create(['user_id' => $other->id, 'total' => 999]);

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Mis pedidos')
            ->assertSee('En camino')
            ->assertSee('Entregados')
            ->assertSee('Accesos rápidos')
            ->assertSee('#'.$mine->id)
            ->assertSee('#'.$minePending->id)
            ->assertSee(route('orders.index'), false)
            ->assertSee(route('catalog.index'), false)
            ->assertDontSee('#999')
            ->assertDontSee('Ventas del mes')
            ->assertDontSee('Reportes')
            ->assertDontSee('Sincronizar');
    }

    public function test_el_panel_muestra_el_estado_vacio_cuando_no_hay_pedidos(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Todavía no hay pedidos.')
            ->assertSee('Ningún producto con stock bajo.');
    }
}
