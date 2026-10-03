<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un pago registrado se puede corregir o anular sin perder la trazabilidad.
 */
class PaymentEditVoidTest extends TestCase
{
    use RefreshDatabase;

    private function vendedor(): User
    {
        return User::factory()->vendedor()->create();
    }

    public function test_el_vendedor_actualiza_el_monto_y_el_metodo_de_un_pago(): void
    {
        $order = Order::factory()->paid()->create();
        $payment = Payment::factory()->for($order)->create(['amount' => 100, 'method' => 'efectivo']);

        $this->actingAs($this->vendedor())
            ->put(route('vendedor.payments.update', [$order, $payment]), [
                'method' => 'transferencia',
                'amount' => 135.5,
                'paid_at' => now()->subDay()->format('Y-m-d'),
            ])
            ->assertRedirect(route('vendedor.orders.show', $order))
            ->assertSessionHas('success');

        $payment->refresh();

        $this->assertSame('transferencia', $payment->method);
        $this->assertEquals(135.5, (float) $payment->amount);
    }

    public function test_la_actualizacion_de_un_pago_valida_los_datos(): void
    {
        $order = Order::factory()->paid()->create();
        $payment = Payment::factory()->for($order)->create(['amount' => 100]);

        $this->actingAs($this->vendedor())
            ->put(route('vendedor.payments.update', [$order, $payment]), [
                'method' => 'bitcoin',
                'amount' => 0,
                'paid_at' => now()->addWeek()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors(['method', 'amount', 'paid_at']);

        $this->assertEquals(100, (float) $payment->fresh()->amount);
    }

    public function test_anular_el_unico_pago_devuelve_el_pedido_a_pendiente(): void
    {
        $order = Order::factory()->paid()->create();
        $payment = Payment::factory()->for($order)->create();

        $this->actingAs($this->vendedor())
            ->put(route('vendedor.payments.void', [$order, $payment]))
            ->assertRedirect(route('vendedor.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertTrue($payment->fresh()->isVoided());
        $this->assertSame(Order::STATUS_PENDIENTE, $order->fresh()->status);
    }

    public function test_anular_un_pago_no_borra_la_fila_ni_el_resto_de_los_pagos(): void
    {
        $order = Order::factory()->paid()->create();
        $anulado = Payment::factory()->for($order)->create();
        $vigente = Payment::factory()->for($order)->create();

        $this->actingAs($this->vendedor())
            ->put(route('vendedor.payments.void', [$order, $anulado]));

        $this->assertModelExists($anulado);
        $this->assertTrue($anulado->fresh()->isVoided());
        $this->assertTrue($vigente->fresh()->isCompleted());
        $this->assertSame(Order::STATUS_PAGADO, $order->fresh()->status);
    }

    public function test_no_se_puede_anular_un_pago_que_no_pertenece_al_pedido(): void
    {
        $order = Order::factory()->paid()->create();
        $otroPago = Payment::factory()->create();

        $this->actingAs($this->vendedor())
            ->put(route('vendedor.payments.void', [$order, $otroPago]))
            ->assertNotFound();

        $this->assertTrue($otroPago->fresh()->isCompleted());
    }

    public function test_un_cliente_no_puede_editar_ni_anular_pagos(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create();
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)
            ->put(route('vendedor.payments.update', [$order, $payment]), ['method' => 'qr', 'amount' => 10])
            ->assertForbidden();

        $this->actingAs($cliente)
            ->put(route('vendedor.payments.void', [$order, $payment]))
            ->assertForbidden();
    }

    public function test_el_detalle_muestra_las_acciones_solo_de_los_pagos_no_anulados(): void
    {
        $order = Order::factory()->paid()->create();
        Payment::factory()->for($order)->create(['amount' => 10]);
        Payment::factory()->for($order)->voided()->create(['amount' => 20]);

        $response = $this->actingAs($this->vendedor())->get(route('vendedor.orders.show', $order));

        $response->assertOk()
            ->assertSee('Completado')
            ->assertSee('Anulado')
            ->assertSee('Bs 10.00')
            ->assertSee('Bs 20.00');

        $this->assertSame(
            1,
            substr_count($response->getContent(), '/anular'),
            'Solo el pago vigente deberia ofrecer la accion de anular.'
        );
    }

    public function test_el_detalle_muestra_sin_pagos_cuando_se_anula_el_unico(): void
    {
        $order = Order::factory()->paid()->create(['total' => 500]);
        $payment = Payment::factory()->for($order)->create(['amount' => 500]);

        $this->actingAs($this->vendedor())
            ->put(route('vendedor.payments.void', [$order, $payment]));

        $this->actingAs($this->vendedor())
            ->get(route('vendedor.orders.show', $order))
            ->assertOk()
            ->assertSee('Sin pagos');
    }
}
