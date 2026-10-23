{{-- Branch tujuan: tersembunyi kalau cuma satu, pilihan kalau beberapa. $branches --}}
@if ($branches->count() === 1)
    <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}">
@else
    <label class="form-label">Branch</label>
    <select name="branch_id" class="form-select mb-3" required>
        @foreach ($branches as $branch)
            <option value="{{ $branch->id }}" @selected(old('branch_id', request('branch')) === $branch->id)>{{ $branch->name }}</option>
        @endforeach
    </select>
@endif
