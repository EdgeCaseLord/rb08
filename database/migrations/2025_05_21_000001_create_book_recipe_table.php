<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_recipe', function (Blueprint $table) {
            $table->unsignedBigInteger('book_id');
            $table->unsignedBigInteger('recipe_id');
            $table->unique(['book_id', 'recipe_id']);
            $table->timestamps();
        });

        // Add foreign key constraints with explicit names
        Schema::table('book_recipe', function (Blueprint $table) {
            $table->foreign('book_id', 'fk_book_recipe_book_id')->references('id')->on('books')->onDelete('cascade');
            $table->foreign('recipe_id', 'fk_book_recipe_recipe_id')->references('id_recipe')->on('recipes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_recipe');
    }
};
