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
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relasi ke users
            $table->foreignId('salary_setting_id')->constrained()->onDelete('cascade'); // Relasi ke salary_settings
            $table->decimal('total_salary', 15, 2); // Gaji akhir setelah potongan
            $table->decimal('total_deduction', 15, 2)->default(0); // Total potongan
            $table->enum('status', ['paid', 'pending', 'canceled']); // Status pembayaran
            $table->text('note')->nullable(); // Catatan tambahan
            $table->date('pay_date'); // Tanggal penggajian
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
