@extends('layouts.app')

@use('App\Console\Commands\SyncAliExpressProducts')
@use('App\Services\AliExpressService')

@section('title', 'Sincronización API')

@section('content')
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold mb-2">Sincronización de productos (AliExpress)</h1>
        <p class="text-sm text-gray-600 mb-6">
            HU-05: importa entre {{ SyncAliExpressProducts::MIN_LIMIT }} y
            {{ SyncAliExpressProducts::MAX_LIMIT }} productos por corrida.
            Un producto ya importado se actualiza, nunca se duplica.
        </p>

        <x-flash-messages />

        @if ($mode === AliExpressService::MODE_DEMO)
            <div class="bg-blue-100 border border-blue-400 text-blue-800 px-4 py-3 rounded mb-6">
                <p class="font-semibold">Modo demostración</p>
                <p class="text-sm mt-1">
                    La API de AliExpress no está disponible en Bolivia: el registro exige
                    verificar un número de celular y el país no figura entre los soportados.
                    Por eso la sincronización corre contra un <strong>catálogo local</strong>
                    con la misma forma de la respuesta real, y el flujo completo (lotes,
                    no duplicación, precio de venta y registro de cada corrida) funciona igual.
                    Si se cargan <code>ALIEXPRESS_APP_KEY</code> y <code>ALIEXPRESS_APP_SECRET</code>,
                    manda la API real.
                </p>
            </div>
        @elseif ($mode === AliExpressService::MODE_UNCONFIGURED)
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-6">
                <p class="font-semibold">Faltan las credenciales de la API</p>
                <p class="text-sm mt-1">
                    Cargá <code>ALIEXPRESS_APP_KEY</code> y <code>ALIEXPRESS_APP_SECRET</code> en el
                    <code>.env</code> (se registran en developers.aliexpress.com) o activá
                    <code>ALIEXPRESS_DEMO=true</code> para sincronizar con el catálogo local.
                    Las claves se leen solo desde el servidor.
                </p>
            </div>
        @endif

        <div class="bg-white rounded shadow p-6 mb-6">
            <form method="POST" action="{{ route('admin.api-sync.sync') }}" class="space-y-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="block text-sm sm:col-span-2">
                        <span class="block text-gray-600 mb-1">Buscar por palabra clave</span>
                        <input type="text" name="keyword" value="{{ old('keyword') }}"
                               placeholder="{{ config('services.aliexpress.default_keyword') }}"
                               class="w-full border-gray-300 rounded">
                        <span class="text-xs text-gray-500">Si lo dejás vacío se usa ALIEXPRESS_DEFAULT_KEYWORD.</span>
                        @error('keyword') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block text-sm">
                        <span class="block text-gray-600 mb-1">Cantidad a importar</span>
                        <select name="limit" class="w-full border-gray-300 rounded">
                            @for ($i = 20; $i <= 50; $i += 10)
                                <option value="{{ $i }}" @selected((int) old('limit', 20) === $i)>{{ $i }}</option>
                            @endfor
                        </select>
                        @error('limit') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>

                <button type="submit" @disabled($mode === AliExpressService::MODE_UNCONFIGURED)
                        class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">
                    Sincronizar productos
                </button>
            </form>

            <p class="text-xs text-gray-500 mt-4">
                El precio de los productos importados se guarda <strong>tal cual lo devuelve la API (USD)</strong>.
                El margen (<code>ALIEXPRESS_MARGIN_PCT</code>) se aplica sobre ese costo para calcular el precio de venta.
            </p>
        </div>

        <div class="bg-white shadow rounded overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Fecha</th>
                        <th class="px-4 py-2 text-left">Usuario</th>
                        <th class="px-4 py-2 text-left">Items</th>
                        <th class="px-4 py-2 text-left">Estado</th>
                        <th class="px-4 py-2 text-left">Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-t">
                            <td class="px-4 py-2 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $log->user?->name ?? 'Consola' }}</td>
                            <td class="px-4 py-2">{{ $log->items_imported }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $log->statusBadgeClass() }}">
                                    {{ $log->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm">{{ $log->message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-gray-600">Todavía no se ejecutó ninguna sincronización.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
@endsection
