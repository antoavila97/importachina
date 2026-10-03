<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount('products')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->categoryData());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Categoría creada correctamente');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->categoryData($category->id));

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Categoría \"{$category->name}\" actualizada");
    }

    /**
     * HU-06: no se puede eliminar una categoría que tenga productos asignados.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            $count = $category->productsCount();

            return redirect()
                ->route('admin.categories.index')
                ->with(
                    'error',
                    "No se puede eliminar \"{$category->name}\": tiene {$count} producto(s) asignado(s). Reasignalos primero."
                );
        }

        $name = $category->name;
        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Categoría \"{$name}\" eliminada");
    }
}
