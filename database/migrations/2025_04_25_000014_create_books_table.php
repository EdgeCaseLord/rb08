<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable()->index();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('analysis_id')->nullable();
            $table->string('status')->default('Warten auf Versand')->index();
            $table->timestamps();
        });

        // Add foreign key constraints with explicit names
        Schema::table('books', function (Blueprint $table) {
            $table->foreign('patient_id', 'fk_books_patient_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('analysis_id', 'fk_books_analysis_id')->references('id')->on('analyses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasColumn('books', 'analysis_id')) {
                $table->dropForeign(['analysis_id']);
                $table->dropColumn('analysis_id');
            }
            if (Schema::hasColumn('books', 'status')) {
                $table->dropColumn('status');
            }
        });
        Schema::dropIfExists('books');
    }
};
