<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_allergens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained('analyses')->onDelete('cascade');
            $table->foreignId('allergen_id')->nullable()->constrained('allergens')->onDelete('set null');
            $table->string('antigen_id')->nullable();
            $table->decimal('calibrated_value')->nullable();
            $table->decimal('signal_noise')->nullable();
            $table->timestamps();
            $table->index(['analysis_id', 'allergen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_allergens');
    }
};
