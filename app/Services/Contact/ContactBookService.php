<?php

namespace App\Services\Contact;

use App\Models\ContactGroup;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Tambah kontak massal (CSV / anggota grup WhatsApp) ke Buku Kontak branch, opsional ke satu Group. */
class ContactBookService
{
    public const MAX_ROWS = 5000;

    public function __construct(private CustomerService $customers) {}

    /**
     * @param  array<int, array{name: ?string, phone: ?string, email: ?string}>  $rows
     * @return array{saved: int, skipped: int}
     */
    public function addMany(UserBranch $branch, array $rows, ?ContactGroup $group, string $source): array
    {
        $saved = 0;
        $skipped = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::transaction(function () use ($branch, $chunk, $group, $source, &$saved, &$skipped) {
                $locked = UserBranch::whereKey($branch->getKey())->lockForUpdate()->firstOrFail();
                $ids = [];

                foreach ($chunk as $row) {
                    if (blank($row['phone'] ?? null) && blank($row['email'] ?? null)) {
                        $skipped++;

                        continue;
                    }
                    $ids[] = $this->customers->resolve($locked, $row['name'] ?? null, $row['phone'] ?? null, $row['email'] ?? null, $source)->getKey();
                    $saved++;
                }

                if ($group && $ids) {
                    $group->customers()->syncWithoutDetaching(array_fill_keys($ids, ['created_at' => now()]));
                }
            });
        }

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    /** Baca CSV: kolom dikenali dari header (nama/name, whatsapp/phone/hp/nomor, email). */
    public function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $first = fgets($handle);
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        rewind($handle);

        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle, 0, $delimiter) ?: []);
        $find = fn (array $names) => collect($header)->search(fn ($h) => in_array($h, $names, true));
        $cols = [
            'name' => $find(['nama', 'name', 'nama lengkap']),
            'phone' => $find(['whatsapp', 'wa', 'phone', 'hp', 'no hp', 'nomor', 'telepon', 'no whatsapp']),
            'email' => $find(['email', 'e-mail']),
        ];

        $rows = [];
        while (($line = fgetcsv($handle, 0, $delimiter)) !== false && count($rows) < self::MAX_ROWS) {
            $rows[] = collect($cols)->map(fn ($i) => $i === false ? null : (trim((string) ($line[$i] ?? '')) ?: null))->all();
        }
        fclose($handle);

        return [$rows, $cols['phone'] !== false || $cols['email'] !== false];
    }
}
