<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SellerOrderTest extends TestCase
{
    use RefreshDatabase;

    private function vendedor(): User
    {
        return User::factory()->vendedor()->create();
    }

    /**
     * @return array{0: Order, 1: User}
     */
    private function pedidoPendiente(): array
    {
        $cliente = User::factory()->cliente()->create([
            'name' => 'Carlos Mendoza',
            'email' => 'carlos@example.com',
        ]);
        $order = Order::factory()->for($cliente)->create([
            'status' => Order::STATUS_PENDIENTE,
            'total' => 250,
            'shipping_address' => 'Av. Ballivian 1238, La Paz',
        ]);

        OrderItem::factory()
            ->for($order)
            ->for(Product::factory()->create(['title' => 'Auriculares Bluetooth']))
            ->create(['quantity' => 2, 'unit_price' => 125]);

        return [$order->fresh(), $cliente];
    }

    // ---------------------------------------------------------------- HU-12

    public function test_el_vendedor_ve_la_lista_de_pedidos_con_cliente_total_y_estado(): void
    {
        [$order] = $this->pedidoPendiente();

        $response = $this->actingAs($this->vendedor())
            ->get(route('vendedor.orders.index'));

        $response->assertOk()
            ->assertSee('Pedidos')
            ->assertSee('#'.$order->id)
            ->assertSee($order->user->name)
            ->assertSee('Bs 250.00')
            ->assertSee('Pendiente');
    }

    public function test_el_vendedor_puede_cambiar_el_estado_de_un_pedido(): void
    {
        [$order] = $this->pedidoPendiente();

        $this->actingAs($this->vendedor())
            ->put(route('vendedor.orders.status', $order), ['status' => Order::STATUS_ENVIADO])
            ->assertRedirect(route('vendedor.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(Order::STATUS_ENVIADO, $order->fresh()->status);
    }

    public function test_cada_estado_aceptado_por_hu12_puede_asignarse(): void
    {
        $vendedor = $this->vendedor();

        foreach (Order::STATUSES as $status) {
            $order = Order::factory()->create();

            $this->actingAs($vendedor)
                ->put(route('vendedor.orders.status', $order), ['status' => $status])
                ->assertSessionHasNoErrors();

            $this->assertSame($status, $order->fresh()->status);
        }
    }

    public function test_el_estado_se_valida_contra_lista_permitida(): void
    {
        [$order] = $this->pedidoPendiente();

        $this->actingAs($this->vendedor())
            ->put(route('vendedor.orders.status', $order), ['status' => 'hackeado'])
            ->assertSessionHasErrors('status');

        $this->assertSame(Order::STATUS_PENDIENTE, $order->fresh()->status);
    }

    public function test_el_estado_es_obligatorio(): void
    {
        [$order] = $this->pedidoPendiente();

        $this->actingAs($this->vendedor())
            ->put(route('vendedor.orders.status', $order), [])
            ->assertSessionHasErrors('status');
    }

    // ---------------------------------------------------------------- HU-13

    public function test_registrar_pago_guarda_metodo_monto_y_fecha(): void
    {
        [$order] = $this->pedidoPendiente();
        $paidAt = now()->subDays(2);

        $this->actingAs($this->vendedor())
            ->post(route('vendedor.payments.store', $order), [
                'method' => 'transferencia',
                'amount' => 250,
                'paid_at' => $paidAt->format('Y-m-d'),
            ])
            ->assertRedirect(route('vendedor.orders.show', $order))
            ->assertSessionHas('success');

        $payment = Payment::where('order_id', $order->id)->sole();

        $this->assertSame('transferencia', $payment->method);
        $this->assertEquals(250, $payment->amount);
        $this->assertTrue($paidAt->isSameDay($payment->paid_at));
        $this->assertSame('completed', $payment->status);
    }

    public function test_registrar_el_pago_pasa_el_pedido_a_pagado(): void
    {
        [$order] = $this->pedidoPendiente();

        $this->assertSame(Order::STATUS_PENDIENTE, $order->status);

        $this->actingAs($this->vendedor())
            ->post(route('vendedor.payments.store', $order), ['method' => 'efectivo'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Order::STATUS_PAGADO, $order->fresh()->status);
    }

    public function test_el_pago_hereda_el_total_del_pedido_si_no_se_envia_monto(): void
    {
        [$order] = $this->pedidoPendiente();

        $this->actingAs($this->vendedor())
            ->post(route('vendedor.payments.store', $order), ['method' => 'efectivo']);

        $this->assertEquals(250, Payment::where('order_id', $order->id)->sole()->amount);
    }

    public function test_el_metodo_de_pago_se_valida(): void
    {
        [$order] = $this->pedidoPendiente();

        $this->actingAs($this->vendedor())
            ->post(route('vendedor.payments.store', $order), ['method' => 'bitcoin'])
            ->assertSessionHasErrors('method');

        $this->assertSame(0, Payment::count());
        $this->assertSame(Order::STATUS_PENDIENTE, $order->fresh()->status);
    }

    public function test_no_se_acepta_un_monto_de_pago_negativo(): void
    {
        [$order] = $this->pedidoPendiente();

        $this->actingAs($this->vendedor())
            ->post(route('vendedor.payments.store', $order), ['method' => 'efectivo', 'amount' => -10])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::count());
    }

    public function test_no_se_acepta_una_fecha_de_pago_futura(): void
    {
        [$order] = $this->pedidoPendiente();

        $this->actingAs($this->vendedor())
            ->post(route('vendedor.payments.store', $order), [
                'method' => 'efectivo',
                'paid_at' => now()->addWeek()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('paid_at');
    }

    public function test_un_cliente_no_puede_registrar_el_pago_de_su_propio_pedido(): void
    {
        [$order, $cliente] = $this->pedidoPendiente();

        $this->actingAs($cliente)
            ->post(route('vendedor.payments.store', $order), ['method' => 'efectivo'])
            ->assertForbidden();

        $this->assertSame(0, Payment::count());
        $this->assertSame(Order::STATUS_PENDIENTE, $order->fresh()->status);
    }

    public function test_la_ruta_de_pago_para_clientes_ya_no_existe(): void
    {
        $this->assertFalse(
            Route::has('payments.store'),
            'La ruta /pedidos/{order}/pago quedo expuesta para los clientes'
        );
    }

    // ---------------------------------------------------------------- HU-15

    public function test_el_detalle_muestra_productos_direccion_y_contacto(): void
    {
        [$order, $cliente] = $this->pedidoPendiente();

        $response = $this->actingAs($this->vendedor())
            ->get(route('vendedor.orders.show', $order));

        $response->assertOk()
            ->assertSee('Auriculares Bluetooth')
            ->assertSee('Av. Ballivian 1238, La Paz')
            ->assertSee($cliente->name)
            ->assertSee($cliente->email)
            ->assertSee('Bs 250.00');
    }

    public function test_el_detalte_permite_cambiar_el_estado_y_registrar_el_pago(): void
    {
        [$order] = $this->pedidoPendiente();

        $response = $this->actingAs($this->vendedor())
            ->get(route('vendedor.orders.show', $order));

        $response->assertOk()
            ->assertSee(route('vendedor.orders.status', $order))
            ->assertSee(route('vendedor.payments.store', $order))
            ->assertSee('Registrar pago');
    }

    public function test_el_detalle_conserva_los_filtros_al_volver_a_la_lista(): void
    {
        [$order] = $this->pedidoPendiente();

        $query = ['status' => Order::STATUS_PENDIENTE, 'from' => '2026-09-01', 'to' => '2026-09-30'];

        $content = $this->actingAs($this->vendedor())
            ->get(route('vendedor.orders.show', array_merge($query, ['order' => $order->id])))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('vendedor.orders.index'), $content);

        foreach ($query as $key => $value) {
            $this->assertStringContainsString(
                "{$key}={$value}",
                $content,
                "El enlace de volver a la lista perdio el filtro {$key}"
            );
        }
    }

    public function test_el_detalle_no_ofrece_pagos_para_un_pedido_entregado(): void
    {
        $order = Order::factory()->delivered()->create();

        $this->actingAs($this->vendedor())
            ->get(route('vendedor.orders.show', $order))
            ->assertOk()
            ->assertSee('no admite nuevos pagos');
    }

    // ------------------------------------------------------------- Filtros

    public function test_los_filtros_de_la_lista(): void
    {
        $vendedor = $this->vendedor();
        $otro = User::factory()->cliente()->create(['name' => 'Maria Lopez']);

        $pagado = Order::factory()->paid()->create(['total' => 10, 'created_at' => now()->subDays(3)]);
        $pendiente = Order::factory()->create([
            'user_id' => $otro->id,
            'total' => 20,
            'created_at' => now()->subDays(90),
        ]);

        $response = $this->actingAs($vendedor)
            ->get(route('vendedor.orders.index', ['status' => Order::STATUS_PAGADO]));

        $response->assertOk()->assertSee('#'.$pagado->id)->assertDontSee('#'.$pendiente->id);

        $response = $this->actingAs($vendedor)->get(route('vendedor.orders.index', [
            'from' => now()->subDays(30)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]));

        $response->assertOk()->assertSee('#'.$pagado->id)->assertDontSee('#'.$pendiente->id);

        $response = $this->actingAs($vendedor)
            ->get(route('vendedor.orders.index', ['search' => 'Maria']));

        $response->assertOk()->assertSee('#'.$pendiente->id)->assertDontSee('#'.$pagado->id);
    }

    public function test_el_rango_de_fechas_invertido_es_validado(): void
    {
        $this->actingAs($this->vendedor())
            ->get(route('vendedor.orders.index', ['from' => '2026-09-30', 'to' => '2026-09-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_el_filtro_de_estado_desconocido_es_validado(): void
    {
        $this->actingAs($this->vendedor())
            ->get(route('vendedor.orders.index', ['status' => 'inventado']))
            ->assertSessionHasErrors('status');
    }

    public function test_el_enlace_de_pedidos_solo_aparece_para_vendedor_y_administrador(): void
    {
        $this->actingAs(User::factory()->cliente()->create())
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee(route('vendedor.orders.index'));

        $this->actingAs(User::factory()->vendedor()->create())
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee(route('vendedor.orders.index'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee(route('vendedor.orders.index'));
    }
}
