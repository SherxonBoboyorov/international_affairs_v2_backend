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
        Schema::table('article_reviewer_assignments', function (Blueprint $table) {
            $table->dropColumn('originality_score');
            $table->dropColumn('methodology_score');
            $table->dropColumn('argumentation_score');
            $table->dropColumn('structure_score');
            $table->dropColumn('significance_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_reviewer_assignments', function (Blueprint $table) {
            //
        });
    }
};
