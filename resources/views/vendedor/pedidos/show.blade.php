@extends('layouts.app')

@use('App\Models\Payment')

@section('title', 'Pedido #'.$order->id)

@section('content')
    @php
        // HU-15: volver a la lista sin perder los filtros aplicados.
        $backUrl = route('vendedor.orders.index', request()->query());
        $paidAmount = (float) $order->payments->where('status', Payment::STATUS_COMPLETED)->sum('amount');
    @endphp

    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Pedido #{{ $order->id }}</h1>
        <a href="{{ $backUrl }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Volver a la lista</a>
    </div>

    <x-flash-messages />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">Datos del pedido</h2>
                    <span class="px-2 py-1 rounded text-xs font-medium {{ $order->statusBadgeClass() }}">
                        {{ $order->statusLabel() }}
                    </span>
                </div>

                <dl class="grid gap-2 sm:grid-cols-2">
                    <div><dt class="text-sm text-gray-500">Fecha</dt>
                        <dd>{{ $order->created_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Total</dt>
                        <dd class="font-semibold">Bs {{ number_format($order->total, 2) }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Unidades</dt>
                        <dd>{{ $order->items->sum('quantity') }}</dd></div>
                    <div><dt class="text-sm text-gray-500">Pagado</dt>
                        <dd>{{ $paidAmount > 0 ? 'Bs ' . number_format($paidAmount, 2) : 'Sin pagos' }}</dd></div>
                </dl>
            </div>

            <div class="bg-white rounded shadow overflow-hidden">
                <h2 class="text-lg font-bold p-6 pb-0">Productos</h2>
                <table class="min-w-full mt-4">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left">Producto</th>
                            <th class="px-4 py-2 text-left">Cantidad</th>
                            <th class="px-4 py-2 text-left">Precio unitario</th>
                            <th class="px-4 py-2 text-left">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->items as $item)
                            <tr class="border-t">
                                <td class="px-4 py-2">{{ $item->product?->title ?: 'Producto eliminado' }}</td>
                                <td class="px-4 py-2">{{ $item->quantity }}</td>
                                <td class="px-4 py-2">Bs {{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-4 py-2">Bs {{ number_format($item->unit_price * $item->quantity, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-2 text-gray-600">El pedido no tiene productos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded shadow p-6">
                <h2 class="text-lg font-bold mb-4">Pagos registrados</h2>

                @if ($order->payments->isEmpty())
                    <p class="text-gray-600 text-sm">Todavia no se registro ningun pago para este pedido.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($order->payments as $payment)
                            <div class="border rounded p-3 {{ $payment->isVoided() ? 'bg-gray-50' : '' }}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-medium {{ $payment->isVoided() ? 'line-through text-gray-500' : '' }}">
                                            Bs {{ number_format($payment->amount, 2) }} — {{ $payment->methodLabel() }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $payment->paid_at?->format('d/m/Y') ?? 'Sin fecha' }}
                                            <span class="px-2 py-0.5 rounded ml-1 {{ $payment->statusBadgeClass() }}">
                                                {{ $payment->statusLabel() }}
                                            </span>
                                        </p>
                                    </div>

                                    @unless ($payment->isVoided())
                                        <div class="flex items-center gap-2">
                                            <details class="relative">
                                                <summary class="cursor-pointer text-sm text-blue-600 hover:text-blue-800 list-none">
                                                    Editar
                                                </summary>

                                                <form method="POST"
                                                      action="{{ route('vendedor.payments.update', [$order, $payment]) }}"
                                                      class="mt-2 w-64 space-y-2 bg-gray-50 border rounded p-3">
                                                    @csrf
                                                    @method('PUT')

                                                    <label class="block text-xs">
                                                        <span class="block text-gray-600 mb-1">Metodo</span>
                                                        <select name="method" class="w-full border-gray-300 rounded text-sm">
                                                            @foreach ($paymentMethods as $value => $label)
                                                                <option value="{{ $value }}" @selected($value === $payment->method)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>

                                                    <label class="block text-xs">
                                                        <span class="block text-gray-600 mb-1">Monto (Bs)</span>
                                                        <input type="number" name="amount" step="0.01" min="0.01" required
                                                               value="{{ number_format($payment->amount, 2, '.', '') }}"
                                                               class="w-full border-gray-300 rounded text-sm">
                                                    </label>

                                                    <label class="block text-xs">
                                                        <span class="block text-gray-600 mb-1">Fecha del pago</span>
                                                        <input type="date" name="paid_at"
                                                               value="{{ $payment->paid_at?->format('Y-m-d') }}"
                                                               class="w-full border-gray-300 rounded text-sm">
                                                    </label>

                                                    @error('method') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                    @error('amount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                    @error('paid_at') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                                                    <button type="submit" class="w-full px-3 py-1.5 bg-blue-600 text-white rounded text-sm">
                                                        Guardar cambios
                                                    </button>
                                                </form>
                                            </details>

                                            <form method="POST" action="{{ route('vendedor.payments.void', [$order, $payment]) }}"
                                                  onsubmit="return confirm('¿Anular este pago de Bs {{ number_format($payment->amount, 2) }}?')">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                                                    Anular
                                                </button>
                                            </form>
                                        </div>
                                    @endunless
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded shadow p-6">
                <h2 class="text-lg font-bold mb-3">Cliente y envio</h2>
                <p class="mb-1"><strong>Nombre:</strong> {{ $order->user?->name ?? 'Usuario eliminado' }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $order->user?->email ?? '—' }}</p>
                <p class="mb-1"><strong>Telefono:</strong> {{ $order->user?->phone ?: 'No especificado' }}</p>
                <p class="mb-1"><strong>Direccion:</strong> {{ $order->shipping_address ?: 'No especificada' }}</p>
            </div>

            <div class="bg-white rounded shadow p-6">
                <h2 class="text-lg font-bold mb-3">Cambiar estado</h2>

                <form method="POST" action="{{ route('vendedor.orders.status', $order) }}">
                    @csrf
                    @method('PUT')

                    <label class="text-sm block mb-1 text-gray-600" for="status">Estado</label>
                    <select id="status" name="status" class="w-full border-gray-300 rounded mb-3">
                        @foreach ($order->nextStatuses() as $status)
                            <option value="{{ $status }}" @selected($status === $order->status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>

                    @error('status') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror

                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded">Actualizar estado</button>
                </form>
            </div>

            <div class="bg-white rounded shadow p-6">
                <h2 class="text-lg font-bold mb-3">Registrar pago</h2>

                @if ($order->isClosed())
                    <p class="text-sm text-gray-600">El pedido ya fue entregado, no admite nuevos pagos.</p>
                @else
                    <form method="POST" action="{{ route('vendedor.payments.store', $order) }}" class="space-y-3">
                        @csrf

                        <label class="text-sm block">
                            <span class="block text-gray-600 mb-1">Metodo</span>
                            <select name="method" class="w-full border-gray-300 rounded">
                                @foreach ($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected($value === 'efectivo')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="text-sm block">
                            <span class="block text-gray-600 mb-1">Monto (Bs)</span>
                            <input type="number" name="amount" step="0.01" min="0.01"
                                   value="{{ old('amount', number_format($order->total, 2, '.', '')) }}"
                                   class="w-full border-gray-300 rounded">
                        </label>

                        <label class="text-sm block">
                            <span class="block text-gray-600 mb-1">Fecha del pago</span>
                            <input type="date" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d')) }}"
                                   class="w-full border-gray-300 rounded">
                        </label>

                        @error('method') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('amount') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('paid_at') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded">
                            Registrar pago y marcar como pagado
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection