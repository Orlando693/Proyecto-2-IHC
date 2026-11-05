<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->foreignId('propuesta_ganadora_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->text('motivo_decision')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropForeign(['propuesta_ganadora_id']);
            $table->dropColumn(['propuesta_ganadora_id', 'motivo_decision']);
        });
    }
};
