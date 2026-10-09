<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_kantin_barang', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kantin_kategori_id')
                ->constrained('m_kantin_kategori')
                ->restrictOnDelete();
            $table->string('kode_barang', 120)->unique();
            $table->string('name', 150);
            $table->string('brand', 100)->nullable();
            $table->string('satuan', 50);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['kantin_kategori_id', 'status']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_kantin_barang');
    }
};
