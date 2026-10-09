<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_kantin_barang', function (Blueprint $table): void {
            $table->decimal('harga', 15, 2)->default(0)->after('satuan');
        });
    }

    public function down(): void
    {
        Schema::table('m_kantin_barang', function (Blueprint $table): void {
            $table->dropColumn('harga');
        });
    }
};
