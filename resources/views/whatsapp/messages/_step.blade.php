{{-- Satu pesan dalam Message Bertahap. $i = indeks di form, $step = nilai lama (old input). --}}
<div class="border rounded p-3 wa-step" data-step>
    @if (! empty($step['id']))
        <input type="hidden" name="steps[{{ $i }}][id]" value="{{ $step['id'] }}">
    @endif
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-semibold">Pesan ke-<span data-step-no>1</span></div>
        <button type="button" class="btn btn-sm btn-light text-danger" data-remove-step title="Hapus pesan ini"><i class="ri-delete-bin-line"></i></button>
    </div>
    <div class="row g-2 align-items-end mb-2">
        <div class="col-sm-4 col-md-3">
            <label class="form-label small mb-1">Dikirim H+ (hari)</label>
            <input type="number" min="0" max="365" name="steps[{{ $i }}][delay_days]" value="{{ $step['delay_days'] ?? '' }}" required class="form-control form-control-sm @error("steps.$i.delay_days") is-invalid @enderror">
            @error("steps.$i.delay_days") <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col">
            <div class="form-text mb-1">0 = di tanggal mulai, 3 = tiga hari setelah tanggal mulai.</div>
        </div>
    </div>
    <div data-content>
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" name="steps[{{ $i }}][use_template]" value="1" id="step-tpl-{{ $i }}" data-use-template @checked(! empty($step['use_template']))>
            <label class="form-check-label small" for="step-tpl-{{ $i }}">Gunakan Template WA</label>
        </div>
        <div data-template-pane>
            <select name="steps[{{ $i }}][wa_template_id]" class="form-select form-select-sm @error("steps.$i.wa_template_id") is-invalid @enderror" data-template-select>
                <option value="">-- Pilih Template --</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}" @selected(($step['wa_template_id'] ?? null) === $template->id)>{{ $template->name }}</option>
                @endforeach
            </select>
            @error("steps.$i.wa_template_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="bg-light rounded p-2 mt-2 small d-none" style="white-space: pre-wrap" data-template-preview></div>
        </div>
        <div data-manual-pane>
            <textarea name="steps[{{ $i }}][body]" rows="3" maxlength="4000" class="form-control form-control-sm mb-2 @error("steps.$i.body") is-invalid @enderror" placeholder="Halo {nama}, ...">{{ $step['body'] ?? '' }}</textarea>
            @error("steps.$i.body") <div class="invalid-feedback mb-2">{{ $message }}</div> @enderror
            @if (! empty($step['upload_name']))
                <div class="small mb-1"><i class="ri-attachment-2"></i> Lampiran saat ini: <strong>{{ $step['upload_name'] }}</strong></div>
            @endif
            <input type="file" name="steps[{{ $i }}][file]" class="form-control form-control-sm @error("steps.$i.file") is-invalid @enderror">
            @error("steps.$i.file") <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
