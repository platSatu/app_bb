<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran quiz menempel di attempt-nya, bukan tabel pesanan sendiri: satu attempt = satu
 * pembayaran (owner memilih "bayar lagi tiap mengulang"), dan nomor referensi QZ... yang sudah
 * ada langsung dipakai sebagai nomor pesanan di Duitku.
 *
 * started_at sengaja TIDAK diubah jadi nullable -- kolom yang sudah berisi data tidak disentuh.
 * Sebagai gantinya ada opened_at: waktu soal pertama benar-benar ditampilkan. Batas waktu
 * mengerjakan dihitung dari situ, supaya peserta tidak kehilangan menit saat menunggu pembayaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->unsignedInteger('amount')->nullable()->after('email');
            $table->dateTime('paid_at')->nullable()->after('is_late');
            $table->string('payment_method', 30)->nullable()->after('paid_at');
            $table->string('payment_url')->nullable()->after('payment_method');
            $table->string('provider_reference', 100)->nullable()->after('payment_url');
            $table->string('payment_note')->nullable()->after('provider_reference');
            $table->dateTime('opened_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn(['amount', 'paid_at', 'payment_method', 'payment_url', 'provider_reference', 'payment_note', 'opened_at']);
        });
    }
};
