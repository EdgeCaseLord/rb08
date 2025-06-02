<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->string('patient_title')->nullable()->after('patient_code');
            $table->string('patient_first_name')->nullable()->after('patient_title');
            $table->string('doctor_title')->nullable()->after('approval_date');
            $table->string('doctor_first_name')->nullable()->after('doctor_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn(['patient_title', 'patient_first_name', 'doctor_title', 'doctor_first_name']);
        });
    }
};
