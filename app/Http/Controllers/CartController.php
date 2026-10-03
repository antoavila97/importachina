<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $cart = $this->getCart();
        $cart->load('items.product');

        return view('cart.index', compact('cart'));
    }

    public function add(Request $request, Product $product)
    {
        $cart = $this->getCart();
        $item = CartItem::where('cart_id', $cart->id)->where('product_id', $product->id)->first();

        if ($item) {
            $item->increment('quantity');
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Producto añadido al carrito');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $this->authorize('update', $cartItem);
        $quantity = max(1, (int) $request->quantity);
        $cartItem->update(['quantity' => $quantity]);

        return redirect()->back()->with('success', 'Cantidad actualizada');
    }

    public function destroy(CartItem $cartItem)
    {
        $this->authorize('delete', $cartItem);
        $cartItem->delete();

        return redirect()->back()->with('success', 'Producto eliminado del carrito');
    }

    private function getCart(): Cart
    {
        $user = auth()->user();
        $cart = Cart::firstOrCreate([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return $cart;
    }
}
