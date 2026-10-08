@extends('layouts.app')

@section('title', $product->exists ? 'Editar producto' : 'Nuevo producto')

@section('content')
    @php
        $isEdit = $product->exists;
        $action = $isEdit ? route('admin.products.update', $product) : route('admin.products.store');
        $costValue = (float) old('cost_price', $product->cost_price);
        $marginValue = (float) old('margin_pct', $product->margin_pct);
    @endphp

    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ $isEdit ? 'Editar producto' : 'Nuevo producto' }}</h1>
            <a href="{{ route('admin.products.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
                &larr; Volver al listado
            </a>
        </div>

        <x-flash-messages />

        <form method="POST" action="{{ $action }}"
              x-data="{ cost: {{ $costValue }}, margin: {{ $marginValue }}, get sale() { return (this.cost * (1 + this.margin / 100)).toFixed(2) } }"
              class="space-y-6">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded shadow p-6 space-y-4">
                <h2 class="text-lg font-bold">Datos del producto</h2>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">Titulo <span class="text-red-600">*</span></span>
                    <input type="text" name="title" value="{{ old('title', $product->title) }}" required
                           maxlength="255" class="w-full border-gray-300 rounded">
                    @error('title') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">Descripcion</span>
                    <textarea name="description" rows="5" maxlength="5000"
                              class="w-full border-gray-300 rounded">{{ old('description', $product->description) }}</textarea>
                    @error('description') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">Categoria</span>
                    <select name="category_id" class="w-full border-gray-300 rounded">
                        <option value="">Sin categoria</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="bg-white rounded shadow p-6 space-y-4">
                <h2 class="text-lg font-bold">Precios (HU-07)</h2>
                <p class="text-sm text-gray-600">
                    El precio de venta no se escribe: se calcula a partir del costo y del margen.
                </p>

                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="block text-sm">
                        <span class="block text-gray-600 mb-1">Costo (Bs) <span class="text-red-600">*</span></span>
                        <input type="number" name="cost_price" step="0.01" min="0" required
                               x-model.number="cost"
                               value="{{ old('cost_price', $product->cost_price) }}"
                               class="w-full border-gray-300 rounded">
                        @error('cost_price') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block text-sm">
                        <span class="block text-gray-600 mb-1">Margen (%) <span class="text-red-600">*</span></span>
                        <input type="number" name="margin_pct" step="0.01" min="0" max="500" required
                               x-model.number="margin"
                               value="{{ old('margin_pct', $product->margin_pct) }}"
                               class="w-full border-gray-300 rounded">
                        @error('margin_pct') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <div class="block text-sm">
                        <span class="block text-gray-600 mb-1">Precio de venta (calculado)</span>
                        <p class="px-3 py-2 bg-green-50 border border-green-200 rounded font-semibold text-green-700"
                           x-text="'Bs ' + sale"></p>
                    </div>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input type="hidden" name="price_locked" value="0">
                    <input type="checkbox" name="price_locked" value="1"
                           @checked(old('price_locked', $product->price_locked))
                           class="mt-1 rounded border-gray-300">
                    <span>
                        Fijar este precio (costo y margen).
                        <span class="text-xs text-gray-500">
                            Si esta marcado, la sincronizacion con AliExpress no lo modifica.
                        </span>
                    </span>
                </label>
                @error('price_locked') <span class="text-sm text-red-600">{{ $message }}</span> @enderror

                <label class="block text-sm max-w-xs">
                    <span class="block text-gray-600 mb-1">Stock <span class="text-red-600">*</span></span>
                    <input type="number" name="stock" min="0" required
                           value="{{ old('stock', $product->stock ?? 0) }}"
                           class="w-full border-gray-300 rounded">
                    @error('stock') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="bg-white rounded shadow p-6 space-y-4">
                <h2 class="text-lg font-bold">Imagenes y origen</h2>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">URL de la imagen principal</span>
                    <div class="flex gap-2" x-data="{ url: @js(old('image_url', $product->image_url)) }">
                        <input type="url" name="image_url" maxlength="255"
                               x-ref="campo" x-model="url"
                               value="{{ old('image_url', $product->image_url) }}"
                               class="flex-1 border-gray-300 rounded" placeholder="https://...">
                        <button type="button" x-show="url" @click="url = ''; $refs.campo.focus()"
                                class="shrink-0 rounded border border-gray-300 px-3 text-sm text-gray-600 hover:bg-gray-100">
                            Vaciar
                        </button>
                    </div>
                    @error('image_url') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">URL del producto en AliExpress</span>
                    <div class="flex gap-2" x-data="{ url: @js(old('source_url', $product->source_url)) }">
                        <input type="url" name="source_url" maxlength="255"
                               x-ref="campo" x-model="url"
                               value="{{ old('source_url', $product->source_url) }}"
                               class="flex-1 border-gray-300 rounded" placeholder="https://...">
                        <button type="button" x-show="url" @click="url = ''; $refs.campo.focus()"
                                class="shrink-0 rounded border border-gray-300 px-3 text-sm text-gray-600 hover:bg-gray-100">
                            Vaciar
                        </button>
                    </div>
                    @error('source_url') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="block text-gray-600 mb-1">Identificador externo</span>
                    <input type="text" name="external_id" maxlength="255"
                           value="{{ old('external_id', $product->external_id) }}"
                           class="w-full border-gray-300 rounded">
                    <span class="text-xs text-gray-500">
                        Lo usa la sincronizacion con la API para no duplicar productos.
                    </span>
                    @error('external_id') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                @if ($isEdit && $product->images->isNotEmpty())
                    <div>
                        <p class="block text-gray-600 mb-2">Imagenes registradas</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($product->images as $image)
                                <img src="{{ $image->url }}" alt="{{ $product->title }}"
                                     class="h-16 w-16 rounded object-cover border">
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded shadow p-6 space-y-4">
                <h2 class="text-lg font-bold">Visibilidad</h2>

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1"
                           @checked(old('active', $product->active ?? true)) class="rounded border-gray-300">
                    <span>Producto activo: aparece en el catalogo publico.</span>
                </label>
                @error('active') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded">
                    {{ $isEdit ? 'Guardar cambios' : 'Crear producto' }}
                </button>
                <a href="{{ route('admin.products.index') }}" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
@endsection
