@extends('layouts.dashboard')

@section('title', 'Soal Quiz')
@section('page-section', \App\Support\Apps::pageSection($routes))

@php
    $rows = old('questions', $questions->map(fn ($q) => [
        'id' => $q->id, 'type' => $q->type, 'question' => $q->question, 'points' => $q->points, 'status' => $q->status,
        'answer_keys' => $q->answer_keys,
        'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'is_correct' => $o->is_correct])->all(),
    ])->all());
@endphp

@section('content')
    @include('page.category._tabs', ['active' => 'quiz-questions'])

    <form method="POST" action="{{ route($routes.'.quiz.questions.sync', $page) }}" id="quiz-form">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h6 class="mb-1 fw-semibold">Soal Quiz</h6>
                    <div class="text-muted small lh-sm">
                        {{ count($rows) }} soal · total {{ collect($rows)->where('type', '!=', 'textarea')->sum('points') }} poin. Geser <i class="ri-draggable"></i> untuk mengurutkan.
                    </div>
                </div>
                @if ($canManage)
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="ri-save-line me-1"></i>Simpan Semua</button>
                @endif
            </div>
            <div class="card-body bg-light p-3 p-md-4">
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <fieldset @disabled(! $canManage)>
                    <div data-sortable id="question-rows">
                        @foreach ($rows as $i => $row)
                            @include('page.quiz._question-row')
                        @endforeach
                    </div>
                    <div id="question-empty" class="text-center text-muted py-4 {{ count($rows) ? 'd-none' : '' }}">
                        <i class="ri-question-answer-line fs-2 d-block mb-1"></i>Belum ada soal. Klik Tambah Soal.
                    </div>
                </fieldset>
                @if ($canManage)
                    <button type="button" class="btn btn-outline-primary w-100 mt-2" id="add-question"><i class="ri-add-line"></i> Tambah Soal</button>
                    <template id="question-template">@include('page.quiz._question-row', ['i' => '__Q__', 'row' => ['options' => [[], []]]])</template>
                    <template id="option-template">@include('page.quiz._option-row', ['i' => '__Q__', 'j' => '__O__', 'option' => []])</template>
                @endif
            </div>
        </div>
    </form>

    <script src="{{ asset('be/assets/js/sortable-rows.js') }}"></script>
    <script>
        (function () {
            var rows = document.getElementById('question-rows');
            var questionTemplate = document.getElementById('question-template');
            var optionTemplate = document.getElementById('option-template');
            var counter = 0;

            function refresh() {
                var list = rows.querySelectorAll('[data-row]');
                document.getElementById('question-empty').classList.toggle('d-none', list.length > 0);
                list.forEach(function (row, index) {
                    var type = row.querySelector('[data-type]').value;
                    row.querySelector('[data-row-number]').textContent = '#' + (index + 1);
                    row.querySelectorAll('[data-box]').forEach(function (box) { box.hidden = box.dataset.box !== type; });
                    row.querySelector('[data-points-box]').hidden = type === 'textarea';
                });
            }

            if (questionTemplate) {
                document.getElementById('add-question').addEventListener('click', function () {
                    var index = 'n' + (counter++);
                    rows.insertAdjacentHTML('beforeend', questionTemplate.innerHTML.replace(/__Q__/g, index).replace(/__O__/g, function () { return 'o' + (counter++); }));
                    rows.lastElementChild.querySelector('textarea').focus();
                    refresh();
                });

                rows.addEventListener('click', function (event) {
                    var row = event.target.closest('[data-row]');
                    if (event.target.closest('[data-remove]')) {
                        if (!confirm('Hapus soal ini? Terhapus setelah Simpan Semua. Hasil lama tetap tersimpan.')) return;
                        row.remove();
                    } else if (event.target.closest('[data-add-option]')) {
                        row.querySelector('[data-options]').insertAdjacentHTML('beforeend',
                            optionTemplate.innerHTML.replace(/__Q__/g, row.dataset.index).replace(/__O__/g, 'o' + (counter++)));
                    } else if (event.target.closest('[data-remove-option]')) {
                        event.target.closest('[data-option]').remove();
                    }
                    refresh();
                });

                rows.addEventListener('change', refresh);
            }

            refresh();
        })();
    </script>
@endsection
