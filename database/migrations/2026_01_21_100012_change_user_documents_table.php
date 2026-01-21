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
        Schema::table('user_documents', function (Blueprint $table) {
            $table->dropColumn('science_field');
            $table->unsignedBigInteger('science_field_id')->nullable()->after('position');
            $table->foreign('science_field_id')
                ->references('id')
                ->on('scientific_activities')
                ->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_documents', function (Blueprint $table) {
            //
        });
    }
};
