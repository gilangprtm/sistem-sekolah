<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['m_inventory_tangible_asset_types', 'm_inventory_intangible_asset_types'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
                $table->unique('name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('m_inventory_intangible_asset_types');
        Schema::dropIfExists('m_inventory_tangible_asset_types');
    }
};
