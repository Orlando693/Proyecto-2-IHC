<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('votacion_cerrada')->default(false);
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropUnique(['plan_id', 'user_id', 'tipo']);
            $table->unique(['proposal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropUnique(['proposal_id', 'user_id']);
            $table->unique(['plan_id', 'user_id', 'tipo']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('votacion_cerrada');
        });
    }
};
