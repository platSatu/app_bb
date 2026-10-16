{{-- Pilihan branch (hanya tampil bila owner punya lebih dari satu). --}}
@if ($branches->count() > 1)
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="small text-muted text-nowrap">Branch</label>
        <select name="branch" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            @foreach ($branches as $option)
                <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </form>
@endif
