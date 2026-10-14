<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_curriculum_uas_links', function (Blueprint $table): void {
            $table->timestampTz('opens_at')->nullable()->after('link');
        });
    }

    public function down(): void
    {
        Schema::table('tr_curriculum_uas_links', function (Blueprint $table): void {
            $table->dropColumn('opens_at');
        });
    }
};
