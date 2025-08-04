<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_disk');
            $table->string('file_name')->nullable();
            $table->string('exporter');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('successful_rows')->default(0);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->index('created_at');
        });

        // Add foreign key constraint with explicit name
        Schema::table('exports', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_exports_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
