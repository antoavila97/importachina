<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $isAdmin = $user->hasRole('Administrador');
        $isSeller = $isAdmin || $user->hasRole('Vendedor');

        $data = [
            'user' => $user,
            'isAdmin' => $isAdmin,
            'isSeller' => $isSeller,
        ];

        if ($isSeller) {
            $monthStart = now()->startOfMonth();

            $sold = Order::query()
                ->whereIn('status', Order::SOLD_STATUSES)
                ->where('created_at', '>=', $monthStart);

            $pending = Order::query()->where('status', Order::STATUS_PENDIENTE);

            $data['monthRevenue'] = (float) (clone $sold)->sum('total');
            $data['monthOrders'] = (clone $sold)->count();
            $data['pendingOrders'] = (clone $pending)->count();
            $data['pendingAmount'] = (float) (clone $pending)->sum('total');
            $data['latestOrders'] = Order::with('user:id,name')->latest('id')->limit(6)->get();
        }

        if ($isAdmin) {
            $data['activeProducts'] = Product::where('active', true)->count();
            $data['lowStock'] = Product::where('active', true)
                ->where('stock', '<=', 5)
                ->orderBy('stock')
                ->limit(5)
                ->get();
        }

        if (! $isSeller) {
            $mine = Order::query()->where('user_id', $user->id);

            $data['myOrdersCount'] = (clone $mine)->count();
            $data['myPending'] = (clone $mine)->where('status', Order::STATUS_PENDIENTE)->count();
            $data['myOnTheWay'] = (clone $mine)->where('status', Order::STATUS_ENVIADO)->count();
            $data['myDelivered'] = (clone $mine)->where('status', Order::STATUS_ENTREGADO)->count();
            $data['latestMyOrders'] = (clone $mine)->latest('id')->limit(5)->get();
        }

        return view('dashboard', $data);
    }
}
