<?php

use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konsep biaya tiket disederhanakan menjadi 3 mode per event (settings.ticket.fee_payer):
 *   organizer = 1% dipotong dari uang yang masuk, buyer = 1% ditambahkan ke pembeli,
 *   free = semua tiket Rp0, total kuota maksimal jatah dari superadmin (ticket_settings.free_allowance).
 * "Bayar di depan" untuk tiket gratis dihapus: yang terlanjur membayar dikembalikan ke Saldo.
 * Event lama: punya tiket berbayar = organizer; semuanya gratis = free tanpa batas (supaya tidak terganggu).
 * ticket_category_changes: riwayat perubahan kategori (sebelum -> sesudah, oleh siapa, kapan).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->refundPrepaidFreeFees();
        Schema::dropIfExists('ticket_free_fees');

        if (Schema::hasColumn('ticket_settings', 'free_unit_price')) {
            Schema::table('ticket_settings', fn (Blueprint $table) => $table->dropColumn('free_unit_price'));
        }

        // free_allowance sekarang = batas total tiket event gratis (default 1.000, bisa diubah superadmin).
        Schema::table('ticket_settings', fn (Blueprint $table) => $table->unsignedInteger('free_allowance')->default(1000)->change());
        DB::table('ticket_settings')->where('free_allowance', 50)->update(['free_allowance' => 1000]);

        $this->assignModesToExistingEvents();

        Schema::create('ticket_category_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category_name', 100);
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_category_changes');
    }

    private function refundPrepaidFreeFees(): void
    {
        if (! Schema::hasTable('ticket_free_fees')) {
            return;
        }

        $wallets = app(WalletService::class);

        DB::table('ticket_free_fees')
            ->join('page_categories', 'page_categories.id', '=', 'ticket_free_fees.page_id')
            ->join('user_branches', 'user_branches.id', '=', 'page_categories.branch_id')
            ->where('ticket_free_fees.exempt', false)->where('ticket_free_fees.paid_units', '>', 0)->where('ticket_free_fees.unit_price', '>', 0)
            ->get(['ticket_free_fees.id', 'ticket_free_fees.paid_units', 'ticket_free_fees.unit_price', 'page_categories.name', 'user_branches.user_id'])
            ->each(function ($fee) use ($wallets) {
                // Idempotency key: migrasi dijalankan ulang pun tidak mengembalikan dua kali.
                $wallets->credit(User::findOrFail($fee->user_id), $fee->paid_units * $fee->unit_price, WalletTransaction::TYPE_TICKET_FREE_REFUND,
                    'ticket-free-fee-unwind:'.$fee->id, null, "Pengembalian biaya tiket gratis · {$fee->name} (biaya di depan dihapus)");
            });
    }

    private function assignModesToExistingEvents(): void
    {
        DB::table('page_categories')
            ->join('page_types', 'page_types.id', '=', 'page_categories.page_type_id')
            ->where('page_types.template', 'ticket')
            ->get(['page_categories.id', 'page_categories.settings'])
            ->each(function ($page) {
                $settings = json_decode((string) $page->settings, true) ?: [];

                if (isset($settings['ticket']['fee_payer'])) {
                    return;
                }

                $paid = DB::table('ticket_categories')->where('page_id', $page->id)->where('price', '>', 0)->exists();
                $settings['ticket']['fee_payer'] = $paid ? 'organizer' : 'free';
                $settings['ticket']['free_unlimited'] = ! $paid;

                DB::table('page_categories')->where('id', $page->id)->update(['settings' => json_encode($settings)]);
            });
    }
};
