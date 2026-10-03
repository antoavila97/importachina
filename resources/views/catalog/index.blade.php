@extends('layouts.app')

@section('title', 'Catálogo - ImportaChina')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold mb-4">Catálogo de productos</h1>

        <x-flash-messages />
        <form method="GET" action="{{ route('catalog.index') }}" class="flex flex-wrap gap-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar productos..." class="px-4 py-2 border rounded">
            <select name="category" class="px-4 py-2 border rounded">
                <option value="">Todas las categorías</option>
                @foreach($categories as $category)
                    <option value="{{ $category->slug }}" {{ request('category') === $category->slug ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded">Filtrar</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse($products as $product)
            <div class="bg-white rounded shadow overflow-hidden">
                <a href="{{ route('catalog.show', $product) }}">
                    <img src="{{ $product->image_url ?: 'https://placehold.co/600x400' }}" alt="{{ $product->title }}" class="w-full h-48 object-cover">
                </a>
                <div class="p-4">
                    <h3 class="font-semibold mb-2 line-clamp-2">{{ $product->title }}</h3>
                    <p class="text-gray-600 text-sm mb-2">{{ $product->category?->name }}</p>
                    <p class="text-lg font-bold text-green-600">Bs {{ number_format($product->sale_price, 2) }}</p>
                    <a href="{{ route('catalog.show', $product) }}" class="mt-3 inline-block px-4 py-2 bg-blue-600 text-white rounded text-sm">Ver detalle</a>
                </div>
            </div>
        @empty
            <p class="col-span-full text-gray-600">No se encontraron productos.</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $products->links() }}
    </div>
@endsection
