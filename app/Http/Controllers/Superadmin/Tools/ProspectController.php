<?php

namespace App\Http\Controllers\Superadmin\Tools;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use App\Support\XlsxExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Superadmin › Tools › Prospek: calon pelanggan yang disimpan dari Tools › Open Places, untuk follow-up. */
class ProspectController extends Controller
{
    /** Kolom Excel, mengikuti Google Maps + follow-up. */
    private const COLUMNS = [
        'name' => 'Nama', 'category' => 'Kategori', 'address' => 'Alamat', 'city' => 'Kecamatan / Kota', 'province' => 'Provinsi',
        'postal_code' => 'Kode Pos', 'phone' => 'Telepon', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'website' => 'Website',
        'rating' => 'Rating', 'rating_count' => 'Jumlah Ulasan', 'opening_hours' => 'Jam Buka', 'maps_url' => 'Link Peta',
        'lat' => 'Latitude', 'lon' => 'Longitude', 'is_closed' => 'Buka / Tutup', 'status' => 'Status', 'notes' => 'Catatan',
        'contacted_at' => 'Dihubungi', 'created_at' => 'Disimpan',
    ];

    public function index(Request $request): View
    {
        return view('superadmin.tools.prospects', [
            'prospects' => $this->filtered($request)->latest()->paginate(15)->withQueryString(),
            'counts' => Prospect::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'cities' => Prospect::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    /** Ubah status, catatan, dan kolom yang diisi manual (rating, ulasan, jam buka, email, website). */
    public function update(Request $request, Prospect $prospect): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Prospect::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'rating_count' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'opening_hours' => ['nullable', 'string', 'max:300'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url:http,https', 'max:300'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        // Nomor HP 08xx / +628xx -> WhatsApp 628xx.
        $digits = preg_replace('/^0/', '62', preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')));
        $data['whatsapp'] = preg_match('/^628\d{6,13}$/', $digits) ? $digits : null;
        // Tanggal pertama kali dihubungi tercatat otomatis saat status pertama kali keluar dari "Baru".
        $data['contacted_at'] = $prospect->contacted_at ?? ($data['status'] !== 'new' ? now() : null);

        $prospect->update($data);

        return back()->with('success', "Prospek {$prospect->name} diperbarui.");
    }

    public function destroy(Prospect $prospect): RedirectResponse
    {
        $prospect->delete();

        return back()->with('success', "Prospek {$prospect->name} dihapus.");
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->latest()->limit(5000)->get()->map(fn (Prospect $p) => collect(self::COLUMNS)->map(fn ($label, $key) => match ($key) {
            'is_closed' => $p->is_closed ? 'Tutup' : 'Buka',
            'status' => $p->statusLabel(),
            'contacted_at', 'created_at' => $p->{$key}?->format('Y-m-d H:i') ?? '',
            'rating', 'lat', 'lon' => $p->{$key} ?? '',
            'rating_count' => $p->rating_count ?? '',
            default => (string) ($p->{$key} ?? ''),
        })->all());

        return XlsxExport::download('prospek-'.now()->format('Ymd-His'), array_values(self::COLUMNS), $rows);
    }

    private function filtered(Request $request): Builder
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Prospect::STATUSES))],
            'city' => ['nullable', 'string', 'max:150'],
        ]);
        $search = trim((string) ($data['search'] ?? ''));

        return Prospect::query()
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('address', 'like', "%{$search}%")));
    }
}
