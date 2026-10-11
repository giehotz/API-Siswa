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
        Schema::create('student_learning_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('academic_year', 20);
            $table->unsignedTinyInteger('semester')->default(1);
            $table->unsignedTinyInteger('level')->nullable();
            $table->string('rombel_name', 50)->nullable();
            $table->unsignedSmallInteger('absent_number')->nullable();
            $table->string('homeroom_teacher')->nullable();
            $table->string('classroom')->nullable();
            $table->string('student_status', 30)->default('Aktif');
            $table->string('status_description')->nullable();
            $table->date('admission_date')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year', 'semester'], 'sla_student_year_semester_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_learning_activities');
    }
};
