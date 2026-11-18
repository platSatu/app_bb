<?php

namespace App\Services\Schedule;

use App\Models\ScheduleStudent;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Menu Murid › Import Excel (18 November 2026): migrasi banyak murid sekaligus dari file .xlsx / .xls / .csv.
 *  - Kolom dibaca dari judul di baris pertama (urutan bebas): Nama (wajib), WhatsApp, Email, Orang Tua / Wali,
 *    Keterangan, Status. Template-nya diunduh dari tombol yang sama.
 *  - Aturan sama dengan form Tambah Murid: nomor dirapikan ke 62..., email huruf kecil, WhatsApp & email unik per branch.
 *  - Murid yang WhatsApp/emailnya sudah ada di branch (atau dobel di file) dilewati, tidak ditimpa.
 *  - Murid yang punya WA/email ikut tercatat di Buku Kontak branch, sama seperti StudentController::saved().
 * Satu transaksi dengan baris branch dikunci, jadi dua admin yang mengimpor bersamaan tidak membuat data kembar.
 */
class StudentImportService
{
    public const MAX_ROWS = 2000;

    public const HEADINGS = ['Nama', 'WhatsApp', 'Email', 'Orang Tua / Wali', 'Keterangan', 'Status'];

    /** Judul kolom yang dikenali (huruf kecil, tanpa spasi ganda) -> field. */
    private const ALIASES = [
        'name' => ['nama', 'nama murid', 'nama siswa', 'nama lengkap', 'name', 'student'],
        'phone' => ['whatsapp', 'wa', 'no whatsapp', 'no. whatsapp', 'nomor whatsapp', 'no wa', 'no. wa', 'hp', 'no hp', 'no. hp', 'phone', 'telepon', 'handphone'],
        'email' => ['email', 'e-mail', 'surel'],
        'guardian_name' => ['orang tua / wali', 'orang tua/wali', 'orang tua', 'wali', 'nama orang tua', 'nama wali', 'guardian'],
        'description' => ['keterangan', 'catatan', 'deskripsi', 'description', 'notes'],
        'status' => ['status'],
    ];

    public function __construct(private readonly CustomerService $customers)
    {
    }

    /** Baris contoh di template. */
    public static function exampleRows(): array
    {
        return [
            ['Budi Santoso', '081234567890', 'budi@contoh.com', 'Ibu Sari', 'Kelas Piano', 'Aktif'],
            ['Citra Lestari', '', 'citra@contoh.com', '', '', 'Aktif'],
        ];
    }

    /**
     * @return array{created: int, skipped: list<string>}
     *
     * @throws RuntimeException file tidak bisa dibaca / kolom Nama tidak ada / terlalu banyak baris
     */
    public function import(UserBranch $branch, UploadedFile $file): array
    {
        $rows = $this->read($file);
        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($branch, $rows, &$created, &$skipped) {
            UserBranch::whereKey($branch->getKey())->lockForUpdate()->first();

            $existing = ScheduleStudent::where('branch_id', $branch->getKey())->get(['phone', 'email']);
            $phones = $existing->pluck('phone')->filter()->flip()->all();
            $emails = $existing->pluck('email')->filter()->map(fn ($e) => strtolower($e))->flip()->all();

            foreach ($rows as $line => $row) {
                $data = [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'phone' => User::normalizePhone($this->text($row['phone'] ?? null)) ?: null,
                    'email' => filled($row['email'] ?? null) ? strtolower(trim((string) $row['email'])) : null,
                    'guardian_name' => filled($row['guardian_name'] ?? null) ? trim((string) $row['guardian_name']) : null,
                    'description' => filled($row['description'] ?? null) ? trim((string) $row['description']) : null,
                    'status' => $this->status($row['status'] ?? null),
                ];

                if ($data['name'] === '' && ! $data['phone'] && ! $data['email']) {
                    continue; // baris kosong
                }

                $validator = Validator::make($data, [
                    'name' => ['required', 'string', 'max:150'],
                    'phone' => ['nullable', 'string', 'min:10', 'max:20'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'guardian_name' => ['nullable', 'string', 'max:150'],
                    'description' => ['nullable', 'string', 'max:1000'],
                    'status' => ['required'],
                ], [
                    'name.required' => 'nama kosong',
                    'phone.min' => 'nomor WhatsApp terlalu pendek',
                    'phone.max' => 'nomor WhatsApp terlalu panjang',
                    'email.email' => 'format email salah',
                    'status.required' => 'status harus Aktif atau Tidak Aktif',
                ]);

                $label = 'Baris '.$line.($data['name'] !== '' ? ' ('.Str::limit($data['name'], 40).')' : '');

                if ($validator->fails()) {
                    $skipped[] = $label.': '.$validator->errors()->first();
                    continue;
                }

                if ($data['phone'] && isset($phones[$data['phone']])) {
                    $skipped[] = $label.': WhatsApp +'.$data['phone'].' sudah dipakai murid lain';
                    continue;
                }

                if ($data['email'] && isset($emails[$data['email']])) {
                    $skipped[] = $label.': email '.$data['email'].' sudah dipakai murid lain';
                    continue;
                }

                $student = new ScheduleStudent($data);
                $student->forceFill([
                    'branch_id' => $branch->getKey(),
                    'left_on' => $data['status'] === ScheduleStudent::STATUS_ACTIVE ? null : today(),
                ])->save();

                // Buku Kontak branch, sama dengan StudentController::saved().
                if ($data['phone'] || $data['email']) {
                    $customer = $this->customers->resolve($branch, 'schedules', $data['name'], $data['phone'], $data['email'], 'schedule');
                    $student->forceFill(['customer_id' => $customer->getKey()])->save();
                }

                if ($data['phone']) {
                    $phones[$data['phone']] = true;
                }

                if ($data['email']) {
                    $emails[$data['email']] = true;
                }

                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @return array<int, array<string, mixed>> nomor baris di Excel => [field => nilai] */
    private function read(UploadedFile $file): array
    {
        try {
            // File upload sementara tidak punya ekstensi, jadi jenis reader diambil dari nama file aslinya.
            $type = match (strtolower($file->getClientOriginalExtension())) {
                'xlsx' => 'Xlsx',
                'xls' => 'Xls',
                'csv', 'txt' => 'Csv', // pemisah koma / titik koma dikenali otomatis
                default => null,
            };
            $reader = $type ? IOFactory::createReader($type) : IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($file->getRealPath())->getActiveSheet();
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('File tidak bisa dibaca. Simpan ulang sebagai .xlsx (Excel) atau .csv, lalu coba lagi.');
        }

        $raw = $sheet->toArray(null, true, false, false);
        $header = array_shift($raw) ?? [];
        $columns = [];

        foreach ($header as $index => $title) {
            $title = preg_replace('/\s+/', ' ', strtolower(trim((string) $title)));
            $title = trim(str_replace(['*', '(wajib)', '(opsional)'], '', $title));

            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($columns[$field]) && in_array($title, $aliases, true)) {
                    $columns[$field] = $index;
                }
            }
        }

        if (! isset($columns['name'])) {
            throw new RuntimeException('Kolom "Nama" tidak ditemukan di baris pertama. Pakai template dari tombol "Unduh template".');
        }

        $rows = [];

        foreach ($raw as $i => $cells) {
            $rows[$i + 2] = collect($columns)->map(fn ($index) => $cells[$index] ?? null)->all();
        }

        $rows = array_filter($rows, fn ($row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty());

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('Maksimal '.number_format(self::MAX_ROWS, 0, ',', '.').' murid sekali impor. Bagi file-nya menjadi beberapa bagian.');
        }

        if ($rows === []) {
            throw new RuntimeException('Belum ada data murid di file ini (baris pertama dipakai untuk judul kolom).');
        }

        return $rows;
    }

    /** Nomor HP yang terbaca sebagai angka (mis. 6.28123E+11) dikembalikan ke teks utuh. */
    private function text(mixed $value): string
    {
        if (is_float($value) || is_int($value)) {
            return number_format((float) $value, 0, '', '');
        }

        return trim((string) $value);
    }

    /** "Aktif"/kosong -> active, "Tidak Aktif"/"Nonaktif"/"Keluar" -> inactive, selain itu null (ditolak). */
    private function status(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return match (true) {
            $value === '' || in_array($value, ['aktif', 'active', 'ya', 'y'], true) => ScheduleStudent::STATUS_ACTIVE,
            in_array($value, ['tidak aktif', 'nonaktif', 'non aktif', 'non-aktif', 'inactive', 'keluar', 'resign', 'tidak'], true) => ScheduleStudent::STATUS_INACTIVE,
            default => null,
        };
    }
}
