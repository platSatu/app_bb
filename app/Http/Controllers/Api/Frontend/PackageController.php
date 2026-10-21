<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\JsonResponse;

/**
 * Daftar paket aktif untuk halaman harga fe-konexa. Bentuk JSON sama dengan API teleios
 * (category_application, category_applications, limits, is_featured, is_trial) supaya
 * fe-konexa tidak perlu diubah; bizbos belum punya batas paket & paket unggulan,
 * jadi limits kosong dan is_featured/is_trial = false.
 */
class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = Package::query()
            ->active()
            ->with('category:id,name')
            ->orderBy('price')
            ->get(['id', 'category_application_id', 'name', 'description', 'duration', 'price'])
            ->map(fn (Package $package) => [
                'id' => $package->id,
                'category_application_id' => $package->category_application_id,
                'name' => $package->name,
                'description' => $package->description,
                'duration' => $package->duration,
                'price' => $package->price,
                'is_featured' => false,
                'is_trial' => false,
                'category_application' => $package->category?->only(['id', 'name']),
                'category_applications' => $package->category ? [$package->category->only(['id', 'name'])] : [],
                'limits' => [],
            ]);

        return response()->json(['data' => $packages]);
    }
}
