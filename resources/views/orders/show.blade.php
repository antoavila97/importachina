@extends('layouts.app')

@section('title', 'Pedido #'.$order->id)

@section('content')
    <h1 class="text-2xl font-bold mb-4">Pedido #{{ $order->id }}</h1>

    <x-flash-messages />

    <div class="bg-white rounded shadow p-6 mb-6">
        <p class="mb-2"><strong>Fecha:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</p>
        <p class="mb-2"><strong>Estado:</strong> {{ $order->statusLabel() }}</p>
        <p class="mb-2"><strong>Total:</strong> Bs {{ number_format($order->total, 2) }}</p>
        <p class="mb-2"><strong>Dirección de envío:</strong> {{ $order->shipping_address ?: 'No especificada' }}</p>
    </div>

    <h2 class="text-lg font-bold mb-2">Productos</h2>
    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left">Producto</th>
                    <th class="px-4 py-2 text-left">Cantidad</th>
                    <th class="px-4 py-2 text-left">Precio unitario</th>
                    <th class="px-4 py-2 text-left">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $item->product?->title ?: 'Producto eliminado' }}</td>
                        <td class="px-4 py-2">{{ $item->quantity }}</td>
                        <td class="px-4 py-2">Bs {{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-4 py-2">Bs {{ number_format($item->unit_price * $item->quantity, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
