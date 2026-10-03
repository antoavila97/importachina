@extends('layouts.app')

@section('title', 'Reporte de ventas')

@section('content')
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">Reporte de ventas</h1>
                <p class="text-sm text-gray-600">
                    Del {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }}
                    ({{ $daily->count() }} días)
                </p>
            </div>

            <form method="GET" action="{{ route('admin.reports.index') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="from" class="block text-xs font-medium text-gray-600 mb-1">Fecha inicial</label>
                    <input type="date" id="from" name="from" value="{{ $from->format('Y-m-d') }}"
                           class="border rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="to" class="block text-xs font-medium text-gray-600 mb-1">Fecha final</label>
                    <input type="date" id="to" name="to" value="{{ $to->format('Y-m-d') }}"
                           class="border rounded px-3 py-2 text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">Filtrar</button>
                <a href="{{ route('admin.reports.index') }}"
                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm">Limpiar</a>
            </form>
        </div>

        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded shadow p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Total vendido</p>
                <p class="text-2xl font-bold text-gray-900">Bs {{ number_format($revenue, 2) }}</p>
            </div>
            <div class="bg-white rounded shadow p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Pedidos vendidos</p>
                <p class="text-2xl font-bold text-gray-900">{{ $ordersCount }}</p>
            </div>
            <div class="bg-white rounded shadow p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Ticket promedio</p>
                <p class="text-2xl font-bold text-gray-900">Bs {{ number_format($averageTicket, 2) }}</p>
            </div>
            <div class="bg-white rounded shadow p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Unidades vendidas</p>
                <p class="text-2xl font-bold text-gray-900">{{ $unitsSold }}</p>
            </div>
        </div>

        <p class="text-sm text-gray-600 mb-6">
            Cuentan como venta los pedidos en estado
            <strong>Pagado, Enviado y Entregado</strong>. Quedan {{ $pendingCount }} pedido(s) pendientes en el periodo.
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded shadow overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="font-semibold">5 productos más vendidos</h2>
                </div>

                @if($topProducts->isEmpty())
                    <p class="px-4 py-6 text-gray-600 text-sm">No hay ventas en el periodo seleccionado.</p>
                @else
                    <table class="min-w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left">#</th>
                                <th class="px-4 py-2 text-left">Producto</th>
                                <th class="px-4 py-2 text-right">Unidades</th>
                                <th class="px-4 py-2 text-right">Vendido</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topProducts as $index => $item)
                                <tr class="border-t">
                                    <td class="px-4 py-2 text-gray-500">{{ $index + 1 }}</td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center gap-2">
                                            @if($item->image_url)
                                                <img src="{{ $item->image_url }}" alt="{{ $item->title }}"
                                                     class="h-10 w-10 rounded object-cover">
                                            @endif
                                            <span>{{ $item->title }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ (int) $item->units }}</td>
                                    <td class="px-4 py-2 text-right">Bs {{ number_format((float) $item->revenue, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="bg-white rounded shadow overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="font-semibold">Venta por día</h2>
                </div>

                @php $maxDay = $daily->max('total'); @endphp

                @if($daily->isEmpty() || $maxDay <= 0)
                    <p class="px-4 py-6 text-gray-600 text-sm">No hay ventas en el periodo seleccionado.</p>
                @else
                    <div class="p-4 space-y-2">
                        @foreach($daily as $day)
                            @if($day['total'] > 0)
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-600 w-20 shrink-0">
                                        {{ $day['date']->format('d/m') }}
                                    </span>
                                    <div class="flex-1 bg-gray-100 rounded h-5 overflow-hidden">
                                        <div class="bg-blue-600 h-5"
                                             style="width: {{ round($day['total'] / $maxDay * 100, 1) }}%"></div>
                                    </div>
                                    <span class="text-xs font-semibold w-24 text-right shrink-0">
                                        Bs {{ number_format($day['total'], 2) }}
                                    </span>
                                    <span class="text-xs text-gray-500 w-16 text-right shrink-0">
                                        {{ $day['orders'] }} ped.
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection