<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Google Form: registrasi ulang (QR + aplikasi petugas ber-PIN) dan pembayaran (lewat Invoice), plus laporan per pendaftar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_form_integrations', function (Blueprint $table) {
            $table->string('name_field', 191)->nullable()->after('phone_field');
            $table->boolean('registration_enabled')->default(false)->after('message');
            $table->string('checkin_token', 48)->nullable()->unique()->after('registration_enabled');
            $table->string('checkin_pin_hash')->nullable()->after('checkin_token');
            $table->boolean('payment_enabled')->default(false)->after('checkin_pin_hash');
            $table->unsignedBigInteger('amount')->nullable()->after('payment_enabled');
        });

        Schema::table('wa_form_submissions', function (Blueprint $table) {
            $table->string('name', 150)->nullable()->after('payload');
            // Kode di QR registrasi ulang, juga bisa diketik manual oleh petugas.
            $table->string('code', 12)->nullable()->unique()->after('phone');
            $table->foreignUuid('invoice_id')->nullable()->after('code')->constrained('invoices')->nullOnDelete();
            $table->timestamp('qr_sent_at')->nullable()->after('error');
            $table->timestamp('attended_at')->nullable()->after('qr_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('wa_form_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropUnique(['code']);
            $table->dropColumn(['name', 'code', 'qr_sent_at', 'attended_at']);
        });
        Schema::table('wa_form_integrations', function (Blueprint $table) {
            $table->dropUnique(['checkin_token']);
            $table->dropColumn(['name_field', 'registration_enabled', 'checkin_token', 'checkin_pin_hash', 'payment_enabled', 'amount']);
        });
    }
};
