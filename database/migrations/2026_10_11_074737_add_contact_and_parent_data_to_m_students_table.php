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
        Schema::table('m_students', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->after('address');
            $table->string('mother_name')->nullable()->after('phone');
            $table->string('father_name')->nullable()->after('mother_name');
            $table->string('mother_occupation')->nullable()->after('father_name');
            $table->string('father_occupation')->nullable()->after('mother_occupation');
            $table->string('parent_phone', 30)->nullable()->after('father_occupation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('m_students', function (Blueprint $table): void {
            $table->dropColumn([
                'phone',
                'mother_name',
                'father_name',
                'mother_occupation',
                'father_occupation',
                'parent_phone',
            ]);
        });
    }
};
