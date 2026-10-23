<?php

namespace App\Services\Contact;

use App\Models\ContactGroup;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Tambah customer massal (Excel/CSV, anggota grup WhatsApp) ke Customers satu aplikasi di branch, opsional ke satu Group WhatsApp. */
class ContactBookService
{
    public const MAX_ROWS = 5000;

    public function __construct(private CustomerService $customers) {}

    /**
     * @param  array<int, array{name: ?string, phone: ?string, email: ?string}>  $rows
     * @return array{saved: int, skipped: int}
     */
    public function addMany(UserBranch $branch, array $rows, ?ContactGroup $group, string $source, string $app = 'whatsapp'): array
    {
        $saved = 0;
        $skipped = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::transaction(function () use ($branch, $chunk, $group, $source, $app, &$saved, &$skipped) {
                $locked = UserBranch::whereKey($branch->getKey())->lockForUpdate()->firstOrFail();
                $ids = [];

                foreach ($chunk as $row) {
                    if (blank($row['phone'] ?? null) && blank($row['email'] ?? null)) {
                        $skipped++;

                        continue;
                    }
                    $ids[] = $this->customers->resolve($locked, $app, $row['name'] ?? null, $row['phone'] ?? null, $row['email'] ?? null, $source)->getKey();
                    $saved++;
                }

                if ($group && $ids) {
                    $group->customers()->syncWithoutDetaching(array_fill_keys($ids, ['created_at' => now()]));
                }
            });
        }

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    /**
     * Baca Excel (.xlsx/.xls) atau CSV. Kolom dikenali dari judul di baris pertama:
     * nama/name, whatsapp/phone/hp/nomor, email.
     *
     * @return array{0: array<int, array{name: ?string, phone: ?string, email: ?string}>, 1: bool} [baris, kolom WA/email ditemukan]
     */
    public function read(UploadedFile $file): array
    {
        try {
            $sheet = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        } catch (Throwable) {
            return [[], false];
        }

        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), array_shift($sheet) ?? []);
        $find = fn (array $names) => collect($header)->search(fn ($h) => in_array($h, $names, true));
        $cols = [
            'name' => $find(['nama', 'name', 'nama lengkap']),
            'phone' => $find(['whatsapp', 'wa', 'phone', 'hp', 'handphone', 'no hp', 'nomor', 'telepon', 'no whatsapp']),
            'email' => $find(['email', 'e-mail']),
        ];

        $rows = collect($sheet)->take(self::MAX_ROWS)
            // Nomor yang dibaca Excel sebagai angka (mis. 8.12E+10) dikembalikan ke digit utuh.
            ->map(fn ($line) => collect($cols)->map(fn ($i) => $i === false ? null : (trim(is_float($line[$i] ?? null) ? number_format($line[$i], 0, '', '') : (string) ($line[$i] ?? '')) ?: null))->all())
            ->reject(fn ($row) => ! array_filter($row))
            ->values()->all();

        return [$rows, $cols['phone'] !== false || $cols['email'] !== false];
    }

    /** Contoh file Excel untuk diisi owner. Kolom WhatsApp diformat teks supaya angka 0 di depan tidak hilang. */
    public function template(string $filename): StreamedResponse
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([['nama', 'whatsapp', 'email'], ['Budi Santoso', '081234567890', 'budi@contoh.com']]);
        $sheet->getStyle('B:B')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A' => 24, 'B' => 18, 'C' => 30] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
