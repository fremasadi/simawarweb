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
        Schema::create('salary_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama salary setting
            $table->decimal('salary', 15, 2); // Gaji pokok
            $table->enum('periode', ['daily', 'weekly', 'monthly']); // Periode penggajian
            $table->decimal('reduction_if_absent', 8, 2); // Potongan jika tidak hadir
            $table->decimal('permit_reduction', 8, 2); // Potongan jika izin
            $table->decimal('deduction_per_minute', 8, 2); // Potongan per menit
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_settings');
    }
};
