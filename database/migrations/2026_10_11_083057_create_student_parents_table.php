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
        Schema::create('student_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete()->unique();

            // Data Ayah
            $table->string('father_name')->nullable();
            $table->text('father_nik')->nullable();
            $table->string('father_life_status', 20)->nullable();
            $table->string('father_phone', 20)->nullable();
            $table->string('father_education')->nullable();
            $table->string('father_job')->nullable();
            $table->string('father_income')->nullable();

            // Data Ibu
            $table->string('mother_name')->nullable();
            $table->text('mother_nik')->nullable();
            $table->string('mother_life_status', 20)->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('mother_education')->nullable();
            $table->string('mother_job')->nullable();
            $table->string('mother_income')->nullable();

            // Data Wali
            $table->string('guardian_name')->nullable();
            $table->string('guardian_job')->nullable();
            $table->string('guardian_phone', 20)->nullable();
            $table->string('guardian_relationship')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_parents');
    }
};
