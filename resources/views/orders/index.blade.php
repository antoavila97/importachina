@extends('layouts.app')

@section('title', 'Mis pedidos')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Mis pedidos</h1>

    <x-flash-messages />

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left">N° Pedido</th>
                    <th class="px-4 py-2 text-left">Fecha</th>
                    <th class="px-4 py-2 text-left">Total</th>
                    <th class="px-4 py-2 text-left">Estado</th>
                    <th class="px-4 py-2 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr class="border-t">
                        <td class="px-4 py-2">#{{ $order->id }}</td>
                        <td class="px-4 py-2">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">Bs {{ number_format($order->total, 2) }}</td>
                        <td class="px-4 py-2">{{ $order->statusLabel() }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:text-blue-800">Ver detalle</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-2 text-gray-600">No tienes pedidos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
@endsection
