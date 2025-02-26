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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->dateTime('deadline');
            $table->string('phone');
            $table->foreignId('image_id')->nullable()->constrained('image_models')->nullOnDelete();
            $table->integer('quantity');
            $table->foreignId('sizemodel_id')->nullable()->constrained('size_models')->nullOnDelete(); // FK ke size_models
            $table->json('size'); // Kolom dengan tipe JSON
            $table->string('status')->default('ditugaskan'); // Default value untuk status
            $table->foreignId('ditugaskan_ke')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
