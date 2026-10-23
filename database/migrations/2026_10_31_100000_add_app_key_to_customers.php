<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Customers per aplikasi: satu tabel, kolom app_key (pages/schedules/invoices/whatsapp).
 * Orang yang sama di dua aplikasi = dua baris, diedit terpisah.
 * Data lama dipecah sesuai pemakaiannya; tabel yang menunjuk customer ikut diarahkan ke baris aplikasinya.
 */
return new class extends Migration
{
    /** Tabel yang menyimpan customer_id, dikelompokkan per aplikasi. */
    private const USAGE = [
        'pages' => ['page_submissions', 'ticket_orders', 'catalog_orders', 'quiz_attempts'],
        'schedules' => ['schedule_students'],
        'invoices' => ['invoices', 'invoice_subscriptions'],
        'whatsapp' => ['contact_group_customer'],
    ];

    /**
     * Aman dijalankan ulang (mis. setelah gagal di tengah): setiap langkah dicek dulu.
     * Index lama (satu nomor per branch) dilepas SEBELUM data dipecah, karena salinan per aplikasi memakai nomor yang sama.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'app_key')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('app_key', 30)->default('whatsapp')->after('branch_id');
            });
        }

        // Index baru dibuat dulu: di MySQL index lama ikut dipakai foreign key branch_id.
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasIndex('customers', 'customers_branch_id_app_key_phone_unique')) {
                $table->unique(['branch_id', 'app_key', 'phone']);
            }
            if (! Schema::hasIndex('customers', 'customers_branch_id_app_key_email_unique')) {
                $table->unique(['branch_id', 'app_key', 'email']);
            }
        });
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasIndex('customers', 'customers_branch_id_phone_unique')) {
                $table->dropUnique(['branch_id', 'phone']);
            }
            if (Schema::hasIndex('customers', 'customers_branch_id_email_unique')) {
                $table->dropUnique(['branch_id', 'email']);
            }
        });

        $this->splitExisting();
    }

    public function down(): void
    {
        // Baris hasil pecahan tidak bisa digabung otomatis; kolom & index dikembalikan kalau datanya memungkinkan.
        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['branch_id', 'phone']);
            $table->unique(['branch_id', 'email']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'app_key', 'phone']);
            $table->dropUnique(['branch_id', 'app_key', 'email']);
            $table->dropColumn('app_key');
        });
    }

    private function splitExisting(): void
    {
        DB::table('customers')->orderBy('id')->chunkById(500, function ($customers) {
            foreach ($customers as $customer) {
                $apps = collect(self::USAGE)
                    ->filter(fn ($tables) => collect($tables)->contains(fn ($t) => DB::table($t)->where('customer_id', $customer->id)->exists()))
                    ->keys();

                if ($apps->isEmpty()) {
                    $apps = collect([$this->appFromSource((string) $customer->source)]);
                }

                DB::transaction(function () use ($customer, $apps) {
                    // Baris asli dipakai aplikasi pertama; aplikasi lain dapat salinan.
                    DB::table('customers')->where('id', $customer->id)->update(['app_key' => $apps->first()]);

                    foreach ($apps->slice(1) as $app) {
                        $copy = (array) $customer;
                        $copy['id'] = (string) Str::orderedUuid();
                        $copy['app_key'] = $app;
                        DB::table('customers')->insert($copy);

                        foreach (self::USAGE[$app] as $table) {
                            DB::table($table)->where('customer_id', $customer->id)->update(['customer_id' => $copy['id']]);
                        }
                    }
                });
            }
        });
    }

    private function appFromSource(string $source): string
    {
        return match (true) {
            Str::startsWith($source, ['Tiket', 'Katalog', 'Quiz', 'Form']) => 'pages',
            Str::startsWith($source, 'Invoice') => 'invoices',
            $source === 'schedule' => 'schedules',
            default => 'whatsapp',
        };
    }
};
