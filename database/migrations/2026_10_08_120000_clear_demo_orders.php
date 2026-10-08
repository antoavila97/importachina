<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Vacia los pedidos de demostracion: pagos y renglones van primero
        // por si las claves foraneas no estan en cascada.
        DB::table('payments')->delete();
        DB::table('order_items')->delete();
        DB::table('orders')->delete();
    }

    public function down(): void
    {
        // Datos de demostracion: no se recuperan.
    }
};
