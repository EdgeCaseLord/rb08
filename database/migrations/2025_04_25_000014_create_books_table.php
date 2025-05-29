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
            $table->foreignId('patient_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('analysis_id')->nullable()->constrained('analyses')->nullOnDelete();
            $table->string('status')->default('Warten auf Versand')->index();
            $table->timestamps();
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
