<?php

use App\Http\Controllers\Schedule\CategoryController;
use App\Http\Controllers\Schedule\GradeController;
use App\Http\Controllers\Schedule\RoomController;
use App\Http\Controllers\Schedule\RoutineController;
use App\Http\Controllers\Schedule\SessionController;
use App\Http\Controllers\Schedule\StudentController;
use App\Http\Controllers\Schedule\SubjectController;
use App\Http\Controllers\Schedule\TeacherController;
use Illuminate\Support\Facades\Route;

// Aplikasi Jadwal, dipakai bersama owner (/schedules) & superadmin (/superadmin/schedules).
// Hak akses tetap dicek lagi di controller lewat accessibleBy().
$resources = [
    'routines' => RoutineController::class,
    'rooms' => RoomController::class,
    'subjects' => SubjectController::class,
    'categories' => CategoryController::class,
    'grades' => GradeController::class,
    'teachers' => TeacherController::class,
    'students' => StudentController::class,
];

// Sesi: lihat (daftar & detail) dan kelola (absensi, batal, pindah, pengganti).
$sessionRead = function () {
    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
};

$sessionWrite = function () {
    Route::put('sessions/{session}/attendance', [SessionController::class, 'attendance'])->middleware('throttle:60,1')->name('sessions.attendance');
    Route::post('sessions/{session}/cancel', [SessionController::class, 'cancel'])->middleware('throttle:30,1')->name('sessions.cancel');
    Route::put('sessions/{session}/move', [SessionController::class, 'move'])->middleware('throttle:30,1')->name('sessions.move');
    Route::post('sessions/{session}/attendances/{attendance}/makeup', [SessionController::class, 'makeup'])->middleware('throttle:30,1')->name('sessions.makeup');
};

Route::middleware('auth')->prefix('schedules')->name('schedules.')->group(function () use ($resources, $sessionRead, $sessionWrite) {
    foreach ($resources as $uri => $controller) {
        Route::resource($uri, $controller)->except('show')->middleware("permission:schedules.{$uri}");
    }

    Route::middleware('permission:schedules.sessions')->group(function () use ($sessionRead, $sessionWrite) {
        $sessionRead();
        $sessionWrite();
    });
});

Route::middleware(['auth', 'superadmin'])->prefix('superadmin/schedules')->name('superadmin.schedules.')->group(function () use ($resources, $sessionRead, $sessionWrite) {
    foreach ($resources as $uri => $controller) {
        Route::get($uri, [$controller, 'index'])->middleware('permission:superadmin.schedules,view')->name("{$uri}.index");
        Route::resource($uri, $controller)->except(['index', 'show'])->middleware('permission:superadmin.schedules,manage');
    }

    Route::middleware('permission:superadmin.schedules,view')->group($sessionRead);
    Route::middleware('permission:superadmin.schedules,manage')->group($sessionWrite);
});
