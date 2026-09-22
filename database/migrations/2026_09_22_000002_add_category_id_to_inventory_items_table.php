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
        Schema::table('tr_inventory_items', function (Blueprint $table) {
            $table->foreignId('inventory_category_id')
                ->nullable()
                ->after('id')
                ->constrained('m_inventory_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_inventory_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_category_id']);
            $table->dropColumn('inventory_category_id');
        });
    }
};
