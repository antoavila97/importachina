<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_un_visitante_no_puede_ver_el_reporte(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
    }

    public function test_un_cliente_no_puede_ver_el_reporte(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_el_administrador_ve_el_reporte(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Reporte de ventas');
    }

    public function test_el_total_vendido_solo_suma_pedidos_pagados_enviados_o_entregados(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->cliente()->create();

        Order::factory()->paid()->for($customer)->create(['total' => 100]);
        Order::factory()->shipped()->for($customer)->create(['total' => 50]);
        Order::factory()->delivered()->for($customer)->create(['total' => 25]);
        Order::factory()->create(['status' => Order::STATUS_PENDIENTE, 'total' => 999]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $this->assertSame('Bs 175.00', $this->kpiValue($response->getContent(), 'Total vendido'));
        $this->assertSame('3', $this->kpiValue($response->getContent(), 'Pedidos vendidos'));
        $this->assertStringContainsString('Quedan 1 pedido(s) pendientes', $response->getContent());
    }

    public function test_el_rango_de_fechas_filtra_los_pedidos(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->cliente()->create();

        Order::factory()->paid()->for($customer)->create([
            'total' => 100,
            'created_at' => now()->subDays(10),
        ]);
        Order::factory()->paid()->for($customer)->create([
            'total' => 70,
            'created_at' => now()->subDays(200),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'from' => now()->subDays(30)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $this->assertSame('Bs 100.00', $this->kpiValue($response->getContent(), 'Total vendido'));
    }

    public function test_muestra_los_cinco_productos_mas_vendidos(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->cliente()->create();

        foreach (range(1, 6) as $position) {
            $product = Product::factory()->create(['title' => "Producto {$position}"]);
            $order = Order::factory()->paid()->for($customer)->create();

            OrderItem::factory()->for($order)->for($product)->create([
                'quantity' => $position,
                'unit_price' => 10,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));
        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString('5 productos más vendidos', $content);
        $this->assertStringContainsString('Producto 6', $content);
        $this->assertStringContainsString('Producto 2', $content);

        // El producto con 1 unidad queda fuera del top 5.
        $this->assertStringNotContainsString('Producto 1<', $content);
    }

    public function test_valida_el_rango_de_fechas(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index', [
                'from' => '2026-05-10',
                'to' => '2026-05-01',
            ]))
            ->assertSessionHasErrors('to');
    }

    private function kpiValue(string $html, string $label): string
    {
        $this->assertSame(
            1,
            preg_match('/'.preg_quote($label, '/').'<\/p>\s*<p[^>]*>(.*?)<\/p>/s', $html, $matches),
            "No se encontro el KPI {$label}"
        );

        return trim($matches[1]);
    }
}
