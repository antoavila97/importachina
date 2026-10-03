<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(): User
    {
        return User::factory()->cliente()->create();
    }

    public function test_el_carrito_se_crea_solo_al_agregar_un_producto(): void
    {
        $cliente = $this->cliente();
        $product = Product::factory()->create(['sale_price' => 20]);

        $this->actingAs($cliente)->get(route('cart.add', $product));

        $cart = Cart::where('user_id', $cliente->id)->where('status', 'active')->first();

        $this->assertNotNull($cart);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_agregar_el_mismo_producto_dos_veces_suma_la_cantidad(): void
    {
        $cliente = $this->cliente();
        $product = Product::factory()->create();

        $this->actingAs($cliente);
        $this->get(route('cart.add', $product));
        $this->get(route('cart.add', $product));

        $this->assertSame(2, CartItem::where('product_id', $product->id)->sum('quantity'));
    }

    public function test_puede_cambiar_la_cantidad_de_un_item(): void
    {
        $cliente = $this->cliente();
        $product = Product::factory()->create();
        $this->actingAs($cliente);

        $this->get(route('cart.add', $product));
        $item = CartItem::firstOrFail();

        $this->post(route('cart.update', $item), ['quantity' => 5])
            ->assertRedirect();

        $this->assertSame(5, $item->fresh()->quantity);
    }

    public function test_puede_eliminar_un_item_del_carrito(): void
    {
        $cliente = $this->cliente();
        $product = Product::factory()->create();
        $this->actingAs($cliente);

        $this->get(route('cart.add', $product));
        $item = CartItem::firstOrFail();

        $this->delete(route('cart.destroy', $item))->assertRedirect();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_no_puede_tocar_items_del_carrito_de_otro_usuario(): void
    {
        $item = CartItem::factory()->create();
        $intruso = $this->cliente();

        $this->actingAs($intruso)->delete(route('cart.destroy', $item))->assertForbidden();
        $this->actingAs($intruso)
            ->post(route('cart.update', $item), ['quantity' => 99])
            ->assertForbidden();
    }

    public function test_el_checkout_crea_el_pedido_con_el_precio_congelado_y_vacia_el_carrito(): void
    {
        $cliente = $this->cliente();
        $product = Product::factory()->create(['sale_price' => 25]);
        $this->actingAs($cliente);

        $this->get(route('cart.add', $product));
        $cart = Cart::where('user_id', $cliente->id)->where('status', 'active')->firstOrFail();
        CartItem::firstOrFail()->update(['quantity' => 3]);

        $response = $this->post(route('checkout.store'), [
            'shipping_address' => 'Av. Siempre Viva 742',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame('75.00', $order->total);
        $this->assertSame(Order::STATUS_PENDIENTE, $order->status);
        $this->assertSame('Av. Siempre Viva 742', $order->shipping_address);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 25,
        ]);

        // El carrito queda cerrado y el siguiente checkout parte de uno nuevo.
        $this->assertSame('completed', $cart->fresh()->status);

        $this->post(route('checkout.store'), ['shipping_address' => 'Segundo intento'])
            ->assertRedirect(route('cart.index'));
        $this->assertSame(1, Order::count());
    }

    public function test_no_se_puede_confirmar_un_carrito_vacio(): void
    {
        $this->actingAs($this->cliente())
            ->post(route('checkout.store'), ['shipping_address' => 'Sin productos'])
            ->assertRedirect(route('cart.index'));

        $this->assertSame(0, Order::count());
    }

    public function test_el_cliente_solo_ve_sus_propios_pedidos(): void
    {
        $propio = $this->cliente();
        $ajeno = Order::factory()->create();

        $this->actingAs($propio)
            ->get(route('orders.show', $ajeno))
            ->assertForbidden();

        $this->actingAs($propio)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertDontSee('Ajeno');
    }
}
