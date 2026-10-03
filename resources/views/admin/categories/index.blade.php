@extends('layouts.app')

@section('title', 'Categorias')

@section('content')
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">Categorias</h1>
                <p class="text-sm text-gray-600">{{ $categories->total() }} categoria(s) definidas</p>
            </div>

            <a href="{{ route('admin.categories.create') }}"
               class="px-4 py-2 bg-blue-600 text-white rounded text-sm">
                Nueva categoria
            </a>
        </div>

        <x-flash-messages />

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Nombre</th>
                        <th class="px-4 py-2 text-left">Slug</th>
                        <th class="px-4 py-2 text-left">ID externo</th>
                        <th class="px-4 py-2 text-right">Productos</th>
                        <th class="px-4 py-2 text-left">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr class="border-t">
                            <td class="px-4 py-2 font-medium">{{ $category->name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $category->slug }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $category->external_category_id ?: '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ $category->products_count }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.categories.edit', $category) }}"
                                       class="text-blue-600 hover:text-blue-800 text-sm">Editar</a>

                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                          onsubmit="return confirm('¿Eliminar la categoría «{{ $category->name }}»?')">
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
                            <td colspan="5" class="px-4 py-6 text-gray-600">
                                Todavia no hay categorias.
                                <a href="{{ route('admin.categories.create') }}" class="text-blue-600 hover:underline">Crear la primera</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $categories->links() }}
        </div>
    </div>
@endsection
