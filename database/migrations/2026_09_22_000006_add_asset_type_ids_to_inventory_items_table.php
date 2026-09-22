<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_inventory_items', function (Blueprint $table): void {
            $table->string('asset_kind')->default('tangible')->after('inventory_type_id');
            $table->foreignId('tangible_asset_type_id')->nullable()->after('asset_kind')->constrained('m_inventory_tangible_asset_types')->nullOnDelete();
            $table->foreignId('intangible_asset_type_id')->nullable()->after('tangible_asset_type_id')->constrained('m_inventory_intangible_asset_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tr_inventory_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('intangible_asset_type_id');
            $table->dropConstrainedForeignId('tangible_asset_type_id');
            $table->dropColumn('asset_kind');
        });
    }
};
