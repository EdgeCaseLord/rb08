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
            $table->unsignedBigInteger('analysis_id');
            $table->unsignedBigInteger('allergen_id')->nullable();
            $table->string('antigen_id')->nullable();
            $table->decimal('calibrated_value')->nullable();
            $table->decimal('signal_noise')->nullable();
            $table->timestamps();
            $table->index(['analysis_id', 'allergen_id']);
        });

        // Add foreign key constraints with explicit names
        Schema::table('analysis_allergens', function (Blueprint $table) {
            $table->foreign('analysis_id', 'fk_analysis_allergens_analysis_id')->references('id')->on('analyses')->onDelete('cascade');
            $table->foreign('allergen_id', 'fk_analysis_allergens_allergen_id')->references('id')->on('allergens')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_allergens');
    }
};
