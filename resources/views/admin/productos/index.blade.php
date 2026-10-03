@extends('layouts.app')

@section('title', 'Productos')

@section('content')
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">Productos</h1>
                <p class="text-sm text-gray-600">{{ $products->total() }} producto(s) en el catálogo</p>
            </div>

            <a href="{{ route('admin.products.create') }}"
               class="px-4 py-2 bg-blue-600 text-white rounded text-sm">
                Nuevo producto
            </a>
        </div>

        <x-flash-messages />

        <form method="GET" action="{{ route('admin.products.index') }}" class="bg-white rounded shadow p-4 mb-6">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm">
                    <span class="block text-gray-600 mb-1">Buscar</span>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Titulo del producto" class="w-full border-gray-300 rounded">
                </label>

                <label class="text-sm">
                    <span class="block text-gray-600 mb-1">Categoria</span>
                    <select name="category_id" class="w-full border-gray-300 rounded">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="text-sm">
                    <span class="block text-gray-600 mb-1">Estado</span>
                    <select name="status" class="w-full border-gray-300 rounded">
                        <option value="">Todos</option>
                        <option value="active" @selected(($filters['status'] ?? null) === 'active')>Activos</option>
                        <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactivos</option>
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">Filtrar</button>
                    <a href="{{ route('admin.products.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm">
                        Limpiar
                    </a>
                </div>
            </div>

            @error('status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('category_id') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </form>

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Producto</th>
                        <th class="px-4 py-2 text-left">Categoria</th>
                        <th class="px-4 py-2 text-right">Costo</th>
                        <th class="px-4 py-2 text-right">Margen</th>
                        <th class="px-4 py-2 text-right">Precio de venta</th>
                        <th class="px-4 py-2 text-right">Stock</th>
                        <th class="px-4 py-2 text-left">Estado</th>
                        <th class="px-4 py-2 text-left">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr class="border-t align-top">
                            <td class="px-4 py-2">
                                <div class="flex items-start gap-3">
                                    @if ($product->image_url)
                                        <img src="{{ $product->image_url }}" alt="{{ $product->title }}"
                                             class="h-12 w-12 rounded object-cover shrink-0">
                                    @else
                                        <span class="h-12 w-12 rounded bg-gray-200 shrink-0"></span>
                                    @endif
                                    <div>
                                        <p class="font-medium">{{ $product->title }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $product->external_id ?: 'Sin ID externo' }}
                                            @if ($product->images_count > 0)
                                                <span class="ml-1">({{ $product->images_count }} imagen(es))</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2">{{ $product->category?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">Bs {{ number_format($product->cost_price, 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ rtrim(rtrim((string) $product->margin_pct, '0'), '.') }}%</td>
                            <td class="px-4 py-2 text-right font-semibold">Bs {{ number_format($product->sale_price, 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ $product->stock }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $product->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $product->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.products.edit', $product) }}"
                                       class="text-blue-600 hover:text-blue-800 text-sm">Editar</a>

                                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
                                          onsubmit="return confirm('¿Eliminar «{{ $product->title }}»?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-gray-600">
                                No hay productos que coincidan con el filtro.
                                <a href="{{ route('admin.products.create') }}" class="text-blue-600 hover:underline">Crear el primero</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
@endsection
