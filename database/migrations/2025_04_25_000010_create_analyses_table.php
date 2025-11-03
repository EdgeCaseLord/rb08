<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->string('qr_code')->nullable();
            $table->string('sample_code')->unique()->nullable();
            $table->date('sample_date')->nullable();
            $table->string('patient_code')->nullable();
            $table->string('patient_name')->nullable();
            $table->date('patient_date_of_birth')->nullable();
            $table->date('assay_date')->nullable();
            $table->date('test_date')->nullable()->index();
            $table->string('test_by')->nullable();
            $table->date('approval_date')->nullable();
            $table->string('approval_by')->nullable();
            $table->text('additional_information')->nullable();
            $table->boolean('is_csv')->default(true);
            $table->unsignedBigInteger('doctor_id')->nullable()->index();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->unsignedBigInteger('import_id')->nullable()->index();
            $table->unsignedBigInteger('lab_id')->nullable()->index();
            $table->integer('antigen_id')->nullable();
            $table->string('antigen_name')->nullable();
            $table->string('code')->nullable();
            $table->float('calibrated_value')->nullable();
            $table->float('signal_noise')->nullable();
            $table->timestamps();
            $table->index('created_at');
            $table->index('updated_at');
            $table->index('sample_code');
        });

        // Add foreign key constraints with explicit names
        Schema::table('analyses', function (Blueprint $table) {
            $table->foreign('doctor_id', 'fk_analyses_doctor_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('patient_id', 'fk_analyses_patient_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('import_id', 'fk_analyses_import_id')->references('id')->on('imports')->onDelete('set null');
            $table->foreign('lab_id', 'fk_analyses_lab_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
