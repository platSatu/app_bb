<form method="POST" action="{{ $action }}" class="row g-2">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="col-6">
        <input type="date" name="date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('date', $default?->date->toDateString()) }}" required>
    </div>
    <div class="col-6">
        <input type="time" name="time" class="form-control" value="{{ old('time', $default?->start_at->format('H:i')) }}" required>
    </div>
    <div class="col-6">
        <select name="teacher_id" class="form-select" required>
            @foreach ($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected(old('teacher_id', $default?->teacher_id ?? $session->teacher_id) === $teacher->id)>{{ $teacher->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6">
        <select name="room_id" class="form-select" required>
            @foreach ($rooms as $room)
                <option value="{{ $room->id }}" @selected(old('room_id', $default?->room_id ?? $session->room_id) === $room->id)>{{ $room->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <button class="btn btn-outline-primary">{{ $button }}</button>
    </div>
</form>
