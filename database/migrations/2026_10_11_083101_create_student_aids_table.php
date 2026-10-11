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
        Schema::create('student_aids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('kip_number', 30)->nullable();
            $table->boolean('is_kip_sync')->default(false);
            $table->boolean('pkh_status')->default(false);
            $table->boolean('pip_status')->default(false);
            $table->boolean('dtks')->default(false);
            $table->string('fund_source')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_aids');
    }
};
