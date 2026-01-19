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
        Schema::create('submission_assignments', function (Blueprint $table) {
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
            $table->unsignedBigInteger('assigned_by')->index()->nullable();
            $table->foreign('assigned_by')
                ->references('id')
                ->on('users')
                ->onDelete('CASCADE');
            $table->timestamp('assigned_at');
            $table->timestamp('deadline')->nullable();
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'rejected'])->default('assigned');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_assignments');
    }
};
