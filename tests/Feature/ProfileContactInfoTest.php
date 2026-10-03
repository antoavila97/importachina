<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU-04: "Como cliente, quiero actualizar mi teléfono y mi dirección para que mis
 * pedidos lleguen al lugar correcto."
 */
class ProfileContactInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_cliente_actualiza_su_telefono_y_su_direccion(): void
    {
        $cliente = User::factory()->cliente()->create();

        $response = $this->actingAs($cliente)->patch(route('profile.update'), [
            'name' => $cliente->name,
            'email' => $cliente->email,
            'phone' => '+591 71234567',
            'address' => 'Av. Ballivian 1238, La Paz',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $cliente->refresh();
        $this->assertSame('+591 71234567', $cliente->phone);
        $this->assertSame('Av. Ballivian 1238, La Paz', $cliente->address);
    }

    public function test_los_datos_quedan_mostrados_al_volver_al_perfil(): void
    {
        $cliente = User::factory()->cliente()->conDireccion()->create();

        $this->actingAs($cliente)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('+591 70000000')
            ->assertSee('Av. Siempre Viva 742');
    }

    public function test_el_telefono_y_la_direccion_son_opcionales(): void
    {
        $cliente = User::factory()->cliente()->conDireccion()->create();

        $this->actingAs($cliente)
            ->patch(route('profile.update'), [
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => '',
                'address' => '',
            ])
            ->assertSessionHasNoErrors();

        $cliente->refresh();
        $this->assertNull($cliente->phone);
        $this->assertNull($cliente->address);
    }

    public function test_rechaza_un_telefono_con_letras(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)
            ->patch(route('profile.update'), [
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => 'llamar al 777',
                'address' => 'Av. Siempre Viva 742',
            ])
            ->assertSessionHasErrors('phone');

        $this->assertNull($cliente->refresh()->phone);
    }

    public function test_rechaza_una_direccion_demasiado_larga(): void
    {
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($cliente)
            ->patch(route('profile.update'), [
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => '70000000',
                'address' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('address');
    }

    public function test_no_se_puede_entrar_al_perfil_sin_iniciar_sesion(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'), [
            'name' => 'Intruso',
            'email' => 'intruso@importachina.com',
        ])->assertRedirect(route('login'));
    }

    public function test_uno_no_puede_editar_el_perfil_de_otro_usuario(): void
    {
        $propio = User::factory()->cliente()->create();
        $ajeno = User::factory()->cliente()->create();

        $this->actingAs($propio)->patch(route('profile.update'), [
            'name' => 'Hackeado',
            'email' => $ajeno->email,
            'phone' => '000',
            'address' => 'Dirección ajena',
        ]);

        $ajeno->refresh();
        $this->assertNotSame('Hackeado', $ajeno->name);
        $this->assertNull($ajeno->phone);
    }

    public function test_el_carrito_muestra_la_direccion_del_perfil_por_defecto(): void
    {
        $cliente = User::factory()->cliente()->conDireccion()->create();
        $product = Product::factory()->create();

        $this->actingAs($cliente);
        $this->get(route('cart.add', $product));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Av. Siempre Viva 742, La Paz');
    }

    public function test_la_direccion_del_perfil_se_usa_como_valor_inicial_del_pedido(): void
    {
        $cliente = User::factory()->cliente()->conDireccion()->create();
        $product = Product::factory()->create(['sale_price' => 30]);

        $this->actingAs($cliente);
        $this->get(route('cart.add', $product));

        // El navegador reenvía el textarea tal como se pintó en el carrito.
        $this->post(route('checkout.store'), [
            'shipping_address' => $cliente->address,
        ])->assertSessionHasNoErrors();

        $this->assertSame($cliente->address, Order::firstOrFail()->shipping_address);
    }

    public function test_no_se_puede_confirmar_la_compra_sin_direccion(): void
    {
        $cliente = User::factory()->cliente()->create(['address' => null]);
        $product = Product::factory()->create();

        $this->actingAs($cliente);
        $this->get(route('cart.add', $product));

        $this->post(route('checkout.store'), ['shipping_address' => ''])
            ->assertSessionHasErrors('shipping_address');

        $this->assertSame(0, Order::count());
        $this->assertSame('active', Cart::where('user_id', $cliente->id)->firstOrFail()->status);
    }

    public function test_el_vendedor_ve_el_telefono_del_cliente_en_el_detalle_del_pedido(): void
    {
        $vendedor = User::factory()->vendedor()->create();
        $cliente = User::factory()->cliente()->conDireccion()->create();
        $order = Order::factory()->create([
            'user_id' => $cliente->id,
            'shipping_address' => $cliente->address,
        ]);

        $this->actingAs($vendedor)
            ->get(route('vendedor.orders.show', $order))
            ->assertOk()
            ->assertSee('+591 70000000');
    }
}
