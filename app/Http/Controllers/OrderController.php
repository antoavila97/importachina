<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = auth()->user()->orders()->latest()->paginate(10);

        return view('orders.index', compact('orders'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CheckoutRequest $request)
    {
        $user = auth()->user();
        $cart = Cart::with('items.product')->where('user_id', $user->id)->where('status', 'active')->first();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'El carrito está vacío');
        }

        $total = 0;
        foreach ($cart->items as $item) {
            $total += $item->product->sale_price * $item->quantity;
        }

        $shippingAddress = $request->shippingAddress();

        $order = DB::transaction(function () use ($user, $cart, $total, $shippingAddress) {
            $order = Order::create([
                'user_id' => $user->id,
                'total' => $total,
                'status' => 'pendiente',
                'shipping_address' => $shippingAddress,
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->product->sale_price,
                ]);
            }

            $cart->update(['status' => 'completed']);

            return $order;
        });

        return redirect()->route('orders.show', $order)->with('success', 'Pedido creado correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $this->authorize('view', $order);
        $order->load('items.product');

        return view('orders.show', compact('order'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
