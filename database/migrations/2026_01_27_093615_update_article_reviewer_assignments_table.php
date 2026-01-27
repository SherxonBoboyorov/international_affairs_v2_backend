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
            $table->dateTime('completed_at')->nullable()->after('comment');

            $table->decimal('originality_score', 3, 1)->nullable()->after('completed_at');
            $table->decimal('methodology_score', 3, 1)->nullable()->after('originality_score');
            $table->decimal('argumentation_score', 3, 1)->nullable()->after('methodology_score');
            $table->decimal('structure_score', 3, 1)->nullable()->after('argumentation_score');
            $table->decimal('significance_score', 3, 1)->nullable()->after('structure_score');
            $table->string('general_recommendation')->nullable()->after('significance_score');
            $table->text('review_comments')->nullable()->after('general_recommendation');
            $table->json('review_files')->nullable()->after('review_comments');
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
