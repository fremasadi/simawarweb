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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relasi ke tabel users
            $table->date('date'); // Tanggal absensi
            $table->time('check_in')->nullable(); // Waktu masuk
            // $table->time('check_out')->nullable(); // Waktu keluar
            $table->enum('status', ['present', 'absent', 'late', 'leave'])->default('present'); // Status kehadiran
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
