@extends('layouts.app')

@section('title', 'Carrito')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Mi carrito</h1>

    <x-flash-messages />

    @if($cart->items->count() > 0)
        <div class="bg-white rounded shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Producto</th>
                        <th class="px-4 py-2 text-left">Precio</th>
                        <th class="px-4 py-2 text-left">Cantidad</th>
                        <th class="px-4 py-2 text-left">Subtotal</th>
                        <th class="px-4 py-2 text-left">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @php $total = 0; @endphp
                    @foreach($cart->items as $item)
                        @php $subtotal = $item->product->sale_price * $item->quantity; $total += $subtotal; @endphp
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $item->product->title }}</td>
                            <td class="px-4 py-2">Bs {{ number_format($item->product->sale_price, 2) }}</td>
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('cart.update', $item) }}">
                                    @csrf
                                    <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" class="w-16 border rounded px-2 py-1">
                                    <button type="submit" class="ml-2 px-2 py-1 bg-gray-800 text-white rounded text-sm">Actualizar</button>
                                </form>
                            </td>
                            <td class="px-4 py-2">Bs {{ number_format($subtotal, 2) }}</td>
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('cart.destroy', $item) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded text-sm">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100">
                        <td colspan="3" class="px-4 py-2 text-right font-bold">Total</td>
                        <td class="px-4 py-2 font-bold">Bs {{ number_format($total, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-6 bg-white rounded shadow p-4">
            <h2 class="font-bold mb-1">Datos de envío</h2>
            <p class="text-sm text-gray-600 mb-3">Viene de tu perfil, cámbialo si lo necesitas para este pedido.</p>

            @if ($errors->any())
                <ul class="mb-3 text-sm text-red-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('checkout.store') }}">
                @csrf
                <label for="shipping_address" class="block text-sm font-medium mb-1">Dirección de envío</label>
                <textarea id="shipping_address" name="shipping_address" rows="3" required
                          class="w-full border rounded px-3 py-2 @error('shipping_address') border-red-500 @enderror"
                          placeholder="Av. Siempre Viva 742, La Paz">{{ old('shipping_address', auth()->user()->address) }}</textarea>

                <div class="mt-4 text-right">
                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded">Confirmar compra</button>
                </div>
            </form>
        </div>
    @else
        <p class="text-gray-600">Tu carrito está vacío.</p>
        <a href="{{ route('catalog.index') }}" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded">Ir al catálogo</a>
    @endif
@endsection
