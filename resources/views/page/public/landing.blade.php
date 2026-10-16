{{-- Landing page publik. Semua isi teks biasa (di-escape), tidak ada HTML dari user/AI. --}}
@php
    $img = fn ($path) => $path ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
    $button = function ($section) use ($page) {
        $target = $section->get('button_page_id') ? \App\Models\PageCategory::where('branch_id', $page->branch_id)->where('status', 'active')->find($section->get('button_page_id')) : null;

        return $target ? $target->url() : $section->get('button_url');
    };
@endphp

@if ($preview ?? false)
    <div class="bg-warning-subtle text-center small py-1">Preview — belum disimpan</div>
@endif

@forelse ($sections as $section)
    @switch($section->type)
        @case('hero')
            <section class="py-5 bg-brand-soft">
                <div class="container py-md-4" style="max-width: 1140px;">
                    <div class="row align-items-center g-4">
                        <div class="{{ $section->get('image') ? 'col-md-6' : 'col-md-10 mx-auto text-center' }}">
                            <h1 class="display-5 fw-bold mb-3">{{ $section->get('title') ?: $page->name }}</h1>
                            @if ($section->get('subtitle')) <p class="lead text-muted mb-4" style="white-space: pre-line;">{{ $section->get('subtitle') }}</p> @endif
                            @if ($section->get('button_label') && ($url = $button($section)))
                                <a href="{{ $url }}" class="btn btn-brand btn-lg px-4">{{ $section->get('button_label') }}</a>
                            @endif
                        </div>
                        @if ($section->get('image'))
                            <div class="col-md-6"><img src="{{ $img($section->get('image')) }}" alt="" class="img-fluid rounded-4 shadow-sm"></div>
                        @endif
                    </div>
                </div>
            </section>
            @break
        @case('features')
            <section class="py-5 bg-white">
                <div class="container" style="max-width: 1140px;">
                    @if ($section->get('title')) <h2 class="h3 fw-bold text-center mb-4">{{ $section->get('title') }}</h2> @endif
                    <div class="row g-3">
                        @foreach ($section->rows() as [$title, $text])
                            <div class="col-md-4">
                                <div class="h-100 border rounded-4 p-4">
                                    <i class="ri-checkbox-circle-fill text-brand fs-3"></i>
                                    <h3 class="h6 fw-semibold mt-2">{{ $title }}</h3>
                                    @if ($text ?? null) <p class="text-muted small mb-0">{{ $text }}</p> @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
            @break
        @case('about')
            <section class="py-5">
                <div class="container" style="max-width: 1140px;">
                    <div class="row align-items-center g-4">
                        @if ($section->get('image'))
                            <div class="col-md-5"><img src="{{ $img($section->get('image')) }}" alt="" class="img-fluid rounded-4 shadow-sm" loading="lazy"></div>
                        @endif
                        <div class="{{ $section->get('image') ? 'col-md-7' : 'col-md-9 mx-auto' }}">
                            @if ($section->get('title')) <h2 class="h3 fw-bold mb-3">{{ $section->get('title') }}</h2> @endif
                            <p class="text-muted mb-0" style="white-space: pre-line;">{{ $section->get('body') }}</p>
                        </div>
                    </div>
                </div>
            </section>
            @break
        @case('gallery')
            <section class="py-5 bg-white">
                <div class="container" style="max-width: 1140px;">
                    @if ($section->get('title')) <h2 class="h3 fw-bold text-center mb-4">{{ $section->get('title') }}</h2> @endif
                    <div class="row g-2">
                        @foreach ((array) $section->get('images', []) as $path)
                            <div class="col-6 col-md-3"><img src="{{ $img($path) }}" alt="" class="w-100 rounded-3 product-thumb" loading="lazy"></div>
                        @endforeach
                    </div>
                </div>
            </section>
            @break
        @case('pricing')
            <section class="py-5">
                <div class="container" style="max-width: 1140px;">
                    @if ($section->get('title')) <h2 class="h3 fw-bold text-center mb-4">{{ $section->get('title') }}</h2> @endif
                    <div class="row g-3 justify-content-center">
                        @foreach ($section->rows() as $cols)
                            <div class="col-md-4">
                                <div class="h-100 bg-white border rounded-4 p-4 text-center">
                                    <h3 class="h6 fw-semibold">{{ $cols[0] }}</h3>
                                    @if ($cols[1] ?? null) <div class="fs-4 fw-bold text-brand my-2">{{ $cols[1] }}</div> @endif
                                    @if ($cols[2] ?? null) <p class="text-muted small mb-0">{{ $cols[2] }}</p> @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
            @break
        @case('faq')
            <section class="py-5 bg-white">
                <div class="container" style="max-width: 820px;">
                    @if ($section->get('title')) <h2 class="h3 fw-bold text-center mb-4">{{ $section->get('title') }}</h2> @endif
                    @foreach ($section->rows() as $cols)
                        <details class="border rounded-3 p-3 mb-2">
                            <summary class="fw-semibold">{{ $cols[0] }}</summary>
                            <p class="text-muted mt-2 mb-0">{{ $cols[1] ?? '' }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
            @break
        @case('video')
            @if ($id = $section->youtubeId())
                <section class="py-5">
                    <div class="container" style="max-width: 960px;">
                        @if ($section->get('title')) <h2 class="h3 fw-bold text-center mb-4">{{ $section->get('title') }}</h2> @endif
                        <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-sm">
                            <iframe src="https://www.youtube-nocookie.com/embed/{{ $id }}" title="Video" loading="lazy" allowfullscreen
                                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"></iframe>
                        </div>
                    </div>
                </section>
            @endif
            @break
        @case('cta')
            <section class="py-5 text-white" style="background: var(--brand);">
                <div class="container text-center" style="max-width: 820px;">
                    @if ($section->get('title')) <h2 class="h3 fw-bold mb-2">{{ $section->get('title') }}</h2> @endif
                    @if ($section->get('body')) <p class="mb-4 opacity-75">{{ $section->get('body') }}</p> @endif
                    @if ($section->get('button_label') && ($url = $button($section)))
                        <a href="{{ $url }}" class="btn btn-light btn-lg px-4">{{ $section->get('button_label') }}</a>
                    @endif
                </div>
            </section>
            @break
        @case('contact')
            @php($wa = \App\Models\User::normalizePhone($section->get('whatsapp')))
            <section class="py-5 bg-white" id="kontak">
                <div class="container" style="max-width: 820px;">
                    @if ($section->get('title')) <h2 class="h3 fw-bold text-center mb-4">{{ $section->get('title') }}</h2> @endif
                    <div class="row g-3">
                        @if ($section->get('address')) <div class="col-md-6"><div class="fw-semibold"><i class="ri-map-pin-line text-brand"></i> Alamat</div><div class="text-muted" style="white-space: pre-line;">{{ $section->get('address') }}</div></div> @endif
                        @if ($section->get('hours')) <div class="col-md-6"><div class="fw-semibold"><i class="ri-time-line text-brand"></i> Jam buka</div><div class="text-muted" style="white-space: pre-line;">{{ $section->get('hours') }}</div></div> @endif
                    </div>
                    <div class="d-flex gap-2 justify-content-center mt-4 flex-wrap">
                        @if (preg_match('~^https://(www\.)?(google\.[a-z.]+/maps|maps\.app\.goo\.gl|goo\.gl/maps)~i', (string) $section->get('maps_url')))
                            <a href="{{ $section->get('maps_url') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="ri-map-2-line me-1"></i>Buka Maps</a>
                        @endif
                        @if ($wa)
                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="btn btn-brand"><i class="ri-whatsapp-line me-1"></i>Chat WhatsApp</a>
                        @endif
                    </div>
                </div>
            </section>
            @break
    @endswitch
@empty
    <section class="py-5 text-center">
        <div class="container">
            <h1 class="h3">{{ $page->name }}</h1>
            <p class="text-muted">Halaman ini sedang disiapkan.</p>
        </div>
    </section>
@endforelse

<footer class="py-4 text-center text-muted small">© {{ now()->year }} {{ $page->branch?->company?->name ?? $page->name }}</footer>
