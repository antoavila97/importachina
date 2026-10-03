<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $products = Product::query()
            ->with('category')
            ->withCount('images')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('active', false))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.productos.index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => $this->categoriesForSelect(),
        ]);
    }

    public function create(): View
    {
        return view('admin.productos.form', [
            'product' => new Product(['active' => true, 'margin_pct' => 30]),
            'categories' => $this->categoriesForSelect(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = new Product($request->productData());

        // HU-07: el precio de venta sale del costo y del margen, nunca del formulario.
        $product->syncSalePrice()->save();

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Producto \"{$product->title}\" creado");
    }

    public function edit(Product $product): View
    {
        return view('admin.productos.form', [
            'product' => $product,
            'categories' => $this->categoriesForSelect(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->fill($request->productData())->syncSalePrice()->save();

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Producto \"{$product->title}\" actualizado");
    }

    /**
     * Un producto con pedidos no se borra: se desactiva, para no romper el historial.
     */
    public function destroy(Product $product): RedirectResponse
    {
        if ($product->orderItems()->exists()) {
            $product->update(['active' => false]);

            return redirect()
                ->route('admin.products.index')
                ->with('warning', "El producto \"{$product->title}\" tiene pedidos: se desactivó en vez de eliminarse.");
        }

        $product->cartItems()->delete();
        $title = $product->title;
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Producto \"{$title}\" eliminado");
    }

    /**
     * @return Collection<int, Category>
     */
    private function categoriesForSelect()
    {
        return Category::orderBy('name')->get();
    }
}
