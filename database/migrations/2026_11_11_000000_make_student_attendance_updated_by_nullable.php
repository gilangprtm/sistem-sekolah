<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_student_attendances', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('tr_student_attendances')->whereNull('updated_by')->exists()) {
            throw new RuntimeException('Cannot roll back nullable updated_by: public attendance records have no updater identity. Preserve these records and resolve them explicitly before rollback.');
        }

        Schema::table('tr_student_attendances', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable(false)->change();
        });
    }
};
