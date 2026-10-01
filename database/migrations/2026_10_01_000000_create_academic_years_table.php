<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('year', 9)->unique();
            $table->string('status', 8)->default('inactive');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX m_academic_years_one_active ON m_academic_years (status) WHERE status = 'active'");
        }
        Schema::create('m_academic_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('m_academic_years')->cascadeOnDelete();
            $table->string('code', 7);
            $table->string('name', 50);
            $table->string('status', 8)->default('inactive');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'code']);
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS m_academic_years_one_active');
        }
        Schema::dropIfExists('m_academic_periods');
        Schema::dropIfExists('m_academic_years');
    }
};
