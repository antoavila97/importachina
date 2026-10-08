<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Las URLs de AliExpress con parametros de rastreo superan los 255 caracteres.
            $table->text('image_url')->nullable()->change();
            $table->text('source_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
            $table->string('source_url')->nullable()->change();
        });
    }
};
