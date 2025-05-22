<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_recipe', function (Blueprint $table) {
            $table->bigInteger('country_id');
            $table->bigInteger('recipe_id');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('recipe_id')->references('id_recipe')->on('recipes')->onDelete('cascade');
            $table->primary(['country_id', 'recipe_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_recipe');
    }
};
