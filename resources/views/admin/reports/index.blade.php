@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
    @php
        // Filtros que se conservan al cambiar de pestaña o al pedir el PDF.
        $preserved = collect(request()->only(['from', 'to', 'estado', 'categoria', 'activo', 'stock', 'rol', 'situacion']))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $pdfUrl = route('admin.reports.pdf', array_merge($preserved, ['tipo' => $tipo]));
    @endphp

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <div>
                <h1 class="text-2xl font-bold">Reportes</h1>
                <p class="text-sm text-gray-600">
                    {{ $report['title'] }} · {{ $report['period'] }}
                </p>
            </div>

            <a href="{{ $pdfUrl }}"
               class="px-4 py-2 bg-indigo-600 text-white rounded text-sm font-semibold hover:bg-indigo-700">
                Descargar PDF
            </a>
        </div>

        {{-- Pestañas --}}
        <div class="border-b border-gray-200 mb-6 overflow-x-auto">
            <nav class="flex gap-1 min-w-max">
                @foreach($tipos as $key => $label)
                    <a href="{{ route('admin.reports.index', array_merge($preserved, ['tipo' => $key])) }}"
                       class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ $tipo === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
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

        {{-- Filtros de la pestaña --}}
        <form method="GET" action="{{ route('admin.reports.index') }}" class="flex flex-wrap items-end gap-2 mb-6">
            <input type="hidden" name="tipo" value="{{ $tipo }}">

            <div>
                <label for="from" class="block text-xs font-medium text-gray-600 mb-1">
                    Fecha inicial{{ $tipo === 'usuarios' ? ' (alta, opcional)' : '' }}
                </label>
                <input type="date" id="from" name="from"
                       value="{{ request('from', $tipo === 'usuarios' ? '' : $from->format('Y-m-d')) }}"
                       class="border rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label for="to" class="block text-xs font-medium text-gray-600 mb-1">
                    Fecha final{{ $tipo === 'usuarios' ? ' (alta, opcional)' : '' }}
                </label>
                <input type="date" id="to" name="to"
                       value="{{ request('to', $tipo === 'usuarios' ? '' : $to->format('Y-m-d')) }}"
                       class="border rounded px-3 py-2 text-sm">
            </div>

            @if($tipo === 'pedidos')
                <div>
                    <label for="estado" class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                    <select id="estado" name="estado" class="border rounded px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Models\Order::STATUSES as $estado)
                            <option value="{{ $estado }}" @selected(request('estado') === $estado)>
                                {{ ucfirst($estado) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($tipo === 'productos')
                <div>
                    <label for="categoria" class="block text-xs font-medium text-gray-600 mb-1">Categoría</label>
                    <select id="categoria" name="categoria" class="border rounded px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('categoria') === (string) $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="activo" class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                    <select id="activo" name="activo" class="border rounded px-3 py-2 text-sm">
                        <option value="todos" @selected(request('activo', 'todos') === 'todos')>Todos</option>
                        <option value="1" @selected(request('activo') === '1')>Activos</option>
                        <option value="0" @selected(request('activo') === '0')>Inactivos</option>
                    </select>
                </div>

                <div>
                    <label for="stock" class="block text-xs font-medium text-gray-600 mb-1">Stock</label>
                    <select id="stock" name="stock" class="border rounded px-3 py-2 text-sm">
                        <option value="todos" @selected(request('stock', 'todos') === 'todos')>Todos</option>
                        <option value="con" @selected(request('stock') === 'con')>Con stock</option>
                        <option value="sin" @selected(request('stock') === 'sin')>Sin stock</option>
                    </select>
                </div>
            @endif

            @if($tipo === 'usuarios')
                <div>
                    <label for="rol" class="block text-xs font-medium text-gray-600 mb-1">Rol</label>
                    <select id="rol" name="rol" class="border rounded px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) request('rol') === (string) $role->id)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="situacion" class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                    <select id="situacion" name="situacion" class="border rounded px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        <option value="active" @selected(request('situacion') === 'active')>Activos</option>
                        <option value="inactive" @selected(request('situacion') === 'inactive')>Inactivos</option>
                    </select>
                </div>
            @endif

            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('admin.reports.index', ['tipo' => $tipo]) }}"
               class="px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm">Limpiar</a>
        </form>

        {{-- Resumen --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach($report['summary'] as $item)
                <div class="bg-white rounded shadow p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ $item['label'] }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $item['value'] }}</p>
                </div>
            @endforeach
        </div>

        @if(! empty($report['note']))
            <p class="text-sm text-gray-600 mb-6">{{ $report['note'] }}</p>
        @endif

        {{-- Secciones del reporte --}}
        @foreach($report['sections'] as $section)
            <div class="bg-white rounded shadow overflow-hidden mb-6">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="font-semibold">{{ $section['title'] }}</h2>
                </div>

                @if(empty($section['rows']))
                    <p class="px-4 py-6 text-gray-600 text-sm">{{ $section['empty'] ?? 'Sin datos.' }}</p>
                @elseif($section['type'] === 'bars')
                    @php $maxBar = max(array_column($section['rows'], 'total')) ?: 1; @endphp
                    <div class="p-4 space-y-2">
                        @foreach($section['rows'] as $day)
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-600 w-20 shrink-0">{{ $day['label'] }}</span>
                                <div class="flex-1 bg-gray-100 rounded h-5 overflow-hidden">
                                    <div class="bg-indigo-600 h-5"
                                         style="width: {{ round($day['total'] / $maxBar * 100, 1) }}%"></div>
                                </div>
                                <span class="text-xs font-semibold w-24 text-right shrink-0">{{ $day['value'] }}</span>
                                <span class="text-xs text-gray-500 w-16 text-right shrink-0">{{ $day['orders'] }} ped.</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-100">
                                <tr>
                                    @foreach($section['columns'] as $column)
                                        <th class="px-4 py-2 {{ $column['align'] === 'right' ? 'text-right' : ($column['align'] === 'center' ? 'text-center' : 'text-left') }}">
                                            {{ $column['label'] }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($section['rows'] as $row)
                                    <tr class="border-t">
                                        @foreach($row as $index => $cell)
                                            <td class="px-4 py-2 {{ ($section['columns'][$index]['align'] ?? 'left') === 'right' ? 'text-right' : (($section['columns'][$index]['align'] ?? 'left') === 'center' ? 'text-center' : 'text-left') }}">
                                                {{ $cell }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endsection
