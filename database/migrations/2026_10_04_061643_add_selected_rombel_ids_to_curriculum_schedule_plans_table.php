<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_curriculum_schedule_plans', function (Blueprint $table): void {
            $table->json('selected_rombel_ids')->nullable()->after('payload_hash');
        });
    }

    public function down(): void
    {
        Schema::table('tr_curriculum_schedule_plans', function (Blueprint $table): void {
            $table->dropColumn('selected_rombel_ids');
        });
    }
};
