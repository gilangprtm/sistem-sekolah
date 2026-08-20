<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang')->unique();
            $table->string('nama_jenis_barang');
            $table->string('merk_type')->nullable();
            $table->string('no_identitas')->nullable(); // No Sertifikat/No Pabrik/No Chasis/No Mesin
            $table->string('bahan')->nullable();
            $table->string('asal_perolehan')->nullable();
            $table->year('tahun_pembelian')->nullable();
            $table->string('ukuran_konstruksi')->nullable(); // P,S,D
            $table->string('satuan')->nullable();
            $table->decimal('harga', 15, 2)->default(0);
            $table->text('keterangan')->nullable(); // mutable
            $table->timestamps();

            // Index untuk search/filter
            $table->index('nama_jenis_barang');
            $table->index('tahun_pembelian');
            $table->index('asal_perolehan');
            $table->index('satuan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
