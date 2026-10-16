<?php

namespace App\Services\Company;

use App\Models\User;
use App\Models\UserCompany;
use App\Services\Media\ImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class CompanyService
{
    private const PROFILE_FIELDS = ['name', 'phone', 'fax', 'email', 'handphone', 'description'];

    public function __construct(private readonly ImageUploader $images)
    {
    }

    /** Dipakai owner di Settings: selalu perusahaan miliknya sendiri. */
    public function saveProfile(User $owner, array $data, ?UploadedFile $logo = null): UserCompany
    {
        return DB::transaction(function () use ($owner, $data, $logo) {
            // Kunci baris user supaya 2 submit bersamaan tidak membuat 2 perusahaan.
            User::whereKey($owner->getKey())->lockForUpdate()->first();

            $company = $owner->company()->firstOrNew();

            return $this->persist($company, Arr::only($data, self::PROFILE_FIELDS), $logo);
        });
    }

    /** Dipakai superadmin: pemilik dipilih saat dibuat, status & limit ikut diatur. */
    public function saveByAdmin(UserCompany $company, array $data, ?UploadedFile $logo = null): UserCompany
    {
        return DB::transaction(function () use ($company, $data, $logo) {
            if (! $company->exists) {
                $company->user_id = $data['user_id'];
            }

            $company->status = $data['status'];
            $company->limit = $data['limit'] ?? null;

            return $this->persist($company, Arr::only($data, self::PROFILE_FIELDS), $logo);
        });
    }

    public function delete(UserCompany $company): void
    {
        $logo = $company->logo;

        DB::transaction(fn () => $company->delete());

        $this->images->delete($logo);
    }

    private function persist(UserCompany $company, array $profile, ?UploadedFile $logo): UserCompany
    {
        $company->fill($profile);
        $company->id ??= $company->newUniqueId();

        $oldLogo = $company->logo;

        if ($logo) {
            $company->logo = $this->images->store($logo, "companies/{$company->id}", 'logo', 512);
        }

        try {
            $company->save();
        } catch (Throwable $e) {
            if ($logo) {
                $this->images->delete($company->logo);
            }

            throw $e;
        }

        if ($logo) {
            DB::afterCommit(fn () => $this->images->delete($oldLogo));
        }

        return $company;
    }
}
