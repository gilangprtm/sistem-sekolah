<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_inventory_items', function (Blueprint $table): void {
            $table->foreignId('inventory_type_id')->nullable()->after('inventory_category_id')->constrained('m_inventory_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tr_inventory_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_type_id');
        });
    }
};
