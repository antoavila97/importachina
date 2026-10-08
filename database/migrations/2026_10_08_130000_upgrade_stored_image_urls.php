<?php

use App\Support\ImageUrl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Las URLs de galeria de AliExpress superan los 255 caracteres.
        Schema::table('product_images', function (Blueprint $table) {
            $table->text('url')->change();
        });

        // Quita los sufijos que achican la imagen (_220x220q75.jpg_.avif).
        $this->upgrade('products', 'image_url');
        $this->upgrade('product_images', 'url');
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('url', 2000)->nullable(false)->change();
        });
    }

    private function upgrade(string $table, string $column): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    $upgraded = ImageUrl::upgrade($row->{$column});

                    if ($upgraded !== null && $upgraded !== $row->{$column}) {
                        DB::table($table)->where('id', $row->id)->update([$column => $upgraded]);
                    }
                }
            });
    }
};
