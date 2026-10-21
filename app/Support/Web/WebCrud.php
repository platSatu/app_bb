<?php

namespace App\Support\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * CRUD bersama modul Website (Superadmin > Settings > Website), dipindahkan dari teleios.
 * Akses sudah dijaga route (middleware superadmin + permission per menu); di sini semua
 * tulis berjalan dalam transaksi dengan lockForUpdate.
 */
class WebCrud
{
    private const PER_PAGE = 20;

    public static function getAll(string $modelClass, array $relations = [], ?string $search = null, array $searchFields = ['name'], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $modelClass::with($relations)
            ->when($search && $searchFields, fn ($query) => $query->where(function ($q) use ($search, $searchFields) {
                foreach ($searchFields as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function find(string $modelClass, mixed $id, array $relations = []): Model
    {
        return $modelClass::with($relations)->findOrFail($id);
    }

    public static function store(string $modelClass, array $data, ?callable $beforeCreate = null, ?callable $afterCreate = null): Model
    {
        return DB::transaction(function () use ($modelClass, $data, $beforeCreate, $afterCreate) {
            $model = $modelClass::create($beforeCreate ? $beforeCreate($data) : $data);

            if ($afterCreate) {
                $afterCreate($model);
            }

            return $model->fresh();
        });
    }

    public static function update(string $modelClass, mixed $id, array $data, ?callable $beforeUpdate = null, ?callable $afterUpdate = null): Model
    {
        return DB::transaction(function () use ($modelClass, $id, $data, $beforeUpdate, $afterUpdate) {
            $model = $modelClass::lockForUpdate()->findOrFail($id);
            $model->fill($beforeUpdate ? $beforeUpdate($model, $data) : $data)->save();

            if ($afterUpdate) {
                $afterUpdate($model);
            }

            return $model->fresh();
        });
    }

    public static function delete(string $modelClass, mixed $id, ?callable $beforeDelete = null): bool
    {
        return DB::transaction(function () use ($modelClass, $id, $beforeDelete) {
            $model = $modelClass::lockForUpdate()->findOrFail($id);

            if ($beforeDelete) {
                $beforeDelete($model);
            }

            return (bool) $model->delete();
        });
    }
}
