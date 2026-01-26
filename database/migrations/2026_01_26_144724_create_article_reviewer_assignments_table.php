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
        Schema::create('article_reviewer_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_reviewer_id');
            $table->unsignedBigInteger('reviewer_id');
            $table->dateTime('assigned_at');
            $table->dateTime('deadline')->nullable();
            $table->string('status')->default('assigned');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('article_reviewer_id')->references('id')->on('article_reviewers')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['article_reviewer_id', 'reviewer_id'], 'ara_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_reviewer_assignments');
    }
};
