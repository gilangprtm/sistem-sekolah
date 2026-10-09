<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_kantin_saldo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('m_students')->restrictOnDelete();
            $table->decimal('saldo', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('tr_kantin_saldo_mutasi', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('m_students')->restrictOnDelete();
            $table->foreignId('kantin_saldo_id')->constrained('m_kantin_saldo')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('top_up');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_kantin_saldo_mutasi');
        Schema::dropIfExists('m_kantin_saldo');
    }
};
