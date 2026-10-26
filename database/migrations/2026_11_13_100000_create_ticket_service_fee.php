<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya layanan tiket (persen dari harga tiket berbayar, diatur superadmin; default 1%).
 * Angka biaya dikunci di setiap pesanan: subtotal (harga tiket), fee, fee_percent, fee_payer.
 * total = yang dibayar pembeli (subtotal + fee kalau pembeli yang menanggung).
 * Yang masuk saldo penyelenggara = total - fee; fee menjadi pendapatan bizbos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('fee_percent', 5, 2)->default(1);
            $table->timestamps();
        });

        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal')->default(0)->after('phone');
            $table->unsignedBigInteger('fee')->default(0)->after('subtotal');
            $table->decimal('fee_percent', 5, 2)->default(0)->after('fee');
            $table->string('fee_payer', 10)->nullable()->after('fee_percent');
        });

        // Pesanan lama: belum ada biaya layanan, harga tiket = total.
        DB::table('ticket_orders')->update(['subtotal' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::table('ticket_orders', fn (Blueprint $table) => $table->dropColumn(['subtotal', 'fee', 'fee_percent', 'fee_payer']));
        Schema::dropIfExists('ticket_settings');
    }
};
