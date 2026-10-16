@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-copy]');
                if (!button) return;
                navigator.clipboard.writeText(button.dataset.copy).then(function () {
                    var html = button.innerHTML;
                    button.innerHTML = '<i class="ri-check-line"></i> Tersalin';
                    setTimeout(function () { button.innerHTML = html; }, 1500);
                });
            });
        </script>
    @endpush
@endonce
