<div class="input-group input-group-sm mb-1" data-option>
    <label class="input-group-text" title="Tandai sebagai jawaban benar">
        <input class="form-check-input mt-0" type="radio" name="questions[{{ $i }}][correct]" value="{{ $j }}" @checked($option['is_correct'] ?? false)>
    </label>
    <input type="text" name="questions[{{ $i }}][options][{{ $j }}][label]" value="{{ $option['label'] ?? '' }}" maxlength="255" class="form-control" placeholder="Teks pilihan">
    <button type="button" class="btn btn-outline-secondary" data-remove-option title="Hapus pilihan"><i class="ri-close-line"></i></button>
</div>
