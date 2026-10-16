@foreach ($fields as $field)
    @php
        $name = 'fields['.$field->id.']';
        $key = 'fields.'.$field->id;
        $id = 'f-'.$field->id;
        $old = $preview ? null : old($key);
        $invalid = ! $preview && $errors->has($key) ? 'is-invalid' : '';
    @endphp
    <div class="mb-3">
        @if ($field->type !== 'checkbox' && $field->type !== 'radio')
            <label for="{{ $id }}" class="form-label">{{ $field->label }} @if ($field->is_required)<span class="text-danger">*</span>@endif</label>
        @else
            <div class="form-label">{{ $field->label }} @if ($field->is_required)<span class="text-danger">*</span>@endif</div>
        @endif

        @switch($field->type)
            @case('textarea')
                <textarea id="{{ $id }}" name="{{ $name }}" rows="4" class="form-control {{ $invalid }}" placeholder="{{ $field->placeholder }}" @disabled($preview)>{{ $old }}</textarea>
                @break
            @case('select')
                <select id="{{ $id }}" name="{{ $name }}" class="form-select {{ $invalid }}" @disabled($preview)>
                    <option value="">{{ $field->placeholder ?: '-- Pilih --' }}</option>
                    @foreach ($field->options ?? [] as $option)
                        <option value="{{ $option }}" @selected($old === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @break
            @case('radio')
            @case('checkbox')
                @foreach ($field->options ?? [] as $option)
                    <div class="form-check">
                        <input class="form-check-input {{ $invalid }}" type="{{ $field->type }}" id="{{ $id }}-{{ $loop->index }}"
                            name="{{ $name }}{{ $field->type === 'checkbox' ? '[]' : '' }}" value="{{ $option }}"
                            @checked(in_array($option, (array) $old, true)) @disabled($preview)>
                        <label class="form-check-label" for="{{ $id }}-{{ $loop->index }}">{{ $option }}</label>
                    </div>
                @endforeach
                @break
            @case('file')
                <input type="file" id="{{ $id }}" name="{{ $name }}" class="form-control {{ $invalid }}" accept=".jpg,.jpeg,.png,.webp,.pdf" @disabled($preview)>
                <div class="form-text">JPG, PNG, WebP, atau PDF, maksimal {{ \App\Models\PageFormField::FILE_MAX_KB / 1024 }} MB.</div>
                @break
            @default
                <input type="{{ ['email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date'][$field->type] ?? 'text' }}"
                    id="{{ $id }}" name="{{ $name }}" class="form-control {{ $invalid }}" value="{{ $old }}"
                    placeholder="{{ $field->placeholder }}" @if ($field->type === 'phone') inputmode="tel" @endif @disabled($preview)>
        @endswitch

        @if ($field->help_text)
            <div class="form-text">{{ $field->help_text }}</div>
        @endif
        @unless ($preview)
            @error($key) <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
            @error($key.'.*') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
        @endunless
    </div>
@endforeach
