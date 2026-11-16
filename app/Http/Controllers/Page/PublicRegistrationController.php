<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\UserBranch;
use App\Rules\Turnstile;
use App\Services\Customer\CustomerService;
use App\Services\Page\PageSubmissionService;
use App\Support\Apps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Halaman publik Registrasi: isian yang disusun owner di Form Pendaftaran dikirim ke sini,
 * lalu disimpan lewat PageSubmissionService -- mesin yang sama dengan Form Pembeli di Tiket.
 *
 * Pengamannya tiga lapis, sama seperti Quiz & Tiket: captcha Turnstile, honeypot (isian
 * tersembunyi yang harus tetap kosong), dan batas 10 kiriman per menit di route.
 */
class PublicRegistrationController extends Controller
{
    public function store(Request $request, string $slug, CustomerService $customers, PageSubmissionService $submissions): RedirectResponse
    {
        $page = PageCategory::with(['type', 'fields', 'branch'])->where('slug', $slug)->firstOrFail();
        abort_unless($page->isTemplate(PageType::TEMPLATE_REGISTRATION) && $page->isOpen() && (bool) $page->branch?->isActive(), 404);
        abort_if($page->fields->isEmpty(), 404);

        // Aturan tiap isian dibentuk dari Form Pendaftaran, jadi validasi selalu mengikuti
        // apa yang diatur owner -- termasuk wajib/tidaknya dan jenis berkas yang boleh diunggah.
        $rules = ['website' => ['nullable', 'max:0'], 'cf-turnstile-response' => [new Turnstile()]];
        $labels = [];

        foreach ($page->fields as $field) {
            $rules['f.'.$field->getKey()] = $field->rules();
            $labels['f.'.$field->getKey()] = mb_strtolower($field->label);
        }

        $request->validate($rules, ['website.max' => 'Pendaftaran gagal dikirim.'], $labels);
        $answers = (array) $request->input('f', []);

        // Berkas tidak ikut di input(), jadi disisipkan dari file() memakai kunci yang sama.
        foreach ($page->fields as $field) {
            if ($field->type === 'file' && $request->hasFile('f.'.$field->getKey())) {
                $answers[$field->getKey()] = $request->file('f.'.$field->getKey());
            }
        }

        $submission = DB::transaction(function () use ($page, $answers, $request, $customers, $submissions) {
            // Branch dikunci dulu, sama seperti saat menambah kontak, supaya dua pendaftaran
            // bersamaan dengan nomor sama tidak membuat customer kembar.
            $branch = UserBranch::whereKey($page->branch_id)->lockForUpdate()->firstOrFail();

            $customer = $customers->resolve(
                $branch,
                Apps::PAGES,
                $this->answerOf($page, $answers, 'text'),
                $this->answerOf($page, $answers, 'phone'),
                $this->answerOf($page, $answers, 'email'),
                'registration',
            );

            return $submissions->submit($page, $customer, $answers, $request->ip(), $request->userAgent());
        });

        return back()->with('registered', $submission->reference);
    }

    /**
     * Nama, WhatsApp & email pendaftar dibaca dari JENIS isiannya, bukan dari label atau urutan,
     * supaya tetap kebaca walau owner mengganti kata "Nama lengkap" jadi "Nama murid".
     */
    private function answerOf(PageCategory $page, array $answers, string $type): ?string
    {
        $field = $page->fields->firstWhere('type', $type);
        $value = $field ? ($answers[$field->getKey()] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
