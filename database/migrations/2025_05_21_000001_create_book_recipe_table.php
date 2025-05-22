<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_recipe', function (Blueprint $table) {
            $table->bigInteger('book_id');
            $table->bigInteger('recipe_id');
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
            $table->foreign('recipe_id')->references('id_recipe')->on('recipes')->onDelete('cascade');
            $table->unique(['book_id', 'recipe_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_recipe');
    }
};
