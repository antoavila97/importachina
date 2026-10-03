@extends('layouts.app')

@section('title', $product->title)

@section('content')
    @php
        $gallery = $product->images->pluck('url')->prepend($product->image_url)->filter()->unique()->values();
        $cover = $gallery->first() ?: 'https://placehold.co/600x400';
    @endphp

    <div class="bg-white rounded shadow p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <img src="{{ $cover }}" alt="{{ $product->title }}" class="w-full rounded">

                @if ($gallery->count() > 1)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($gallery as $imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $product->title }}"
                                 class="h-16 w-16 rounded object-cover border">
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                <h1 class="text-2xl font-bold mb-2">{{ $product->title }}</h1>
                <p class="text-gray-600 mb-2">Categoría: {{ $product->category?->name ?? 'Sin categoría' }}</p>
                <p class="text-sm text-gray-600 mb-2">
                    Stock disponible:
                    <strong class="{{ $product->stock > 0 ? 'text-gray-900' : 'text-red-600' }}">
                        {{ $product->stock }} unidad(es)
                    </strong>
                </p>
                <p class="text-3xl font-bold text-green-600 mb-4">Bs {{ number_format($product->sale_price, 2) }}</p>

                @if ($product->description)
                    <div class="mb-4 text-gray-700">{!! nl2br(e($product->description)) !!}</div>
                @else
                    <p class="mb-4 text-sm text-gray-500">Este producto no tiene descripción.</p>
                @endif

                @if ($product->source_url)
                    <a href="{{ $product->source_url }}" target="_blank" rel="noopener noreferrer"
                       class="block text-sm text-blue-600 hover:underline mb-4">
                        Ver el producto en AliExpress
                    </a>
                @endif

                @if ($product->stock > 0)
                    <a href="{{ route('cart.add', $product) }}" class="inline-block px-6 py-3 bg-blue-600 text-white rounded">
                        Añadir al carrito
                    </a>
                @else
                    <p class="px-6 py-3 bg-gray-200 text-gray-600 rounded inline-block">
                        Sin stock por el momento
                    </p>
                @endif
            </div>
        </div>
    </div>
@endsection
