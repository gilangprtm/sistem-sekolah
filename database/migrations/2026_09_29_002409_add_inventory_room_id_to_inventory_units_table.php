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
        Schema::table('tr_inventory_units', function (Blueprint $table): void {
            $table->foreignId('inventory_room_id')
                ->nullable()
                ->after('inventory_item_id')
                ->constrained('m_inventory_rooms')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_inventory_units', function (Blueprint $table): void {
            $table->dropForeign(['inventory_room_id']);
            $table->dropColumn('inventory_room_id');
        });
    }
};
