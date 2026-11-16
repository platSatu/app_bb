{{--
    Halaman publik Registrasi. Isiannya dibaca dari Form Pendaftaran milik page ini
    (page_form_fields), jadi apa pun yang disusun owner langsung tampil di sini.
    Nama input sengaja f[<id field>] supaya cocok dengan aturan validasi di PublicRegistrationController.
--}}
@push('head')
    <style>
        .reg-card { border: 0; border-radius: 1rem; }
        .reg-badge { width: 64px; height: 64px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.9rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); }
        .chip { background: #f3f4f6; border-radius: 999px; padding: .25rem .75rem; font-size: .8125rem; }
    </style>
@endpush

@if ($reference = session('registered'))
    <div class="card reg-card shadow-sm">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="reg-badge mb-3"><i class="ri-checkbox-circle-line"></i></span>
            <h1 class="h4 fw-bold mb-1">Terima kasih sudah mendaftar!</h1>
            <p class="text-muted mb-4">{{ $page->name }}</p>
            <p class="text-muted small mb-3">Data Anda sudah kami terima. Simpan nomor di bawah ini sebagai bukti pendaftaran ya.</p>
            <span class="chip"><i class="ri-hashtag me-1"></i>{{ $reference }}</span>
        </div>
    </div>
@else
    <div class="card reg-card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <span class="reg-badge mb-3"><i class="ri-user-add-line"></i></span>
            <h1 class="h4 fw-bold mb-1">{{ $page->name }}</h1>
            @if ($page->description)
                <p class="text-muted" style="white-space: pre-line;">{{ $page->description }}</p>
            @endif

            @if ($page->fields->isEmpty())
                <p class="text-muted mb-0 mt-3">Formulir pendaftaran sedang disiapkan. Coba lagi nanti ya.</p>
            @else
                @if ($errors->any())
                    <div class="alert alert-danger small py-2 mt-3">Ada isian yang belum benar, mohon diperiksa lagi.</div>
                @endif

                <form method="POST" action="{{ route('page.register', $page->slug) }}" enctype="multipart/form-data" class="mt-4">
                    @csrf
                    {{-- Honeypot: diisi bot, tidak pernah diisi orang. Harus tetap kosong. --}}
                    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="d-none" aria-hidden="true">

                    @foreach ($page->fields as $field)
                        @php($key = 'f.'.$field->id)
                        @php($name = 'f['.$field->id.']')
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="field-{{ $field->id }}">
                                {{ $field->label }}@if ($field->is_required)<span class="text-danger"> *</span>@endif
                            </label>

                            @if ($field->type === 'textarea')
                                <textarea id="field-{{ $field->id }}" name="{{ $name }}" rows="3" class="form-control @error($key) is-invalid @enderror" placeholder="{{ $field->placeholder }}" @required($field->is_required)>{{ old($key) }}</textarea>
                            @elseif ($field->hasOptions())
                                @if ($field->type === 'select')
                                    <select id="field-{{ $field->id }}" name="{{ $name }}" class="form-select @error($key) is-invalid @enderror" @required($field->is_required)>
                                        <option value="">-- Pilih --</option>
                                        @foreach ($field->options ?? [] as $option)
                                            <option value="{{ $option }}" @selected(old($key) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    @php($checkbox = $field->type === 'checkbox')
                                    @php($chosen = (array) old($key, []))
                                    @foreach ($field->options ?? [] as $i => $option)
                                        <div class="form-check">
                                            <input class="form-check-input @error($key) is-invalid @enderror" type="{{ $checkbox ? 'checkbox' : 'radio' }}"
                                                name="{{ $name }}{{ $checkbox ? '[]' : '' }}" id="field-{{ $field->id }}-{{ $i }}" value="{{ $option }}"
                                                @checked($checkbox ? in_array($option, $chosen, true) : old($key) === $option)>
                                            <label class="form-check-label" for="field-{{ $field->id }}-{{ $i }}">{{ $option }}</label>
                                        </div>
                                    @endforeach
                                @endif
                            @elseif ($field->type === 'file')
                                <input id="field-{{ $field->id }}" type="file" name="{{ $name }}" class="form-control @error($key) is-invalid @enderror"
                                    accept=".{{ str_replace(',', ',.', \App\Models\PageFormField::FILE_MIMES) }}" @required($field->is_required)>
                                <div class="form-text">Format {{ str_replace(',', ', ', \App\Models\PageFormField::FILE_MIMES) }}, maksimal {{ (int) (\App\Models\PageFormField::FILE_MAX_KB / 1024) }} MB.</div>
                            @else
                                @php($input = ['phone' => 'tel', 'email' => 'email', 'number' => 'number', 'date' => 'date'][$field->type] ?? 'text')
                                <input id="field-{{ $field->id }}" type="{{ $input }}" name="{{ $name }}" value="{{ old($key) }}"
                                    class="form-control @error($key) is-invalid @enderror" placeholder="{{ $field->placeholder }}" @required($field->is_required)>
                            @endif

                            @if ($field->help_text)
                                <div class="form-text">{{ $field->help_text }}</div>
                            @endif
                            @error($key) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    @endforeach

                    <div class="mb-3">@include('auth.partials.turnstile')</div>

                    <button type="submit" class="btn btn-brand w-100 py-2 mt-2"><i class="ri-send-plane-line me-1"></i>Kirim Pendaftaran</button>
                </form>
            @endif
        </div>
    </div>
@endif
