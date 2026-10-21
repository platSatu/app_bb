<?php

namespace App\Services\Page;

use App\Models\Customer;
use App\Models\PageCategory;
use App\Models\PageSubmission;
use App\Models\PageSubmissionAnswer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Jawaban Form Pembeli. Label & jenis disalin, supaya data lama tetap utuh walau pertanyaan diubah/dihapus. */
class PageSubmissionService
{
    // File pembeli (KTP, bukti, dll) di disk privat; hanya bisa diunduh lewat route yang dicek izinnya.
    public const DISK = 'local';

    public static function directory(PageCategory $page): string
    {
        return 'page-submissions/'.$page->getKey();
    }

    /**
     * WAJIB dipanggil di dalam transaksi pemanggil (mis. pembuatan pesanan tiket).
     * $answers sudah tervalidasi: [field_id => value|UploadedFile|array].
     */
    public function submit(PageCategory $page, Customer $customer, array $answers, ?string $ip, ?string $userAgent): PageSubmission
    {
        $submission = new PageSubmission();
        $submission->forceFill([
            'page_category_id' => $page->getKey(),
            'company_id' => $page->branch->company_id,
            'customer_id' => $customer->getKey(),
            'reference' => $this->uniqueReference(),
            'ip_address' => $ip,
            'user_agent' => Str::limit((string) $userAgent, 250, ''),
        ])->save();

        $stored = [];

        try {
            foreach ($page->fields as $index => $field) {
                $value = $answers[$field->getKey()] ?? null;
                $answer = new PageSubmissionAnswer();
                $answer->forceFill([
                    'page_submission_id' => $submission->getKey(),
                    'page_form_field_id' => $field->getKey(),
                    'label' => $field->label,
                    'type' => $field->type,
                    'sort_order' => $index,
                ]);

                if ($value instanceof UploadedFile) {
                    $stored[] = $path = $value->store(self::directory($page).'/'.$submission->getKey(), self::DISK);
                    $answer->forceFill(['file_path' => $path, 'file_name' => Str::limit($value->getClientOriginalName(), 140, '')]);
                } else {
                    $answer->value = is_array($value) ? implode(', ', $value) : ($value === null ? null : (string) $value);
                }

                $answer->save();
            }
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($stored);

            throw $e;
        }

        return $submission;
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'FRM'.now()->format('ymd').Str::upper(Str::random(5));
        } while (PageSubmission::where('reference', $reference)->exists());

        return $reference;
    }
}
