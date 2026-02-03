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
            $table->json('draft_criteria_scores')->nullable()->after('criteria_scores');
            $table->string('draft_general_recommendation')->nullable()->after('draft_criteria_scores');
            $table->text('draft_review_comments')->nullable()->after('draft_general_recommendation');
            $table->datetime('draft_expires_at')->nullable()->after('draft_review_comments');
            $table->datetime('draft_last_saved_at')->nullable()->after('draft_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_reviewer_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'draft_criteria_scores',
                'draft_general_recommendation',
                'draft_review_comments',
                'draft_expires_at',
                'draft_last_saved_at'
            ]);
        });
    }
};
