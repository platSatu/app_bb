<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Support\Web\WebCrud;
use App\Support\Web\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebFeature;
use App\Support\Web\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for highlighted product/service features
 * (App\Models\WebFeature) — flat list, no category, one image per row.
 * Same shape as Superadmin\Web\FaqController, plus image upload/cleanup
 * through App\Support\Web\WebImageUploader (same pattern as
 * Superadmin\Web\ArticleController's `images` handling, minus slug/meta
 * tags — WebFeature doesn't have those).
 *
 * Rows already exist from database/seeders/WebFeatureSeeder.php with
 * `images` left null — this CRUD is what lets a superadmin upload the
 * actual image per feature afterwards, per the user's request.
 */
class FeatureController extends Controller
{
    private const IMAGE_SUBDIRECTORY = 'features';

    public function index(Request $request): View
    {
        $search = $request->string('search')->value();

        // Urut sort_order (bisa diatur dengan tombol naik/turun). Sengaja
        // tidak lewat WebCrud::getAll() yang selalu urut created_at desc;
        // route ini sudah di belakang middleware 'superadmin'.
        $features = WebFeature::with('user')
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('superadmin.web.features.index', compact('features'));
    }

    public function create(): View
    {
        $users = User::whereIn('user_type', [User::TYPE_SUPERADMIN, User::TYPE_STAFF])->orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.features.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($request->hasFile('images')) {
            $validated['images'] = WebImageUploader::upload($request->file('images'), self::IMAGE_SUBDIRECTORY);
        }

        $validated['sort_order'] = SortOrder::next(WebFeature::query());

        WebCrud::store(WebFeature::class, $validated);

        return redirect()
            ->route('superadmin.web.features.index')
            ->with('success', 'Fitur berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $feature = WebCrud::find(WebFeature::class, $id);
        $users = User::whereIn('user_type', [User::TYPE_SUPERADMIN, User::TYPE_STAFF])->orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.features.edit', compact('feature', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($request->hasFile('images')) {
            $validated['images'] = WebImageUploader::upload($request->file('images'), self::IMAGE_SUBDIRECTORY);
        }

        WebCrud::update(
            WebFeature::class,
            $id,
            $validated,
            beforeUpdate: function ($model, $data) {
                if (array_key_exists('images', $data) && $model->images && $model->images !== $data['images']) {
                    WebImageUploader::delete($model->images);
                }

                return $data;
            }
        );

        return redirect()
            ->route('superadmin.web.features.index')
            ->with('success', 'Fitur berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        WebCrud::delete(WebFeature::class, $id, function ($model) {
            WebImageUploader::delete($model->images);
        });

        return redirect()
            ->route('superadmin.web.features.index')
            ->with('success', 'Fitur berhasil dihapus.');
    }

    public function move(string $id, string $direction): RedirectResponse
    {
        SortOrder::move(WebFeature::findOrFail($id), $direction, WebFeature::query());

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'images' => ['nullable', 'image', 'max:4096'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
