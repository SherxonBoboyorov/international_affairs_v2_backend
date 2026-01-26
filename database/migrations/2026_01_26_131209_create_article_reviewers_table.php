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

            if (!Schema::hasTable('article_considerations')) {
                Schema::create('article_considerations', function (Blueprint $table) {
                    $table->id();
                    $table->string('article_title');
                    $table->string('fio');
                    $table->string('email')->nullable();
                    $table->string('article_file')->nullable();
                    $table->text('message')->nullable();

                    $table->string('status')->default('not_assigned');
                    $table->timestamps();
                });
            } else {
                Schema::table('article_considerations', function (Blueprint $table) {
                    if (!Schema::hasColumn('article_considerations', 'status')) {
                        $table->string('status')->default('not_assigned')->after('message');
                    }
                });
            }

            Schema::create('article_reviewers', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('fio');
                $table->text('description')->nullable();

                $table->string('file_path')->nullable();
                $table->dateTime('deadline')->nullable();

                $table->string('status')->default('not_assigned');

                $table->unsignedBigInteger('created_by')->nullable();

                $table->unsignedBigInteger('original_article_id')->nullable();
                $table->string('original_fio')->nullable();
                $table->string('original_article_file')->nullable();
                $table->string('original_title')->nullable();

                $table->timestamps();

                $table->foreign('created_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();

                $table->foreign('original_article_id')
                    ->references('id')->on('article_considerations')
                    ->nullOnDelete();
            });
        }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_reviewers');
    }
};
