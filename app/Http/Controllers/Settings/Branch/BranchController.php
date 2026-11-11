<?php

namespace App\Http\Controllers\Settings\Branch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Branch\BranchRequest;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Models\WaDevice;
use App\Services\Media\QrCodeGenerator;
use App\Services\Subscription\AppAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Branch selalu milik perusahaan user yang login. company_id & user_id
 * diambil dari sesi login, tidak pernah dari form atau URL.
 */
class BranchController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (! $company = $request->user()->company) {
            return $this->companyRequired();
        }

        $branches = $company->branches()
            ->search($request->query('search'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Branch dengan paket WhatsApp aktif mendapat tombol pintas ke sakelar notifikasi WhatsApp.
        $waBranches = UserBranch::whereKey($branches->pluck('id'))->whereIn('id', AppAccess::activeBranches('whatsapp'))->pluck('id')->all();

        return view('settings.branch.index', compact('branches', 'waBranches'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()->company) {
            return $this->companyRequired();
        }

        return view('settings.branch.create', ['branch' => new UserBranch(['status' => UserBranch::STATUS_ACTIVE])]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $branch = new UserBranch($request->validated());
        $branch->user_id = $request->user()->getKey();
        $branch->company_id = $request->user()->company->getKey();
        $branch->save();

        return redirect()->route('settings.branches.index')
            ->with('success', "Branch {$branch->name} berhasil ditambahkan.");
    }

    public function edit(Request $request, string $branch, QrCodeGenerator $qr): View
    {
        $model = $this->ownedBranch($request, $branch);
        $hasSchedules = app(AppAccess::class)->branchActive($model->getKey(), 'schedules');

        return view('settings.branch.edit', [
            'branch' => $model,
            // Sakelar notifikasi WhatsApp hanya ditampilkan selama paket WhatsApp branch ini aktif.
            'hasWhatsapp' => app(AppAccess::class)->branchActive($model->getKey(), 'whatsapp'),
            'waConnected' => WaDevice::connectedByBranch($model->getKey()) !== [],
            // QR tempat untuk absen pengajar, hanya selama paket Jadwal branch ini aktif.
            'placeQr' => $hasSchedules ? $qr->dataUri($model->placeQr(), 10) : null,
        ]);
    }

    /** Ganti QR tempat: QR lama (yang tercetak / sempat difoto) langsung tidak berlaku. */
    public function placeQr(Request $request, string $branch): RedirectResponse
    {
        $model = $this->ownedBranch($request, $branch);
        abort_unless(app(AppAccess::class)->branchActive($model->getKey(), 'schedules'), 403);

        $model->increment('place_qr_version');

        return redirect()->to(route('settings.branches.edit', $model).'#qr-tempat')
            ->with('success', 'QR tempat '.$model->name.' sudah diganti. Cetak QR baru dan tempel menggantikan yang lama ya.');
    }

    /** Simpan sakelar notifikasi WhatsApp (Invoice, Tiket, Jadwal). Ditolak kalau paket WhatsApp tidak aktif. */
    public function waNotify(Request $request, string $branch): RedirectResponse
    {
        $model = $this->ownedBranch($request, $branch);
        abort_unless(app(AppAccess::class)->branchActive($model->getKey(), 'whatsapp'), 403);

        $model->forceFill(['wa_notify' => collect(UserBranch::WA_NOTIFY)->map(fn ($meta, $kind) => $request->boolean("wa_notify.{$kind}"))->all()])->save();

        return back()->with('success', 'Pengaturan notifikasi WhatsApp '.$model->name.' tersimpan.');
    }

    public function update(BranchRequest $request, string $branch): RedirectResponse
    {
        $model = $this->ownedBranch($request, $branch);
        $model->update($request->validated());

        return redirect()->route('settings.branches.index')
            ->with('success', "Branch {$model->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, string $branch): RedirectResponse
    {
        $model = $this->ownedBranch($request, $branch);
        $model->delete();

        return redirect()->route('settings.branches.index')
            ->with('success', "Branch {$model->name} sudah dihapus.");
    }

    private function ownedBranch(Request $request, string $id): UserBranch
    {
        $company = $request->user()->company;
        abort_unless($company instanceof UserCompany, 404);

        return $company->branches()->findOrFail($id);
    }

    private function companyRequired(): RedirectResponse
    {
        return redirect()->route('settings.company.edit')
            ->with('info', 'Lengkapi profil perusahaan dulu ya, setelah itu Anda bisa menambah branch.');
    }
}
