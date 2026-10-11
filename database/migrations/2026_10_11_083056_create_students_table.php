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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('emis_student_id');
            $table->string('local_nis', 30)->nullable();
            $table->string('nisn', 10)->nullable()->index();
            $table->text('nik')->nullable();
            $table->string('nik_hash', 64)->nullable()->index();
            $table->text('kk_num')->nullable();
            $table->string('family_head_name')->nullable();
            $table->string('full_name');
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion', 50)->nullable();
            $table->unsignedTinyInteger('child_of_num')->nullable();
            $table->unsignedTinyInteger('siblings_num')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('hobby')->nullable();
            $table->string('life_goal')->nullable();

            // Alamat & Domisili
            $table->text('full_address')->nullable();
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('sub_district')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->string('residence_status')->nullable();
            $table->string('distance_to_school')->nullable();
            $table->string('travel_time')->nullable();
            $table->string('transportation')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Metadata & Sync
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institution_id', 'emis_student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
