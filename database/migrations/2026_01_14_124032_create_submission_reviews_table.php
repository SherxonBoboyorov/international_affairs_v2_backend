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
        Schema::create('submission_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id')->index()->nullable();
            $table->foreign('submission_id')
                ->references('id')
                ->on('submissions')
                ->onDelete('CASCADE');
            $table->unsignedBigInteger('reviewer_id')->index()->nullable();
            $table->foreign('reviewer_id')
                ->references('id')
                ->on('users')
                ->onDelete('CASCADE');
            $table->tinyInteger('originality_score')->unsigned();
            $table->tinyInteger('methodology_score')->unsigned();
            $table->tinyInteger('argumentation_score')->unsigned();
            $table->tinyInteger('structure_score')->unsigned();
            $table->tinyInteger('significance_score')->unsigned();
            $table->text('general_recommendation')->nullable();
            $table->text('comments')->nullable();
            $table->json('files')->nullable();
            $table->enum('status', ['pending', 'submitted', 'approved', 'rejected'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_reviews');
    }
};
