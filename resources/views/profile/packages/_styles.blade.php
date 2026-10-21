@once
    @push('styles')
        <style>
            .pkg-card { border: 1px solid rgba(0,0,0,.06); border-radius: 1rem; transition: transform .15s ease, box-shadow .15s ease; overflow: hidden; }
            .pkg-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(16,24,40,.08); }
            .pkg-head { padding: 1.25rem; background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .12), rgba(var(--bs-primary-rgb), .02)); }
            .pkg-card.is-trial .pkg-head { background: linear-gradient(135deg, rgba(var(--bs-info-rgb), .18), rgba(var(--bs-info-rgb), .03)); }
            .pkg-price { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
            .pkg-app { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; background: rgba(var(--bs-primary-rgb), .08); color: var(--bs-primary); }
            .pkg-feature { display: flex; gap: .5rem; font-size: .875rem; margin-bottom: .35rem; }
            .pkg-feature i { color: var(--bs-success); }
            .pkg-option { border: 1px solid rgba(0,0,0,.1); border-radius: .75rem; padding: .85rem 1rem; cursor: pointer; display: block; }
            .btn-check:checked + .pkg-option { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .05); }
        </style>
    @endpush
@endonce
