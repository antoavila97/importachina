@extends('layouts.app')

@section('title', $product->title . ' - ImportaChina')

@section('content')
    @php
        $gallery = $product->images->pluck('url')->prepend($product->image_url)->filter()->unique()->values();
        $cover = $gallery->first();
    @endphp

    {{-- Migas de pan --}}
    <nav class="mb-4 text-sm text-gray-500">
        <a href="{{ route('catalog.index') }}" class="transition hover:text-indigo-700 hover:underline">
            {{ __('Catálogo') }}
        </a>
        <span class="mx-1.5" aria-hidden="true">/</span>
        @if ($product->category)
            <a href="{{ route('catalog.index', ['category' => $product->category->slug]) }}"
               class="transition hover:text-indigo-700 hover:underline">
                {{ $product->category->name }}
            </a>
            <span class="mx-1.5" aria-hidden="true">/</span>
        @endif
        <span class="text-gray-900">{{ $product->title }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
        {{-- Galeria --}}
        <div>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-100">
                <x-product-image
                    :src="$cover"
                    :alt="$product->title"
                    class="aspect-[4/3] w-full object-cover"
                />
            </div>

            @if ($gallery->count() > 1)
                <div class="mt-3 grid grid-cols-5 gap-2">
                    @foreach ($gallery as $imageUrl)
                        <a href="{{ $imageUrl }}" target="_blank" rel="noopener noreferrer"
                           class="overflow-hidden rounded-lg border border-gray-200 bg-gray-100 transition hover:border-indigo-400">
                            <x-product-image
                                :src="$imageUrl"
                                :alt="$product->title"
                                class="aspect-square w-full object-cover"
                            />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Informacion --}}
        <div class="flex flex-col">
            @if ($product->category)
                <a href="{{ route('catalog.index', ['category' => $product->category->slug]) }}"
                   class="w-fit text-xs font-medium tracking-wide text-indigo-600 uppercase hover:underline">
                    {{ $product->category->name }}
                </a>
            @endif

            <h1 class="mt-2 text-2xl font-bold text-gray-900 sm:text-3xl">
                {{ $product->title }}
            </h1>

            <p class="mt-3 text-3xl font-bold text-gray-900">
                Bs {{ number_format($product->sale_price, 2) }}
            </p>

            <p class="mt-2 text-sm text-gray-600">
                {{ __('Stock disponible:') }}
                <strong class="{{ $product->stock > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $product->stock === 1
                        ? __('1 unidad')
                        : __(':count unidades', ['count' => $product->stock]) }}
                </strong>
            </p>

            @if ($product->description)
                <div class="mt-6 border-t border-gray-200 pt-6 text-sm leading-relaxed text-gray-700">
                    {!! nl2br(e($product->description)) !!}
                </div>
            @else
                <p class="mt-6 border-t border-gray-200 pt-6 text-sm text-gray-500">
                    {{ __('Este producto no tiene descripción.') }}
                </p>
            @endif

            <div class="mt-auto pt-8">
                @if ($product->stock > 0)
                    <a href="{{ route('cart.add', $product) }}"
                       class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-300 focus:outline-none sm:w-auto">
                        {{ __('Añadir al carrito') }}
                    </a>
                @else
                    <p class="inline-flex w-full items-center justify-center rounded-lg bg-gray-100 px-6 py-3 text-base font-semibold text-gray-400 sm:w-auto">
                        {{ __('Sin stock por el momento') }}
                    </p>
                @endif

                @if ($product->source_url)
                    <a href="{{ $product->source_url }}" target="_blank" rel="noopener noreferrer"
                       class="mt-3 flex items-center gap-1.5 text-sm font-medium text-gray-600 transition hover:text-indigo-700 hover:underline">
                        {{ __('Ver el producto en AliExpress') }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6m0-6L10 14M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-8">
        <a href="{{ route('catalog.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition hover:text-indigo-700 hover:underline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
            </svg>
            {{ __('Volver al catálogo') }}
        </a>
    </div>
@endsection