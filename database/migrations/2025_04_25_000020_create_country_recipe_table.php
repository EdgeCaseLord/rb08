<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_recipe', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('recipe_id');
            $table->primary(['country_id', 'recipe_id']);
            $table->timestamps();
        });

        // Add foreign key constraints with explicit names
        Schema::table('country_recipe', function (Blueprint $table) {
            $table->foreign('country_id', 'fk_country_recipe_country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('recipe_id', 'fk_country_recipe_recipe_id')->references('id_recipe')->on('recipes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_recipe');
    }
};
