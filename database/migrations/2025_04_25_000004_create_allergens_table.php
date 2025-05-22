<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergens', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->index();
            $table->string('code')->unique(); // Changed to non-nullable with unique index
            $table->text('description')->nullable();
            $table->string('name_latin')->nullable();
            $table->text('description_de')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergens');
    }
};
