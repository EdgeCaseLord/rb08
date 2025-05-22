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
            $table->foreignId('doctor_id')->nullable()->constrained('users')->onDelete('set null')->index();
            $table->foreignId('patient_id')->nullable()->constrained('users')->onDelete('set null')->index();            $table->foreignId('import_id')->nullable()->constrained('imports')->onDelete('set null')->index();
            $table->foreignId('lab_id')->nullable()->constrained('users')->onDelete('set null')->index();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
