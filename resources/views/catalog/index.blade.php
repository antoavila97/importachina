@extends('layouts.app')

@section('title', __('Catálogo') . ' - ImportaChina')

@section('content')
    {{-- Encabezado + buscador + categorias + Filtrar, todo en una sola fila --}}
    <div class="mb-6 space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">
                    {{ __('Catálogo de productos') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $products->total() === 1
                        ? __('1 producto encontrado')
                        : __(':count productos encontrados', ['count' => $products->total()]) }}
                </p>
            </div>
        </div>

        <x-flash-messages />

        <form method="GET" action="{{ route('catalog.index') }}"
              class="flex flex-col gap-3 sm:flex-row sm:items-center">
            {{-- Buscador --}}
            <div class="relative flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"/>
                    </svg>
                </span>
                <input
                    type="search"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="{{ __('Buscar productos...') }}"
                    aria-label="{{ __('Buscar productos') }}"
                    class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
                >
            </div>

            {{-- Categorias --}}
            <select
                name="category"
                aria-label="{{ __('Filtrar por categoría') }}"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none sm:w-auto"
            >
                <option value="">{{ __('Todas las categorías') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>

            <button
                type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-300 focus:outline-none focus-visible:underline"
            >
                {{ __('Filtrar') }}
            </button>

            @if (request()->hasAny(['q', 'category']))
                <a href="{{ route('catalog.index') }}"
                   class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition hover:text-gray-900 hover:underline">
                    {{ __('Limpiar') }}
                </a>
            @endif
        </form>
    </div>

    {{-- 1 columna en movil, 2 en tablet, 3-4 en escritorio --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($products as $product)
            <article class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-lg">
                <a href="{{ route('catalog.show', $product) }}" class="relative block overflow-hidden bg-gray-100">
                    <x-product-image
                        :src="$product->image_url"
                        :alt="$product->title"
                        class="aspect-[4/3] w-full object-cover transition duration-300 group-hover:scale-105"
                    />

                    {{-- Badges: sin stock tiene prioridad sobre "nuevo" --}}
                    @if ($product->stock <= 0)
                        <span class="absolute top-2 left-2 rounded-full bg-gray-900/90 px-2.5 py-1 text-xs font-semibold text-white">
                            {{ __('Agotado') }}
                        </span>
                    @elseif ($product->created_at && $product->created_at->gt(now()->subDays(15)))
                        <span class="absolute top-2 left-2 rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white">
                            {{ __('Nuevo') }}
                        </span>
                    @endif
                </a>

                <div class="flex flex-1 flex-col p-4">
                    @if ($product->category)
                        <p class="mb-1 text-xs font-medium tracking-wide text-indigo-600 uppercase">
                            {{ $product->category->name }}
                        </p>
                    @endif

                    <h3 class="text-sm font-semibold text-gray-900">
                        <a href="{{ route('catalog.show', $product) }}" class="line-clamp-2 hover:text-indigo-700">
                            {{ $product->title }}
                        </a>
                    </h3>

                    <div class="mt-auto pt-3">
                        <p class="text-lg font-bold text-gray-900">
                            Bs {{ number_format($product->sale_price, 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            @if ($product->stock > 0)
                                {{ $product->stock === 1
                                    ? __('1 unidad disponible')
                                    : __(':count unidades disponibles', ['count' => $product->stock]) }}
                            @else
                                {{ __('Sin stock por el momento') }}
                            @endif
                        </p>

                        <a href="{{ route('catalog.show', $product) }}"
                           class="mt-3 inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold transition
                                  {{ $product->stock > 0
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        : 'bg-gray-100 text-gray-400 pointer-events-none' }}">
                            {{ $product->stock > 0 ? __('Ver detalle') : __('Agotado') }}
                        </a>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                <svg class="h-12 w-12 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"/>
                </svg>
                <h3 class="mt-4 text-base font-semibold text-gray-900">{{ __('No se encontraron productos') }}</h3>
                <p class="mt-1 max-w-sm text-sm text-gray-500">
                    {{ __('Prueba con otra búsqueda o quita los filtros aplicados.') }}
                </p>
                @if (request()->hasAny(['q', 'category']))
                    <a href="{{ route('catalog.index') }}"
                       class="mt-5 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                        {{ __('Ver todo el catálogo') }}
                    </a>
                @endif
            </div>
        @endforelse
    </div>

    @if ($products->hasPages())
        <div class="mt-8">
            {{ $products->onEachSide(1)->links() }}
        </div>
    @endif
@endsection