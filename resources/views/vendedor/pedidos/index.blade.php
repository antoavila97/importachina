@extends('layouts.app')

@section('title', 'Pedidos')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Pedidos</h1>

    <x-flash-messages />

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <a href="{{ route('vendedor.orders.index') }}"
           class="rounded shadow p-3 border {{ $filters['status'] ? 'border-gray-200' : 'border-blue-400 bg-blue-50' }}">
            <p class="text-xs uppercase text-gray-500">Todos</p>
            <p class="text-xl font-bold">{{ $counts['todos'] }}</p>
        </a>

        @foreach ($statuses as $status)
            <a href="{{ route('vendedor.orders.index', array_filter(['status' => $status, 'from' => $filters['from'], 'to' => $filters['to'], 'search' => $filters['search']])) }}"
               class="rounded shadow p-3 border {{ $filters['status'] === $status ? 'border-blue-400 bg-blue-50' : 'border-gray-200' }}">
                <p class="text-xs uppercase text-gray-500">{{ ucfirst($status) }}</p>
                <p class="text-xl font-bold">{{ $counts[$status] }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('vendedor.orders.index') }}" class="bg-white rounded shadow p-4 mb-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-sm">
                <span class="block text-gray-600 mb-1">Estado</span>
                <select name="status" class="w-full border-gray-300 rounded">
                    <option value="">Todos</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="block text-gray-600 mb-1">Desde</span>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="w-full border-gray-300 rounded">
            </label>

            <label class="text-sm">
                <span class="block text-gray-600 mb-1">Hasta</span>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="w-full border-gray-300 rounded">
            </label>

            <label class="text-sm">
                <span class="block text-gray-600 mb-1">Cliente</span>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Nombre o email"
                       class="w-full border-gray-300 rounded">
            </label>
        </div>

        @error('status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('from') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('to') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

        <div class="mt-3 flex gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Filtrar</button>
            <a href="{{ route('vendedor.orders.index') }}" class="px-4 py-2 bg-gray-200 rounded">Limpiar</a>
        </div>
    </form>

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left">N° Pedido</th>
                    <th class="px-4 py-2 text-left">Fecha</th>
                    <th class="px-4 py-2 text-left">Cliente</th>
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
                        <td class="px-4 py-2">
                            {{ $order->user?->name ?? 'Usuario eliminado' }}
                            <span class="block text-xs text-gray-500">{{ $order->user?->email }}</span>
                        </td>
                        <td class="px-4 py-2">Bs {{ number_format($order->total, 2) }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $order->statusBadgeClass() }}">
                                {{ $order->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <a href="{{ route('vendedor.orders.show', $order) }}" class="text-blue-600 hover:text-blue-800">
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-2 text-gray-600">No hay pedidos que coincidan con el filtro</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
@endsection