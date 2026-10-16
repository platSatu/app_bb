<?php

namespace App\Services\Page;

use App\Models\PageCategory;
use App\Models\PageSubmission;
use App\Models\PageSubmissionAnswer;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PageSubmissionService
{
    // File pendaftar (KTP, bukti, dll) di disk private; hanya bisa diunduh lewat route yang dicek izinnya.
    public const DISK = 'local';

    public function __construct(private readonly CustomerService $customers)
    {
    }

    public static function directory(PageCategory $page): string
    {
        return 'page-submissions/'.$page->getKey();
    }

    /** $answers sudah tervalidasi: [field_id => value|UploadedFile|array]. */
    public function submit(PageCategory $page, array $answers, ?string $ip, ?string $userAgent): PageSubmission
    {
        $submission = new PageSubmission();
        $submission->forceFill([
            'id' => $submission->newUniqueId(),
            'page_category_id' => $page->getKey(),
            'company_id' => $page->branch->company_id,
            'reference' => $this->uniqueReference(),
            'ip_address' => $ip,
            'user_agent' => Str::limit((string) $userAgent, 250, ''),
        ]);

        $stored = [];

        try {
            return DB::transaction(function () use ($page, $answers, $submission, &$stored) {
                // Kunci branch: kiriman bersamaan ke branch yang sama antre, kontak tidak kembar.
                $branch = UserBranch::whereKey($page->branch_id)->lockForUpdate()->firstOrFail();
                $contact = $page->fields->whereNotNull('contact_field')->mapWithKeys(
                    fn ($field) => [$field->contact_field => $answers[$field->getKey()] ?? null]
                );

                $customer = $this->customers->resolve(
                    $branch,
                    Str::limit((string) $contact->get('name'), 150, ''),
                    $contact->get('phone'),
                    $contact->get('email'),
                    'Page: '.Str::limit($page->name, 200, ''),
                );

                $submission->customer_id = $customer->getKey();
                $submission->save();

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

                return $submission;
            });
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($stored);

            throw $e;
        }
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'REG'.now()->format('ymd').Str::upper(Str::random(5));
        } while (PageSubmission::where('reference', $reference)->exists());

        return $reference;
    }
}
