<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Schedule\Concerns\InScheduleArea;
use App\Models\ScheduleCategory;
use App\Models\ScheduleGrade;
use App\Models\ScheduleRoom;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;
use App\Models\ScheduleSubject;
use App\Models\ScheduleTeacher;
use App\Models\User;
use App\Models\UserBranch;
use App\Support\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Master data Jadwal, dipakai bersama owner (/schedules) & superadmin (/superadmin/schedules).
 * Urutan induk: Ruangan > Bidang > Kategori > Grade. Branch selalu ikut induknya, tidak dari form.
 * Semua data dicari lewat accessibleBy(), jadi id dari URL/form milik orang lain tidak bisa dipakai.
 */
abstract class ScheduleResourceController extends Controller
{
    use InScheduleArea;

    public const RESOURCES = [
        'rooms' => ['label' => 'Ruangan', 'icon' => 'ri-door-open-line', 'model' => ScheduleRoom::class, 'placeholder' => 'mis. Melati'],
        'subjects' => ['label' => 'Bidang', 'icon' => 'ri-music-2-line', 'model' => ScheduleSubject::class, 'placeholder' => 'mis. Bass', 'parent' => 'rooms', 'key' => 'room_id'],
        'categories' => ['label' => 'Kategori', 'icon' => 'ri-price-tag-3-line', 'model' => ScheduleCategory::class, 'placeholder' => 'mis. Pop', 'parent' => 'subjects', 'key' => 'subject_id'],
        'grades' => ['label' => 'Grade', 'icon' => 'ri-medal-line', 'model' => ScheduleGrade::class, 'placeholder' => 'mis. A', 'parent' => 'categories', 'key' => 'category_id'],
        'teachers' => ['label' => 'Pengajar', 'icon' => 'ri-user-star-line', 'model' => ScheduleTeacher::class, 'placeholder' => 'Nama lengkap pengajar'],
        'students' => ['label' => 'Murid', 'icon' => 'ri-graduation-cap-line', 'model' => ScheduleStudent::class, 'placeholder' => 'Nama lengkap murid'],
    ];

    /** Tab di atas semua halaman Schedules: jadwal & sesi dulu, lalu master data. */
    public static function tabs(): array
    {
        return [
            'routines' => ['label' => 'Jadwal Rutin', 'icon' => 'ri-calendar-2-line'],
            'sessions' => ['label' => 'Sesi & Absensi', 'icon' => 'ri-checkbox-multiple-line'],
            ...self::RESOURCES,
        ];
    }

    protected string $resource;

    /** Judul kolom tambahan di index; isinya dari schedule/{resource}/_row. */
    protected array $columns = [];

    protected array $with = [];

    protected array $searchable = ['name'];

    protected bool $uniqueName = true;

    /** Kolom di jadwal rutin & sesi yang menunjuk data ini; dipakai = tidak boleh dihapus. */
    protected ?string $usageColumn = null;

    public function index(Request $request): View|RedirectResponse
    {
        $config = $this->config();
        $user = $request->user();
        $search = trim((string) $request->query('search'));
        $parentId = isset($config['parent']) ? $request->query('parent') : null;
        $child = $this->child();

        if (! $this->isAdmin($request) && ! UserBranch::where('user_id', $user->getKey())->exists()) {
            return $this->branchRequired();
        }

        $items = $this->model()::accessibleBy($user)
            ->with(['branch:id,name,company_id', 'branch.company:id,name', ...$this->with])
            ->when($child, fn ($q) => $q->withCount($child))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => collect($this->searchable)->each(fn ($column) => $w->orWhere($column, 'like', "%{$search}%"))))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($parentId, fn ($q) => $q->where($config['key'], $parentId))
            ->when($request->query('branch'), fn ($q, $id) => $q->where('branch_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('schedule.index', [
            ...$this->shared($request),
            'items' => $items,
            'parentFilter' => $parentId ? $this->modelOf($config['parent'])::accessibleBy($user)->with($this->labelWith($config['parent']))->find($parentId) : null,
            'parentOptions' => isset($config['parent']) && ! $this->isAdmin($request) ? $this->parentOptions($user) : collect(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $config = $this->config();
        $item = $this->newModel();
        $options = collect();

        if (isset($config['parent'])) {
            $parentConfig = self::RESOURCES[$config['parent']];
            $parent = $request->query('parent')
                ? $this->modelOf($config['parent'])::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($request->query('parent'))
                : null;

            if (! $parent && $this->isAdmin($request)) {
                return redirect()->route($this->routes($request).'.'.$config['parent'].'.index')
                    ->with('info', "Pilih {$parentConfig['label']} dulu, lalu klik tombol + {$config['label']} di barisnya.");
            }

            $options = $this->parentOptions($request->user(), $parent?->branch_id);

            if ($options->isEmpty()) {
                return redirect()->route($this->routes($request).'.'.$config['parent'].'.create')
                    ->with('info', "Buat {$parentConfig['label']} dulu ya. Setiap {$config['label']} berada di bawah satu {$parentConfig['label']}.");
            }

            $item->forceFill([$config['key'] => $parent?->getKey()]);
        } elseif (! $this->isAdmin($request) && ! UserBranch::where('user_id', $request->user()->getKey())->exists()) {
            return $this->branchRequired();
        }

        return $this->form($request, $item, $options);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = $this->newModel();
        $this->save($request, $item);

        return redirect()->route($this->routes($request).'.'.$this->resource.'.index', $this->indexFilter($item))
            ->with('success', "{$this->config()['label']} {$item->name} berhasil ditambahkan.".$this->nextHint());
    }

    public function edit(Request $request, string $id): View
    {
        $item = $this->find($request, $id);

        return $this->form($request, $item, isset($this->config()['parent']) ? $this->parentOptions($request->user(), $item->branch_id) : collect());
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $item = $this->find($request, $id);
        $this->save($request, $item);

        return redirect()->route($this->routes($request).'.'.$this->resource.'.index', $this->indexFilter($item))
            ->with('success', "{$this->config()['label']} {$item->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $item = $this->find($request, $id);
        $label = $this->config()['label'];

        if (($child = $this->child()) && ($count = $item->{$child}()->count())) {
            $childLabel = self::RESOURCES[$child]['label'];

            return back()->with('error', "{$label} {$item->name} masih punya {$count} {$childLabel}. Hapus atau pindahkan {$childLabel}-nya dulu ya, supaya datanya tidak ikut hilang.");
        }

        if ($this->isUsed($item)) {
            return back()->with('error', "{$label} {$item->name} sudah dipakai di jadwal. Supaya riwayat absensi & honor tetap aman, ubah statusnya menjadi Tidak Aktif saja.");
        }

        $item->delete();

        return back()->with('success', "{$label} {$item->name} sudah dihapus.");
    }

    protected function isUsed(Model $item): bool
    {
        return $this->usageColumn !== null && (
            ScheduleRoutine::where($this->usageColumn, $item->getKey())->exists()
            || ScheduleSession::where($this->usageColumn, $item->getKey())->exists()
        );
    }

    protected function extraRules(Request $request, string $branchId, Model $item): array
    {
        return [];
    }

    protected function attributes(): array
    {
        return [];
    }

    /** Dijalankan sebelum validasi, mis. merapikan nomor HP. */
    protected function prepare(Request $request): void
    {
    }

    /** Dijalankan di dalam transaksi setelah data tersimpan. */
    protected function saved(Request $request, Model $item, array $data): void
    {
    }

    protected function formData(Request $request, Model $item): array
    {
        return [];
    }

    private function save(Request $request, Model $item): void
    {
        $config = $this->config();
        [$branchId, $parentId] = $this->placement($request, $item);
        $this->prepare($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', ...($this->uniqueName ? [Rule::unique($item->getTable(), 'name')
                ->where($config['key'] ?? 'branch_id', $parentId ?? $branchId)
                ->ignore($item->getKey())] : [])],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(array_keys($item::STATUS_LABELS))],
            ...$this->extraRules($request, $branchId, $item),
        ], ['name.unique' => "{$config['label']} dengan nama ini sudah ada."], ['name' => 'nama', ...$this->attributes()]);

        DB::transaction(function () use ($request, $item, $data, $branchId, $parentId, $config) {
            $item->fill(Arr::only($data, $item->getFillable()));
            $item->forceFill(['branch_id' => $branchId, ...(isset($config['key']) ? [$config['key'] => $parentId] : [])])->save();
            $this->saved($request, $item, $data);
        });
    }

    /** Branch & induk yang sah: induk harus bisa dikelola user, dan saat edit tetap di branch yang sama. */
    private function placement(Request $request, Model $item): array
    {
        $config = $this->config();

        if (isset($config['parent'])) {
            $parent = $this->modelOf($config['parent'])::accessibleBy($request->user(), Menu::ABILITY_MANAGE)
                ->when($item->exists, fn ($q) => $q->where('branch_id', $item->branch_id))
                ->find((string) $request->input('parent_id'));

            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'Pilih '.self::RESOURCES[$config['parent']]['label'].' dari daftar yang tersedia.']);
            }

            return [$parent->branch_id, $parent->getKey()];
        }

        if ($item->exists) {
            return [$item->branch_id, null];
        }

        $request->validate(['branch_id' => ['required', Rule::in($this->branches($request)->pluck('id'))]], [], ['branch_id' => 'branch']);

        return [$request->input('branch_id'), null];
    }

    private function form(Request $request, Model $item, Collection $parentOptions): View
    {
        return view('schedule.form', [
            ...$this->shared($request),
            'item' => $item,
            'parentOptions' => $parentOptions,
            'branches' => ! $item->exists && ! isset($this->config()['parent']) ? $this->branches($request) : collect(),
            ...$this->formData($request, $item),
        ]);
    }

    private function shared(Request $request): array
    {
        $isAdmin = $this->isAdmin($request);

        return [
            'resource' => $this->resource,
            'config' => $this->config(),
            'resources' => self::RESOURCES,
            'columns' => $this->columns,
            'child' => $this->child(),
            'routes' => $this->routes($request),
            'isAdmin' => $isAdmin,
            'canManage' => $this->canManage($request),
            // Superadmin tidak memfilter per branch di index (bisa ribuan); cukup lewat pencarian.
            'branches' => $isAdmin ? collect() : $this->branches($request),
        ];
    }

    private function parentOptions(User $user, ?string $branchId = null): Collection
    {
        $parent = $this->config()['parent'];

        return $this->modelOf($parent)::accessibleBy($user)
            ->with(['branch:id,name', ...$this->labelWith($parent)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->sortBy(fn ($option) => $option->branch?->name.'|'.$option->label())
            ->values();
    }

    /** Owner: branch miliknya. Superadmin: semua branch (hanya dipakai saat membuat data tingkat atas). */
    private function branches(Request $request): Collection
    {
        if ($this->isAdmin($request)) {
            return UserBranch::with('company:id,name')->orderBy('name')->get(['id', 'name', 'status', 'company_id'])
                ->sortBy(fn ($branch) => $branch->company?->name)->values();
        }

        return UserBranch::where('user_id', $request->user()->getKey())->orderBy('name')->get(['id', 'name', 'status']);
    }

    private function find(Request $request, string $id): Model
    {
        return $this->model()::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($id);
    }

    private function indexFilter(Model $item): array
    {
        $key = $this->config()['key'] ?? null;

        return $key ? ['parent' => $item->{$key}] : [];
    }

    private function nextHint(): string
    {
        return ($child = $this->child()) ? ' Lanjutkan dengan tombol + '.self::RESOURCES[$child]['label'].' di barisnya.' : '';
    }

    private function child(): ?string
    {
        return collect(self::RESOURCES)->search(fn ($config) => ($config['parent'] ?? null) === $this->resource) ?: null;
    }

    private function config(): array
    {
        return self::RESOURCES[$this->resource];
    }

    /** @return class-string<Model> */
    private function model(): string
    {
        return $this->config()['model'];
    }

    private function modelOf(string $resource): string
    {
        return self::RESOURCES[$resource]['model'];
    }

    private function labelWith(string $resource): array
    {
        return $this->modelOf($resource)::LABEL_WITH;
    }

    private function newModel(): Model
    {
        $model = $this->model();

        return new $model(['status' => $model::STATUS_ACTIVE]);
    }

}
