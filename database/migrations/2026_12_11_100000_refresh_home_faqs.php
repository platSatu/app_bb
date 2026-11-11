<?php

use Database\Seeders\WebFaqSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FAQ beranda website: 6 pertanyaan seputar tempat kursus (WebFaqSeeder::HOME) dipasang paling atas, karena
 * website menampilkan 6 FAQ teratas. FAQ lain tidak diubah isinya dan tetap aktif sebagai bahan jawaban live chat.
 * Aman dijalankan ulang: pertanyaan yang sudah ada tidak dibuat dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        $questions = array_keys(WebFaqSeeder::HOME);
        $missing = array_values(array_diff($questions, DB::table('web_faqs')->whereIn('name', $questions)->pluck('name')->all()));

        // Geser FAQ lain ke bawah supaya FAQ beranda menempati urutan teratas.
        DB::table('web_faqs')->whereNotIn('name', $questions)->increment('sort_order', count($questions));

        foreach ($questions as $i => $question) {
            if (in_array($question, $missing, true)) {
                DB::table('web_faqs')->insert(['id' => (string) Str::uuid(), 'name' => $question, 'descriptions' => WebFaqSeeder::HOME[$question], 'status' => 'active', 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('web_faqs')->where('name', $question)->update(['sort_order' => $i + 1, 'status' => 'active', 'updated_at' => now()]);
            }
        }

    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis; FAQ bisa diatur lagi lewat Superadmin › Website › FAQ.
    }
};
