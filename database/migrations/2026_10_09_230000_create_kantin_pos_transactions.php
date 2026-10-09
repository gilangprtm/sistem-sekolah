<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_kantin_penjualan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('m_students')->restrictOnDelete();
            $table->decimal('total', 15, 2);
            $table->timestamps();
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('tr_kantin_penjualan_detail', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('tr_kantin_penjualan')->cascadeOnDelete();
            $table->foreignId('kantin_barang_id')->constrained('m_kantin_barang')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('harga', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_kantin_penjualan_detail');
        Schema::dropIfExists('tr_kantin_penjualan');
    }
};
