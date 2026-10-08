<?php

use App\Http\Controllers\Admin\ApiSyncController as AdminApiSyncController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Vendedor\OrderController as SellerOrderController;
use App\Http\Controllers\Vendedor\PaymentController as SellerPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('catalog.index');
})->name('home');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/{product}', [CatalogController::class, 'show'])->name('catalog.show');

Route::middleware('auth')->group(function () {
    Route::get('/carrito/agregar/{product}', [CartController::class, 'add'])->name('cart.add');
    Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
    Route::post('/carrito/{cartItem}/update', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/carrito/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/checkout', [OrderController::class, 'store'])->name('checkout.store');
    Route::get('/mis-pedidos', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/mis-pedidos/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::middleware(['role:Administrador,Vendedor'])->prefix('vendedor')->name('vendedor.')->group(function () {
        Route::get('/pedidos', [SellerOrderController::class, 'index'])->name('orders.index');
        Route::get('/pedidos/{order}', [SellerOrderController::class, 'show'])->name('orders.show');
        Route::put('/pedidos/{order}/estado', [SellerOrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('/pedidos/{order}/pago', [SellerPaymentController::class, 'store'])->name('payments.store');
        Route::put('/pedidos/{order}/pagos/{payment}', [SellerPaymentController::class, 'update'])->name('payments.update');
        Route::put('/pedidos/{order}/pagos/{payment}/anular', [SellerPaymentController::class, 'void'])->name('payments.void');
    });

    Route::middleware(['role:Administrador'])->prefix('admin')->name('admin.')->group(function () {
        // Los parametros se nombran en ingles para que el binding implicito de
        // Laravel resuelva contra los type hints ($user, $category, $product)
        // y para que los FormRequest reciban route('product') / route('user') / route('category').
        Route::resource('productos', AdminProductController::class)
            ->names('products')
            ->parameters(['productos' => 'product'])
            ->except('show');
        Route::resource('categorias', CategoryController::class)
            ->names('categories')
            ->parameters(['categorias' => 'category'])
            ->except('show');
        Route::resource('usuarios', UserController::class)
            ->names('users')
            ->parameters(['usuarios' => 'user'])
            ->except('show');
        Route::get('api-sync', [AdminApiSyncController::class, 'index'])->name('api-sync.index');
        Route::post('api-sync', [AdminApiSyncController::class, 'sync'])->name('api-sync.sync');
        Route::get('reportes', [AdminReportController::class, 'index'])->name('reports.index');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
