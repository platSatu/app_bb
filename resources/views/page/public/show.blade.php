@extends('page.public.layout')

@section('title', $open ? $page->name : 'Page tidak tersedia')
@section('width', $open && $page->isTemplate(\App\Models\PageType::TEMPLATE_CATALOG) ? '1140px' : '720px')
@if ($open && $page->isTemplate(\App\Models\PageType::TEMPLATE_LANDING))
    @section('bare', true)
@endif

@section('content')
    @if ($open && ($view = $page->templateConfig('public')))
        @include($view)
    @elseif ($open)
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5 text-center">
                <h1 class="h3 mb-2">{{ $page->name }}</h1>
                @if ($page->description)
                    <p class="text-muted mb-0" style="white-space: pre-line;">{{ $page->description }}</p>
                @endif
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5 text-center">
                <i class="ri-time-line fs-1 text-muted"></i>
                <h1 class="h4 mt-2">Page ini sedang tidak tersedia</h1>
                <p class="text-muted mb-0">
                    @if ($page->isActive() && $page->start_date?->isFuture())
                        Page akan dibuka mulai {{ $page->start_date->translatedFormat('d F Y') }}.
                    @else
                        Masa berlakunya sudah selesai atau sedang ditutup oleh pemiliknya.
                    @endif
                </p>
            </div>
        </div>
    @endif
@endsection
