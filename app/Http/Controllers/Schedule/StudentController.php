<?php

namespace App\Http\Controllers\Schedule;

use App\Mail\ScheduleCardMail;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleStudent;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaDevice;
use App\Services\Customer\CustomerService;
use App\Services\Schedule\ScheduleRoutineService;
use App\Services\WhatsApp\WaGateway;
use App\Support\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends ScheduleResourceController
{
    protected string $resource = 'students';

    protected array $columns = ['Kontak', 'Orang Tua / Wali'];

    protected array $searchable = ['name', 'email', 'phone', 'guardian_name'];

    protected bool $uniqueName = false;

    public function __construct(private readonly CustomerService $customers)
    {
    }

    protected function prepare(Request $request): void
    {
        $request->merge([
            'phone' => User::normalizePhone($request->input('phone')) ?: null,
            'email' => filled($request->input('email')) ? strtolower(trim($request->input('email'))) : null,
        ]);
    }

    /**
     * Kirim kartu absen QR ke email terdaftar, atau ke WhatsApp murid lewat nomor WhatsApp branch
     * (hanya kalau paket WhatsApp aktif & ada nomor yang terhubung). Tanpa paket, admin memakai link wa.me di menu.
     */
    public function sendCard(Request $request, string $student, WaGateway $wa): RedirectResponse
    {
        $item = ScheduleStudent::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->with('branch:id,name')->findOrFail($student);
        $via = $request->validate(['via' => ['required', Rule::in(['email', 'whatsapp'])]])['via'];

        if ($via === 'email') {
            if (! $item->email) {
                return back()->with('error', "{$item->name} belum punya email. Tambahkan dulu lewat Edit, lalu kirim lagi.");
            }

            Mail::to($item->email)->queue(new ScheduleCardMail($item->getKey()));

            return back()->with('success', "Kartu absen {$item->name} sedang dikirim ke {$item->email}.");
        }

        $device = self::waDevices()[$item->branch_id] ?? null;

        if (! $item->phone || ! $device) {
            return back()->with('error', $item->phone
                ? 'Nomor WhatsApp branch belum terhubung. Kirim lewat link WhatsApp manual dulu ya.'
                : "{$item->name} belum punya nomor WhatsApp. Tambahkan dulu lewat Edit.");
        }

        $result = $wa->sendText($item->branch_id, ['device_id' => $device, 'to' => $item->phone, 'text' => $item->cardMessage(), 'client_ref' => (string) Str::uuid()]);

        return back()->with($result['success'] ? 'success' : 'error', $result['success']
            ? "Kartu absen {$item->name} terkirim ke WhatsApp {$item->phone}."
            : 'WhatsApp belum terkirim: '.($result['message'] ?? 'coba lagi sebentar lagi.'));
    }

    /** [branch_id => device_id] nomor WhatsApp yang terhubung di branch dengan paket WhatsApp aktif (sekali per request). */
    public static function waDevices(): array
    {
        return once(fn () => WaDevice::connectedByBranch());
    }

    protected function extraRules(Request $request, string $branchId, Model $item): array
    {
        $unique = fn (string $column) => Rule::unique($item->getTable(), $column)->where('branch_id', $branchId)->ignore($item->getKey());

        return [
            'email' => ['nullable', 'email', 'max:255', $unique('email')],
            'phone' => ['nullable', 'string', 'min:10', 'max:20', $unique('phone')],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'left_on' => ['nullable', 'date'],
        ];
    }

    protected function attributes(): array
    {
        return ['phone' => 'nomor WhatsApp', 'guardian_name' => 'nama orang tua / wali', 'left_on' => 'tanggal keluar'];
    }

    protected function isUsed(Model $item): bool
    {
        return DB::table('schedule_participants')->where('student_id', $item->getKey())->exists()
            || ScheduleAttendance::where('student_id', $item->getKey())->exists();
    }

    /**
     * Murid Tidak Aktif = resign/keluar per tanggal keluar (bawaan hari ini): dilepas dari jadwal & sesi mendatang,
     * riwayat absensi tetap. Murid yang punya WA/email ikut tercatat di Buku Kontak branch.
     */
    protected function saved(Request $request, Model $item, array $data): void
    {
        if ($item->isActive()) {
            $item->forceFill(['left_on' => null])->save();
        } else {
            $item->forceFill(['left_on' => $item->left_on ?? today()])->save();
            app(ScheduleRoutineService::class)->removeStudent($item->getKey(), $item->left_on);
        }

        if (! $item->phone && ! $item->email) {
            return;
        }

        $branch = UserBranch::whereKey($item->branch_id)->lockForUpdate()->firstOrFail();
        $customer = $this->customers->resolve($branch, 'schedules', $item->name, $item->phone, $item->email, 'schedule');
        $item->forceFill(['customer_id' => $customer->getKey()])->save();
    }
}
