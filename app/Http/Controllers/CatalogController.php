<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->where('active', true);

        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->q.'%');
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        $products = $query->paginate(12);
        $categories = Category::orderBy('name')->get();

        return view('catalog.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        if (! $product->active) {
            abort(404);
        }

        // HU-08: la galería usa product_images; image_url es la portada.
        $product->load('images');

        return view('catalog.show', compact('product'));
    }
}
