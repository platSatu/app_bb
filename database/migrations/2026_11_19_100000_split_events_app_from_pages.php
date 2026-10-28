<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Event (page bertemplate tiket) dipisah dari Pages menjadi layanan sendiri: menu, paket, dan customers sendiri.
 * Tidak ada data page/tiket yang dipindah; tabelnya tetap sama. Yang disesuaikan:
 *   1. Langganan: setiap branch yang punya langganan Pages otomatis dapat Event dengan masa aktif yang sama,
 *      jadi event yang sudah berjalan tidak tiba-tiba tertutup. Batas kategori tiket pindah ke Event.
 *   2. Paket: paket yang membuka Pages juga membuka Event. Batas kategori tiket pindah ke Event.
 *   3. Customers: pembeli tiket pindah ke customers Event. Kalau orang yang sama juga punya pesanan katalog/kuis,
 *      barisnya dipecah (sama seperti pemisahan customers per aplikasi sebelumnya).
 *   4. Role staff platform: yang boleh membuka Applications › Pages juga boleh membuka Applications › Event.
 *   5. Broadcast WhatsApp yang menyasar "Semua customer Pages" ikut menyasar customer Event (pembeli tiket tetap kebagian).
 * Aman dijalankan ulang: setiap langkah dicek dulu.
 */
return new class extends Migration
{
    private const EVENT_TEMPLATES = ['ticket'];

    public function up(): void
    {
        $ticketTypes = DB::table('page_types')->whereIn('template', self::EVENT_TEMPLATES)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->subscriptions($ticketTypes);
        $this->packages($ticketTypes);
        $this->customers($ticketTypes);
        $this->permissions();
        $this->broadcasts();
    }

    public function down(): void
    {
        // Langganan & paket Event tidak dihapus otomatis (bisa saja sudah diperpanjang/dibeli terpisah).
        // Customers Event dikembalikan ke Pages kalau tidak bentrok nomor/email.
        DB::table('customers')->where('app_key', 'events')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $clash = DB::table('customers')->where('branch_id', $row->branch_id)->where('app_key', 'pages')
                    ->where(fn ($q) => $q->when($row->phone, fn ($w) => $w->where('phone', $row->phone))
                        ->when($row->email, fn ($w) => $w->orWhere('email', $row->email)))
                    ->when(! $row->phone && ! $row->email, fn ($q) => $q->whereRaw('1 = 0'))
                    ->exists();

                if (! $clash) {
                    DB::table('customers')->where('id', $row->id)->update(['app_key' => 'pages']);
                }
            }
        });
    }

    /** @return array{0: array, 1: array} [batas untuk Event, sisa batas untuk Pages] */
    private function splitLimits(?array $limits, array $ticketTypes): array
    {
        $limits ??= [];

        return [
            array_intersect_key($limits, array_flip($ticketTypes)),
            array_diff_key($limits, array_flip($ticketTypes)),
        ];
    }

    private function decode(mixed $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $value = is_array($json) ? $json : json_decode((string) $json, true);

        return is_array($value) ? $value : null;
    }

    private function subscriptions(array $ticketTypes): void
    {
        DB::table('branch_subscriptions')->where('app_key', 'pages')->orderBy('id')->chunkById(500, function ($rows) use ($ticketTypes) {
            foreach ($rows as $row) {
                DB::transaction(function () use ($row, $ticketTypes) {
                    $limits = $this->decode($row->limits);
                    [$eventLimits, $pageLimits] = $this->splitLimits($limits, $ticketTypes);

                    $exists = DB::table('branch_subscriptions')->where('branch_id', $row->branch_id)->where('app_key', 'events')->exists();

                    if (! $exists) {
                        DB::table('branch_subscriptions')->insert([
                            'id' => (string) Str::orderedUuid(),
                            'branch_id' => $row->branch_id,
                            'app_key' => 'events',
                            'starts_at' => $row->starts_at,
                            'ends_at' => $row->ends_at,
                            // null = langganan lama tanpa batas tercatat, tetap tanpa batas.
                            'limits' => $limits === null ? null : json_encode((object) $eventLimits),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    if ($limits !== null && $eventLimits !== []) {
                        DB::table('branch_subscriptions')->where('id', $row->id)->update(['limits' => json_encode((object) $pageLimits)]);
                    }
                });
            }
        });
    }

    private function packages(array $ticketTypes): void
    {
        DB::table('packages')->orderBy('id')->chunkById(200, function ($rows) use ($ticketTypes) {
            foreach ($rows as $row) {
                $apps = $this->decode($row->apps) ?? [];
                $limits = $this->decode($row->limits);

                if (! in_array('pages', $apps, true) && ! isset($limits['pages'])) {
                    continue;
                }

                // Event tampil paling depan, sama seperti urutan menu.
                if (in_array('pages', $apps, true) && ! in_array('events', $apps, true)) {
                    $apps = ['events', ...$apps];
                }

                if ($limits !== null) {
                    [$eventLimits, $pageLimits] = $this->splitLimits($limits['pages'] ?? null, $ticketTypes);
                    $limits['events'] = (object) [...((array) ($limits['events'] ?? [])), ...$eventLimits];
                    $limits['pages'] = (object) $pageLimits;
                }

                DB::table('packages')->where('id', $row->id)->update([
                    'apps' => json_encode(array_values($apps)),
                    'limits' => $limits === null ? null : json_encode($limits),
                ]);
            }
        });
    }

    private function customers(array $ticketTypes): void
    {
        if ($ticketTypes === []) {
            return;
        }

        $ticketPages = DB::table('page_categories')->whereIn('page_type_id', $ticketTypes)->select('id');

        DB::table('customers')->where('app_key', 'pages')->orderBy('id')->chunkById(500, function ($customers) use ($ticketPages) {
            foreach ($customers as $customer) {
                $ticketUse = DB::table('ticket_orders')->where('customer_id', $customer->id)->exists()
                    || DB::table('page_submissions')->where('customer_id', $customer->id)->whereIn('page_category_id', clone $ticketPages)->exists();

                $otherUse = DB::table('catalog_orders')->where('customer_id', $customer->id)->exists()
                    || DB::table('quiz_attempts')->where('customer_id', $customer->id)->exists()
                    || DB::table('page_submissions')->where('customer_id', $customer->id)->whereNotIn('page_category_id', clone $ticketPages)->exists();

                // Tanpa transaksi tiket: hanya customer manual/impor dari tiket (sumber "Tiket: ...") yang belum dipakai Pages.
                if (! $ticketUse && ($otherUse || ! Str::startsWith((string) $customer->source, 'Tiket'))) {
                    continue;
                }

                DB::transaction(function () use ($customer, $otherUse, $ticketPages) {
                    // Sudah ada customer Event dengan WA/email yang sama di branch ini (mis. migrasi diulang): pakai yang itu.
                    $existing = $this->eventCustomer($customer);

                    if (! $otherUse && ! $existing) {
                        DB::table('customers')->where('id', $customer->id)->update(['app_key' => 'events']);

                        return;
                    }

                    // Dipakai Event dan Pages: baris asli tetap di Pages, Event dapat salinan.
                    $target = $existing;
                    if (! $target) {
                        $copy = (array) $customer;
                        $copy['id'] = $target = (string) Str::orderedUuid();
                        $copy['app_key'] = 'events';
                        DB::table('customers')->insert($copy);
                    }

                    DB::table('ticket_orders')->where('customer_id', $customer->id)->update(['customer_id' => $target]);
                    DB::table('page_submissions')->where('customer_id', $customer->id)->whereIn('page_category_id', clone $ticketPages)->update(['customer_id' => $target]);
                });
            }
        });
    }

    private function eventCustomer(object $customer): ?string
    {
        if (! $customer->phone && ! $customer->email) {
            return null;
        }

        return DB::table('customers')->where('branch_id', $customer->branch_id)->where('app_key', 'events')
            ->where(fn ($q) => $q->when($customer->phone, fn ($w) => $w->orWhere('phone', $customer->phone))
                ->when($customer->email, fn ($w) => $w->orWhere('email', $customer->email)))
            ->value('id');
    }

    private function broadcasts(): void
    {
        DB::table('wa_broadcasts')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $target = $this->decode($row->target) ?? [];
                $apps = (array) ($target['apps'] ?? []);

                if (in_array('pages', $apps, true) && ! in_array('events', $apps, true)) {
                    $target['apps'] = ['events', ...$apps];
                    DB::table('wa_broadcasts')->where('id', $row->id)->update(['target' => json_encode($target)]);
                }
            }
        });
    }

    private function permissions(): void
    {
        DB::table('role_permissions')->where('permission_key', 'superadmin.pages')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('role_permissions')->insertOrIgnore([
                    'id' => (string) Str::orderedUuid(),
                    'role_id' => $row->role_id,
                    'permission_key' => 'superadmin.events',
                    'ability' => $row->ability,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
};
