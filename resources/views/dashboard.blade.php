@extends('layouts.app')

@section('title', 'Panel')

@section('content')
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Hola, {{ $user->name }}</h1>
            <p class="text-sm text-gray-600">
                @if ($isAdmin)
                    Resumen de la tienda y de tus pendientes de hoy.
                @elseif ($isSeller)
                    Resumen de ventas y pedidos.
                @else
                    Tus pedidos y el cat&aacute;logo de productos.
                @endif
            </p>
        </div>

        @php
            $kpis = $isSeller
                ? [
                    ['Ventas del mes', 'Bs ' . number_format($monthRevenue, 2)],
                    ['Pedidos del mes', $monthOrders],
                    $isAdmin
                        ? ['Productos activos', $activeProducts]
                        : ['Por cobrar', 'Bs ' . number_format($pendingAmount, 2)],
                    ['Pendientes de despacho', $pendingOrders],
                ]
                : [
                    ['Mis pedidos', $myOrdersCount],
                    ['Pendientes', $myPending],
                    ['En camino', $myOnTheWay],
                    ['Entregados', $myDelivered],
                ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach ($kpis as [$label, $value])
                <div class="bg-white rounded shadow p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">{{ $isSeller ? 'Últimos pedidos' : 'Mis últimos pedidos' }}</h2>
                    <a href="{{ $isSeller ? route('vendedor.orders.index') : route('orders.index') }}"
                       class="text-sm text-blue-600 hover:underline">Ver todos</a>
                </div>

                @php
                    $orders = $isSeller ? $latestOrders : $latestMyOrders;
                @endphp

                @if ($orders->isEmpty())
                    <p class="text-sm text-gray-500">Todavía no hay pedidos.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-xs uppercase tracking-wide text-gray-500">
                                    <th class="py-2 pr-4">Pedido</th>
                                    @if ($isSeller)
                                        <th class="py-2 pr-4">Cliente</th>
                                    @endif
                                    <th class="py-2 pr-4">Total</th>
                                    <th class="py-2 pr-4">Estado</th>
                                    <th class="py-2">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orders as $order)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 pr-4">
                                            <a href="{{ $isSeller ? route('vendedor.orders.show', $order) : route('orders.show', $order) }}"
                                               class="text-blue-600 hover:underline">#{{ $order->id }}</a>
                                        </td>
                                        @if ($isSeller)
                                            <td class="py-2 pr-4">{{ $order->user?->name ?? '—' }}</td>
                                        @endif
                                        <td class="py-2 pr-4">Bs {{ number_format($order->total, 2) }}</td>
                                        <td class="py-2 pr-4">
                                            <span class="px-2 py-1 rounded text-xs font-medium {{ $order->statusBadgeClass() }}">
                                                {{ $order->statusLabel() }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-gray-500">{{ $order->created_at->format('d/m/Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                @if ($isAdmin)
                    <div class="bg-white rounded shadow p-6">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-lg font-bold">Stock bajo</h2>
                            <a href="{{ route('admin.products.index') }}" class="text-sm text-blue-600 hover:underline">Productos</a>
                        </div>
                        @forelse ($lowStock as $product)
                            <div class="flex items-center justify-between gap-3 border-b py-2 last:border-0 text-sm">
                                <a href="{{ route('admin.products.edit', $product) }}" class="hover:underline">
                                    {{ \Illuminate\Support\Str::limit($product->title, 40) }}
                                </a>
                                <span class="{{ $product->stock <= 2 ? 'text-red-600 font-semibold' : 'text-amber-600' }}">
                                    {{ $product->stock }} u.
                                </span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Ningún producto con stock bajo.</p>
                        @endforelse
                    </div>
                @endif

                <div class="bg-white rounded shadow p-6">
                    <h2 class="text-lg font-bold mb-3">Accesos rápidos</h2>
                    <div class="flex flex-wrap gap-2">
                        @if ($isAdmin)
                            <a href="{{ route('admin.products.create') }}"
                               class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Nuevo producto</a>
                            <a href="{{ route('admin.reports.index') }}"
                               class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Reportes</a>
                            <a href="{{ route('admin.api-sync.index') }}"
                               class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Sincronizar</a>
                        @elseif ($isSeller)
                            <a href="{{ route('vendedor.orders.index') }}"
                               class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Gestionar pedidos</a>
                            <a href="{{ route('catalog.index') }}"
                               class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Ver catálogo</a>
                        @else
                            <a href="{{ route('catalog.index') }}"
                               class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Seguir comprando</a>
                            <a href="{{ route('cart.index') }}"
                               class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Ver carrito</a>
                            <a href="{{ route('orders.index') }}"
                               class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Mis pedidos</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
