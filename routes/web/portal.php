<?php

use App\Http\Controllers\Portal\JoinController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\TeachingController;
use App\Http\Controllers\Schedule\PortalAdminController;
use Illuminate\Support\Facades\Route;

// Owner: link pendaftaran per branch & persetujuan pendaftar.
Route::middleware(['auth', 'permission:schedules.portal'])->prefix('schedules/portal')->name('schedules.portal.')->group(function () {
    Route::get('/', [PortalAdminController::class, 'requests'])->name('requests');
    Route::post('requests/{portalRequest}/approve', [PortalAdminController::class, 'approve'])->middleware('throttle:60,1')->name('approve');
    Route::post('requests/{portalRequest}/reject', [PortalAdminController::class, 'reject'])->middleware('throttle:60,1')->name('reject');
    Route::get('links', [PortalAdminController::class, 'links'])->name('links');
    Route::post('links/{link}/toggle', [PortalAdminController::class, 'toggle'])->middleware('throttle:30,1')->name('links.toggle');
    Route::post('links/{link}/regenerate', [PortalAdminController::class, 'regenerate'])->middleware('throttle:10,1')->name('links.regenerate');
});

// Link pendaftaran publik (tamu atau member yang sudah masuk).
Route::get('daftar/{token}', [JoinController::class, 'show'])->middleware('throttle:60,1')->name('portal.join');
Route::post('daftar/{token}', [JoinController::class, 'store'])->middleware('throttle:10,1')->name('portal.join.store');

// Halaman peran pengajar & murid/orang tua. Data selalu dibatasi ke murid/pengajar milik akun yang login.
Route::middleware('auth')->prefix('portal')->name('portal.')->group(function () {
    Route::get('jadwal', [PortalController::class, 'schedule'])->name('schedule');
    Route::get('kehadiran', [PortalController::class, 'attendance'])->name('attendance');

    Route::get('mengajar', [TeachingController::class, 'index'])->name('teaching');
    Route::get('absen', [TeachingController::class, 'pending'])->name('absen');
    Route::get('absen/{session}', [TeachingController::class, 'show'])->name('absen.show');
    Route::put('absen/{session}', [TeachingController::class, 'save'])->middleware('throttle:60,1')->name('absen.save');
});
