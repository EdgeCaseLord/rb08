<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergen_user', function (Blueprint $table) {
            $table->unsignedBigInteger('allergen_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('allergen_id', 'fk_allergen_user_allergen_id')->references('id')->on('allergens')->onDelete('cascade');
            $table->foreign('user_id', 'fk_allergen_user_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'allergen_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergen_user');
    }
};
