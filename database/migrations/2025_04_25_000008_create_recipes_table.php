<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id('id_recipe');
            $table->bigInteger('id_external')->nullable()->unique();
            $table->string('title')->nullable()->index();
            $table->string('subtitle')->nullable();
            $table->integer('serving')->nullable();
            $table->string('language')->nullable();
            $table->json('images')->nullable();
            $table->json('description')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('diet')->nullable();
            $table->json('time')->nullable();
            $table->timestamp('create')->nullable();
            $table->timestamp('last_update')->nullable();
            $table->string('alttitle')->nullable();
            $table->string('yield_quantity_1')->nullable();
            $table->string('yield_quantity_2')->nullable();
            $table->string('yield_info')->nullable();
            $table->string('yield_info_short')->nullable();
            $table->string('price')->nullable();
            $table->boolean('suitable_for_pregnancy')->nullable();
            $table->json('category')->nullable();
            $table->json('media')->nullable();
            $table->json('ingredients')->nullable();
            $table->json('steps')->nullable();
            $table->json('allergens')->nullable();
            $table->json('diets')->nullable();
            $table->json('substances')->nullable();
            $table->string('course')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
