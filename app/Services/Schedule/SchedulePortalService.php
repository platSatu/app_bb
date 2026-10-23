<?php

namespace App\Services\Schedule;

use App\Models\SchedulePortalLink;
use App\Models\SchedulePortalRequest;
use App\Models\ScheduleStudent;
use App\Models\ScheduleTeacher;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pendaftaran portal: link per branch -> permintaan -> disetujui admin -> terhubung ke data murid/pengajar.
 * Semua perubahan memakai lockForUpdate supaya klik ganda tidak membuat data dobel.
 */
class SchedulePortalService
{
    public function __construct(private readonly CustomerService $customers)
    {
    }

    /** Link murid & pengajar untuk setiap branch; yang belum ada dibuatkan. */
    public function linksFor(Collection $branches): Collection
    {
        foreach ($branches as $branch) {
            foreach (array_keys(SchedulePortalLink::TYPE_LABELS) as $type) {
                if (! SchedulePortalLink::where('branch_id', $branch->getKey())->where('type', $type)->exists()) {
                    (new SchedulePortalLink())->forceFill(['branch_id' => $branch->getKey(), 'type' => $type, 'token' => SchedulePortalLink::newToken()])->save();
                }
            }
        }

        return SchedulePortalLink::whereIn('branch_id', $branches->modelKeys())->get()->groupBy('branch_id');
    }

    public function request(SchedulePortalLink $link, User $user, string $name, ?string $guardian): SchedulePortalRequest
    {
        return DB::transaction(function () use ($link, $user, $name, $guardian) {
            User::whereKey($user->getKey())->lockForUpdate()->first();

            $pending = SchedulePortalRequest::where('branch_id', $link->branch_id)->where('user_id', $user->getKey())
                ->where('type', $link->type)->where('status', SchedulePortalRequest::STATUS_PENDING);

            if ($link->type === SchedulePortalLink::TYPE_TEACHER) {
                if (ScheduleTeacher::where('branch_id', $link->branch_id)->where('user_id', $user->getKey())->exists()) {
                    throw new RuntimeException('Akun Anda sudah terdaftar sebagai pengajar di sini.');
                }

                if ($pending->exists()) {
                    throw new RuntimeException('Pendaftaran Anda sebelumnya masih menunggu persetujuan admin.');
                }
            } else {
                $sameName = fn ($query) => $query->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

                if ($sameName(ScheduleStudent::where('branch_id', $link->branch_id)->where('user_id', $user->getKey()))->exists()) {
                    throw new RuntimeException("{$name} sudah terhubung dengan akun Anda di sini.");
                }

                if ($sameName($pending)->exists()) {
                    throw new RuntimeException("Pendaftaran {$name} masih menunggu persetujuan admin.");
                }
            }

            $request = new SchedulePortalRequest();
            $request->forceFill([
                'branch_id' => $link->branch_id,
                'user_id' => $user->getKey(),
                'type' => $link->type,
                'name' => $name,
                'guardian_name' => $guardian,
                'email' => $user->email,
                'phone' => $user->handphone,
                'status' => SchedulePortalRequest::STATUS_PENDING,
            ])->save();

            return $request;
        });
    }

    /** Data di branch yang mungkin orang yang sama (email, WA, atau nama sama) dan belum punya akun. */
    public function matches(SchedulePortalRequest $request): Collection
    {
        return $this->modelFor($request)::where('branch_id', $request->branch_id)
            ->whereNull('user_id')
            ->where(fn ($q) => $q->where('name', $request->name)
                ->when($request->email, fn ($w, $email) => $w->orWhere('email', $email))
                ->when($request->phone, fn ($w, $phone) => $w->orWhere('phone', $phone)))
            ->limit(5)
            ->get(['id', 'name', 'email', 'phone']);
    }

    /** $memberId = id murid/pengajar yang sudah ada, atau null untuk membuat data baru. */
    public function approve(SchedulePortalRequest $request, ?string $memberId, User $actor): ScheduleStudent|ScheduleTeacher
    {
        return DB::transaction(function () use ($request, $memberId, $actor) {
            $branch = UserBranch::whereKey($request->branch_id)->lockForUpdate()->firstOrFail();
            $locked = SchedulePortalRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new RuntimeException('Pendaftaran ini sudah diproses sebelumnya.');
            }

            $model = $this->modelFor($locked);

            if (! $locked->isStudent() && ScheduleTeacher::where('branch_id', $branch->getKey())->where('user_id', $locked->user_id)->exists()) {
                throw new RuntimeException('Akun ini sudah terhubung ke pengajar lain di branch ini.');
            }

            if ($memberId) {
                $member = $model::where('branch_id', $branch->getKey())->whereKey($memberId)->lockForUpdate()->first();

                if (! $member || $member->user_id) {
                    throw new RuntimeException('Data yang dipilih tidak ditemukan atau sudah punya akun portal.');
                }
            } else {
                $member = new $model();
                $taken = fn (string $column, ?string $value) => $value && $model::where('branch_id', $branch->getKey())->where($column, $value)->exists();
                $member->fill([
                    'name' => $locked->name,
                    // Email/WA per branch harus unik; anak kedua dengan akun yang sama cukup terhubung lewat akun.
                    'email' => $taken('email', $locked->email) ? null : $locked->email,
                    'phone' => $taken('phone', $locked->phone) ? null : $locked->phone,
                    'status' => $model::STATUS_ACTIVE,
                    ...($locked->isStudent() ? ['guardian_name' => $locked->guardian_name] : []),
                ]);
                $member->forceFill(['branch_id' => $branch->getKey()]);
            }

            $member->forceFill(['user_id' => $locked->user_id])->save();

            if ($locked->isStudent() && ! $member->customer_id && ($locked->phone || $locked->email)) {
                $customer = $this->customers->resolve($branch, 'schedules', $locked->guardian_name ?: $locked->name, $locked->phone, $locked->email, 'schedule');
                $member->forceFill(['customer_id' => $customer->getKey()])->save();
            }

            $locked->forceFill([
                'status' => SchedulePortalRequest::STATUS_APPROVED,
                'member_id' => $member->getKey(),
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            return $member;
        });
    }

    public function reject(SchedulePortalRequest $request, ?string $reason, User $actor): void
    {
        DB::transaction(function () use ($request, $reason, $actor) {
            $locked = SchedulePortalRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new RuntimeException('Pendaftaran ini sudah diproses sebelumnya.');
            }

            $locked->forceFill([
                'status' => SchedulePortalRequest::STATUS_REJECTED,
                'reject_reason' => $reason,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();
        });
    }

    /** @return class-string<ScheduleStudent|ScheduleTeacher> */
    private function modelFor(SchedulePortalRequest $request): string
    {
        return $request->isStudent() ? ScheduleStudent::class : ScheduleTeacher::class;
    }
}
