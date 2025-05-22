<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('avatar')->nullable()->default('demo/default.png');
            $table->timestamp('email_verified_at')->nullable()->index();
            $table->string('password')->nullable();
            $table->string('remember_token')->nullable();
            $table->string('username')->nullable()->unique();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('verification_code')->nullable();
            $table->integer('verified')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('language')->nullable();
            $table->string('timezone')->nullable();
            $table->string('currency')->nullable();
            $table->string('stripe_id')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->string('role')->nullable()->index();
            $table->integer('lab_id')->nullable()->index();
            $table->integer('doctor_id')->nullable()->index();
            $table->string('patient_code')->nullable()->unique();
            $table->date('birthdate')->nullable()->index();
            $table->float('threshold')->nullable();
            $table->json('settings')->nullable()->default('null');
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
