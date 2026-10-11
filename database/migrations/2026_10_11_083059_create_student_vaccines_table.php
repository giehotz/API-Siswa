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
        Schema::create('student_vaccines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('hepatitis_b')->default(false);
            $table->boolean('polio')->default(false);
            $table->boolean('bcg')->default(false);
            $table->boolean('campak')->default(false);
            $table->boolean('dpt')->default(false);
            $table->boolean('covid19')->default(false);
            $table->json('raw_vaccine_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_vaccines');
    }
};
