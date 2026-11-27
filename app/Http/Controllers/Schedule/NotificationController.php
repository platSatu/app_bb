<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Schedule\Concerns\InScheduleArea;
use App\Models\UserBranch;
use App\Models\WaDevice;
use App\Services\Subscription\AppAccess;
use App\Support\ScheduleNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Schedules › Notifikasi (owner): sakelar on/off, jam kirim, dan isi pesan WhatsApp Jadwal per branch.
 * Hanya branch dengan paket Jadwal & WhatsApp aktif yang bisa diatur. Disimpan di user_branches.wa_notify.
 */
class NotificationController extends Controller
{
    use InScheduleArea;

    public function index(Request $request): View|RedirectResponse
    {
        $branches = $this->branches($request)->orderBy('name')->get(['id', 'name', 'status', 'wa_notify']);

        if ($branches->isEmpty()) {
            return $this->branchRequired();
        }

        $whatsapp = UserBranch::whereKey($branches->modelKeys())->whereIn('id', AppAccess::activeBranches('whatsapp'))->pluck('id')->all();

        return view('schedule.notifications', [
            'resource' => 'notifications',
            'routes' => 'schedules',
            'branches' => $branches->filter(fn (UserBranch $branch) => in_array($branch->id, $whatsapp, true))->values(),
            'connected' => WaDevice::connectedByBranch(),
            'adminPhone' => $request->user()->handphone,
            'hasPin' => filled($request->user()->wa_pin),
        ]);
    }

    public function update(Request $request, string $branch): RedirectResponse
    {
        $model = $this->branches($request)->findOrFail($branch);
        abort_unless(app(AppAccess::class)->branchActive($model->getKey(), 'whatsapp'), 403);

        $data = $request->validate([
            'schedule_at' => ['required', 'date_format:H:i'],
            'teacher_recap_at' => ['required', 'date_format:H:i'],
            'schedule_soon_minutes' => ['required', 'integer', Rule::in(array_keys(ScheduleNotice::SOON_OPTIONS))],
            'templates' => ['nullable', 'array'],
            'templates.*' => ['nullable', 'string', 'max:'.ScheduleNotice::TEMPLATE_MAX],
        ], ['templates.*.max' => 'Isi pesan maksimal :max karakter.'], ['schedule_at' => 'jam pengingat murid', 'teacher_recap_at' => 'jam rekap pengajar']);

        // Pesan yang dikosongkan / sama persis dengan bawaan disimpan kosong = ikut template bawaan.
        $templates = collect(ScheduleNotice::KINDS)->map(function ($meta, $kind) use ($data) {
            $text = trim(str_replace("\r\n", "\n", (string) ($data['templates'][$kind] ?? '')));

            return $text === '' || $text === $meta[3] ? null : $text;
        })->filter()->all();

        $model->forceFill(['wa_notify' => [
            ...($model->wa_notify ?? []),
            ...collect(ScheduleNotice::KINDS)->map(fn ($meta, $kind) => $request->boolean("notify.{$kind}"))->all(),
            ScheduleNotice::BOT => $request->boolean('notify.'.ScheduleNotice::BOT),
            ...collect($data)->only(array_keys(ScheduleNotice::TIMES))->all(),
            'templates' => $templates,
        ]])->save();

        return redirect()->to(route('schedules.notifications.index').'#branch-'.$model->getKey())
            ->with('success', "Pengaturan notifikasi {$model->name} tersimpan.");
    }

    /**
     * PIN menu admin WhatsApp (4–6 angka), dipakai untuk aksi besar lewat WhatsApp: setujui semua pengajuan,
     * tagih semua murid, kirim pengingat massal. Membuat / mengganti PIN wajib memasukkan kata sandi akun.
     */
    public function pin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'pin' => ['required', 'regex:/^\d{4,6}$/', 'confirmed'],
        ], [
            'pin.regex' => 'PIN berupa 4 sampai 6 angka.',
            'pin.confirmed' => 'Ulangi PIN belum sama.',
            'current_password.current_password' => 'Kata sandi akun belum benar.',
        ], ['current_password' => 'kata sandi akun', 'pin' => 'PIN']);

        $request->user()->forceFill(['wa_pin' => Hash::make($data['pin'])])->save();

        return redirect()->to(route('schedules.notifications.index').'#pin-admin')->with('success', 'PIN admin WhatsApp tersimpan. Jangan bagikan PIN ini ke siapa pun ya.');
    }

    /** Branch milik owner yang paket Jadwal-nya aktif. */
    private function branches(Request $request)
    {
        return UserBranch::where('user_id', $request->user()->getKey())->whereIn('id', AppAccess::activeBranches('schedules'));
    }
}
