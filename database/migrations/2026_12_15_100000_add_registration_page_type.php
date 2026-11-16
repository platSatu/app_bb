<?php

use App\Models\PageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kategori page "Registrasi": formulir pendaftaran, tanpa tiket dan tanpa pembayaran.
 *
 * Tipe ini pernah ada lalu digabung ke Tiket (2026_10_21_100000_merge_registration_into_ticket),
 * supaya jawaban formulir bisa ditautkan ke pesanan. Dihidupkan lagi sebagai kategori sendiri
 * karena untuk pendaftaran biasa, "dapat tiket setelah mendaftar" membingungkan pendaftarnya.
 *
 * Yang dihidupkan hanya kategorinya. Mesin formulir & penyimpanan jawabannya tetap yang sudah
 * ada (page_form_fields + page_submissions), jadi tidak ada tabel baru dan Tiket tidak tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        PageType::firstOrCreate(
            ['template' => PageType::TEMPLATE_REGISTRATION],
            [
                'name' => 'Registrasi',
                'description' => 'Formulir pendaftaran: kumpulkan nama, email, dan nomor WhatsApp pendaftar. Tanpa tiket dan tanpa pembayaran.',
                'status' => PageType::STATUS_ACTIVE,
            ],
        );
    }

    public function down(): void
    {
        // Hanya kalau belum dipakai page mana pun; jangan sampai page milik pelanggan jadi yatim.
        DB::table('page_types')->where('template', PageType::TEMPLATE_REGISTRATION)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('page_categories')->whereColumn('page_categories.page_type_id', 'page_types.id'))
            ->delete();
    }
};
