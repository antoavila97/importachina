<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncProductsRequest;
use App\Models\ApiSyncLog;
use App\Services\AliExpressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ApiSyncController extends Controller
{
    public function index(AliExpressService $api): View
    {
        $logs = ApiSyncLog::with('user')->latest()->paginate(20);

        return view('admin.api-sync.index', [
            'logs' => $logs,
            'mode' => $api->mode(),
        ]);
    }

    /**
     * HU-05: el administrador dispara la sincronizacion desde el panel.
     * El comando corre en este proceso y devuelve el codigo de salida real.
     */
    public function sync(SyncProductsRequest $request): RedirectResponse
    {
        $exitCode = Artisan::call('app:sync-aliexpress-products', [
            '--keyword' => $request->keyword(),
            '--limit' => $request->limit(),
        ]);

        $log = ApiSyncLog::latest('id')->first();

        return redirect()
            ->route('admin.api-sync.index')
            ->with(
                $exitCode === 0 ? 'success' : 'error',
                $log?->message ?? ($exitCode === 0 ? 'Sincronización completada' : 'La sincronización falló')
            );
    }
}
