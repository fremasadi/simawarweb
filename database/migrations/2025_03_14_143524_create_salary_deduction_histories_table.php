<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('salary_deduction_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('salary_id')->constrained('salaries')->onDelete('cascade');
            $table->foreignId('attendance_id')->constrained('attendances')->onDelete('cascade');
            $table->enum('deduction_type', ['telat', 'tidak hadir']);
            $table->integer('late_minutes')->nullable();
            $table->decimal('deduction_amount', 12, 2);
            $table->decimal('deduction_per_minute', 12, 2)->nullable();
            $table->decimal('reduction_if_absent', 12, 2)->nullable();
            $table->date('deduction_date');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_deduction_histories');
    }
};
