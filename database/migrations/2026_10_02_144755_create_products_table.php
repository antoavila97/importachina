<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('margin_pct', 5, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->string('image_url')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
